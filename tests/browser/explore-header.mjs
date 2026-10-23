import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

// Run against the seeded local Laravel app and its Vite dev server. This checks
// actual header geometry, not just its CSS class. No screenshots are needed.
const { chromium, devices } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const origin = process.env.EXPLORE_TEST_URL || 'http://127.0.0.1:8001';
assert.ok(['localhost', '127.0.0.1'].includes(new URL(origin).hostname), 'Local tests only');
const diagnose = process.argv.includes('--diagnose');
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const androidActivitySource = await readFile(new URL('../../android/LocalAndroidPreview/src/com/zulors/localpreview/MainActivity.java', import.meta.url), 'utf8');
assert.ok(androidActivitySource.includes(
    'html.zulors-android-app .mobile-app-content,html.zulors-android-app .mobile-app-stage,html.zulors-android-app .mobile-route-view,html.zulors-android-app .base-publication{overflow-x:clip!important;overflow-y:visible!important;}'
), 'Android WebView guards keep route wrappers from becoming sticky scrollports');

async function login(page) {
    await page.goto(`${origin}/auth/login`, { waitUntil: 'domcontentloaded' });
    await page.getByPlaceholder('Login or Email').fill(process.env.EXPLORE_TEST_EMAIL || 'user@example.com');
    await page.getByRole('button', { name: 'Continue with Email', exact: true }).click();
    await page.getByPlaceholder('Enter password').fill(process.env.EXPLORE_TEST_PASSWORD || 'password');
    await page.getByRole('button', { name: 'Continue with Email', exact: true }).click();
    await page.waitForURL(url => !url.pathname.startsWith('/auth/'));
}

async function measure(header) {
    return header.evaluate(el => {
        const rect = el.getBoundingClientRect();
        const input = el.querySelector('input');
        const inputRect = input?.getBoundingClientRect();
        const hit = inputRect && document.elementFromPoint(inputRect.x + inputRect.width / 2, inputRect.y + inputRect.height / 2);
        return {
            scrollY, top: Math.round(rect.top), bottom: Math.round(rect.bottom),
            hidden: el.classList.contains('-translate-y-full'),
            searchClickable: Boolean(input && hit === input),
        };
    });
}

async function wheel(page, delta) {
    await page.mouse.wheel(0, delta);
    await page.waitForTimeout(450); // Native scrolling and the 300ms header transition.
}

async function swipe(page, startY, endY) {
    const session = await page.context().newCDPSession(page);
    try {
        await session.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: 215, y: startY }] });
        for (let step = 1; step <= 10; step++) {
            await session.send('Input.dispatchTouchEvent', {
                type: 'touchMove', touchPoints: [{ x: 215, y: startY + (endY - startY) * step / 10 }],
            });
            await page.waitForTimeout(35);
        }
        await session.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await page.waitForTimeout(700);
    } finally {
        await session.detach();
    }
}

try {
    for (const mode of [
        { name: 'mobile-web', options: { ...devices['iPhone 13'], viewport: { width: 430, height: 932 } } },
        { name: 'narrow-desktop', options: { viewport: { width: 560, height: 900 } } },
        { name: 'android-app', options: { ...devices['Pixel 5'], userAgent: `${devices['Pixel 5'].userAgent} ZulorsAndroidApp` } },
    ]) {
        if (process.env.EXPLORE_TEST_MODE && mode.name !== process.env.EXPLORE_TEST_MODE) continue;
        const context = await browser.newContext(mode.options);
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await login(page);
        if (mode.name === 'narrow-desktop') {
            // Use the app's own mobile-layout switch, as in a narrow Chrome window.
            await page.locator('a[href$="/mobile"]').first().click();
            await page.locator('#zulors-mobile-app').waitFor();
        }

        for (const route of ['posts', 'people']) {
            await page.goto(`${origin}/explore/${route}`, { waitUntil: 'domcontentloaded' });
            if (mode.name === 'android-app') {
                await page.evaluate(() => document.documentElement.classList.add('zulors-android-app'));
            }
            const header = page.locator('.mobile-safe-overlay-top');
            await header.waitFor({ state: 'visible' });
            if (mode.name === 'android-app') {
                const overflowY = await page.locator('.mobile-app-content').evaluate(element => getComputedStyle(element).overflowY);
                assert.equal(overflowY, 'visible', 'Android WebView route wrapper must not capture sticky headers');
            }
            await page.waitForFunction(() => document.documentElement.scrollHeight > innerHeight + 1300);
            await page.waitForTimeout(600);
            // Wider viewports wrap fewer lines; keep room for repeated direction
            // changes rather than trying to scroll past the end of the list.
            const depth = await page.evaluate(() => Math.min(2400, document.documentElement.scrollHeight - innerHeight - 240));
            await page.mouse.move(220, 600);
            await wheel(page, depth);
            const down = await measure(header);
            await wheel(page, -8);
            const up = await measure(header);
            console.log(JSON.stringify({ mode: mode.name, route, down, up }));

            if (diagnose) {
                console.log(JSON.stringify(await header.evaluate(el => {
                    const ancestors = [];
                    for (let p = el.parentElement; p; p = p.parentElement) {
                        const style = getComputedStyle(p);
                        ancestors.push({ element: p.id || p.className || p.tagName, overflowX: style.overflowX,
                            overflowY: style.overflowY, scrollTop: p.scrollTop, height: p.clientHeight, total: p.scrollHeight });
                    }
                    return { ancestors };
                })));
                await page.addStyleTag({ content: '.mobile-app-content,.mobile-app-stage,.mobile-route-view{overflow-x:clip!important;overflow-y:visible!important}' });
                await wheel(page, 200);
                await wheel(page, -8);
                console.log(JSON.stringify({ cssProbe: await measure(header) }));
                continue;
            }

            assert.ok(down.hidden && down.bottom <= 1, 'Downward scroll hides the header');
            assert.ok(up.scrollY > 1000, 'Reveal must happen deep in the feed');
            assert.ok(!up.hidden && up.top === 0 && up.searchClickable, 'A small upward scroll reveals a usable header at the viewport top');

            for (let cycle = 1; cycle <= 4; cycle++) {
                await wheel(page, 40);
                const hidden = await measure(header);
                assert.ok(hidden.hidden && hidden.bottom <= 1, `Cycle ${cycle}: hidden ${JSON.stringify(hidden)}`);
                await wheel(page, -8);
                const shown = await measure(header);
                assert.ok(!shown.hidden && shown.top === 0 && shown.searchClickable, `Cycle ${cycle}: shown`);
                console.log(JSON.stringify({ mode: mode.name, route, cycle, ...shown }));
            }
            // Native scroll events also arrive in increments below the hide
            // threshold. A 1px reversal must reveal even after a 3px increment.
            for (const delta of [40, 3, -1]) {
                await page.evaluate(delta => window.scrollBy(0, delta), delta);
                await page.waitForTimeout(400);
            }
            const tinyUp = await measure(header);
            assert.ok(!tinyUp.hidden && tinyUp.top === 0 && tinyUp.searchClickable, `1px reversal: ${JSON.stringify(tinyUp)}`);
            if (mode.options.hasTouch) {
                await page.evaluate(() => window.scrollTo(0, 1500));
                await page.waitForTimeout(400);
                await swipe(page, 650, 480);
                const touchDown = await measure(header);
                assert.ok(touchDown.hidden && touchDown.scrollY > 1500 && touchDown.bottom <= 1, 'Native touch swipe hides header');
                await swipe(page, 480, 560);
                const touchUp = await measure(header);
                assert.ok(touchUp.scrollY < touchDown.scrollY && touchUp.scrollY > 1000 && !touchUp.hidden && touchUp.top === 0 && touchUp.searchClickable,
                    `Native upward swipe reveals header: ${JSON.stringify(touchUp)}`);
                console.log(JSON.stringify({ mode: mode.name, route, touchDown, touchUp }));
            }
            await header.locator('input').click();
            await page.waitForFunction(() => window.scrollY <= 2, null, { timeout: 6000 });
            assert.ok(await header.locator('input').evaluate(el => el === document.activeElement), 'Search can be focused');
            // Let the smooth-scroll operation release its per-target lock before
            // changing the emulated motion preference for the instant-scroll check.
            await page.waitForFunction(() => document.scrollingElement.scrollTop <= 1, null, { timeout: 6000 });
            await page.waitForTimeout(100);

            await page.emulateMedia({ reducedMotion: 'reduce' });
            const reducedMotionResult = await page.evaluate(() => {
                const target = document.scrollingElement;
                target.scrollTop = 1800;
                const before = target.scrollTop;
                document.querySelector('.mobile-safe-navbar .grid > div:nth-child(2) button').click();
                return { before, after: target.scrollTop };
            });
            assert.ok(reducedMotionResult.before > 1000 && reducedMotionResult.after <= 2, `Reduced motion resets instantly: ${JSON.stringify(reducedMotionResult)}`);

            await page.emulateMedia({ reducedMotion: 'no-preference' });
            await page.evaluate(() => {
                const target = document.scrollingElement;
                const nativeScrollTo = target.scrollTo.bind(target);
                window.__scrollToTopCallCount = 0;
                target.scrollTo = (options, ...args) => {
                    if(options && typeof options === 'object' && options.top === 0) {
                        window.__scrollToTopCallCount += 1;
                    }
                    return nativeScrollTo(options, ...args);
                };
                target.scrollTop = 1800;
                const searchButton = document.querySelector('.mobile-safe-navbar .grid > div:nth-child(2) button');
                searchButton.click();
                searchButton.click();
            });
            await page.waitForFunction(() => window.scrollY <= 2, null, { timeout: 6000 });
            assert.equal(await page.evaluate(() => window.__scrollToTopCallCount), 1, 'Repeated navbar clicks share one scroll animation');

            await page.evaluate(() => window.scrollTo(0, 1800));
            await page.waitForTimeout(350);
            const currentExplorePath = new URL(page.url()).pathname;
            await page.locator('.mobile-safe-navbar .grid > div:nth-child(2) button').click();
            await page.waitForFunction(() => window.scrollY <= 2, null, { timeout: 6000 });
            assert.equal(new URL(page.url()).pathname, currentExplorePath, 'Navbar Search keeps the current Explore tab');

            // Follow a real tab link after revealing the header deep in the feed.
            await header.locator(`a[href$="/explore/${route === 'posts' ? 'people' : 'posts'}"]`).click();
            await page.waitForURL(url => url.pathname.endsWith(route === 'posts' ? '/people' : '/posts'));
            await page.waitForTimeout(400);
            const nextHeader = await measure(page.locator('.mobile-safe-overlay-top'));
            assert.ok(!nextHeader.hidden && nextHeader.top === 0 && nextHeader.searchClickable, 'Header works after tab navigation');
            assert.deepEqual(errors, [], 'No uncaught app exceptions');
        }

        const homeLink = page.locator('.mobile-safe-navbar .grid > div:first-child a');
        await homeLink.click();
        await page.waitForURL(url => url.pathname === '/');
        const homeHeader = page.locator('.mobile-safe-header');
        await homeHeader.waitFor({ state: 'visible' });
        await page.evaluate(() => window.scrollTo(0, 700));
        await page.waitForTimeout(400);
        assert.ok(await page.evaluate(() => window.scrollY > 0), 'Home feed can be scrolled before returning to Explore');
        assert.ok(await homeHeader.evaluate(el => el.classList.contains('-translate-y-full')), 'Home header still hides on downward scroll');
        await page.evaluate(() => window.scrollBy(0, -8));
        await page.waitForTimeout(350);
        assert.ok(!await homeHeader.evaluate(el => el.classList.contains('-translate-y-full')), 'Home header still reveals on upward scroll');
        await page.evaluate(() => window.scrollTo(0, 700));
        await page.waitForTimeout(350);
        await page.locator('.mobile-safe-navbar .grid > div:nth-child(2) button').click();
        await page.waitForURL(url => url.pathname.endsWith('/explore/posts'));
        await page.waitForFunction(() => window.scrollY <= 2, null, { timeout: 6000 });

        await page.locator('.mobile-safe-navbar .grid > div:nth-child(5) a').click();
        await page.waitForURL(url => /^\/@[^/]+\/posts$/.test(url.pathname));
        const profileHeader = page.locator('.mobile-safe-page-start');
        const profileTabs = page.locator('.mobile-safe-sticky-top');
        await profileHeader.waitFor({ state: 'visible' });
        await profileTabs.waitFor({ state: 'visible' });
        await page.evaluate(() => window.scrollTo(0, 500));
        await page.waitForTimeout(400);
        const hiddenProfileHeader = await profileHeader.evaluate(el => el.classList.contains('-translate-y-full'));
        const visibleProfileTabs = await profileTabs.evaluate(el => !el.classList.contains('-translate-y-full'));
        assert.ok(hiddenProfileHeader, 'Profile top navigation auto-hides while scrolling down');
        assert.ok(visibleProfileTabs, 'Profile Posts/Media/Info tabs are not attached to the auto-hide header');

        await page.evaluate(() => window.scrollBy(0, -8));
        await page.waitForTimeout(400);
        assert.ok(!await profileHeader.evaluate(el => el.classList.contains('-translate-y-full')), 'Profile navigation reveals on upward scroll');

        for (const tab of ['media', 'info', 'posts']) {
            await profileTabs.locator(`a[href$="/${tab}"]`).click();
            await page.waitForURL(url => url.pathname.endsWith(`/${tab}`));
            await page.waitForTimeout(250);
            assert.ok(!await profileHeader.evaluate(el => el.classList.contains('-translate-y-full')), `${tab} tab change resets profile navigation`);
            assert.ok(await profileTabs.evaluate(el => !el.classList.contains('-translate-y-full')), `${tab} tab remains outside auto-hide state`);
        }

        assert.deepEqual(errors, [], 'No uncaught app exceptions after profile tab navigation');

        await page.locator('.mobile-safe-navbar .grid > div:nth-child(2) button').click();
        await page.waitForURL(url => url.pathname.endsWith('/explore/posts'));
        await page.locator('.mobile-safe-overlay-top a[href$="/explore/reels"]').click();
        await page.waitForURL(url => url.pathname.startsWith('/explore/reels'));
        const reelsViewport = page.locator('.mobile-reels-viewport');
        await reelsViewport.waitFor({ state: 'visible' });
        const reelsScroller = page.locator('.mobile-reels-viewport .snap-y');
        await page.waitForFunction(() => {
            const viewport = document.querySelector('.mobile-reels-viewport');
            const scroller = document.querySelector('.mobile-reels-viewport .snap-y');
            const loading = viewport?.querySelector('.colibri-primary-animation');
            return scroller || !loading;
        }, null, { timeout: 15000 });
        const reelsPath = new URL(page.url()).pathname;
        if (await reelsScroller.count()) {
            await page.waitForFunction(() => {
                const scroller = document.querySelector('.mobile-reels-viewport .snap-y');
                return scroller && scroller.scrollHeight > scroller.clientHeight;
            }, null, { timeout: 15000 });
            const reelsSnapType = await reelsScroller.evaluate(el => getComputedStyle(el).scrollSnapType);
            assert.ok(reelsSnapType.includes('y') && reelsSnapType.includes('mandatory'), `Reels retains vertical snap scrolling: ${reelsSnapType}`);
            await swipe(page, 650, 300);
            const reelsAfterSwipe = await reelsScroller.evaluate(el => el.scrollTop);
            assert.ok(reelsAfterSwipe > 100, `Touch swipe advances Reels: ${reelsAfterSwipe}`);
        } else {
            console.log(JSON.stringify({ mode: mode.name, reels: 'No reels were available in the local test account; snap gesture skipped.' }));
        }

        await page.locator('.mobile-safe-navbar .grid > div:nth-child(2) button').click();
        await page.waitForURL(url => url.pathname === reelsPath);
        if (await reelsScroller.count()) {
            await page.waitForFunction(() => {
                const scroller = document.querySelector('.mobile-reels-viewport .snap-y');
                return scroller && scroller.scrollTop <= 2;
            }, null, { timeout: 6000 });
        }
        assert.equal(new URL(page.url()).pathname, reelsPath, 'Navbar Search stays on the current Reels route');

        await context.close();
        if (diagnose) break;
    }
} finally {
    await browser.close();
}
