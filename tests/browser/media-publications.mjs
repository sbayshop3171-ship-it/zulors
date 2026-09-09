import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { tmpdir } from 'node:os';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

const root = fileURLToPath(new URL('../../', import.meta.url));
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII=', 'base64');
const scratch = await mkdtemp(path.join(tmpdir(), 'zulors-publications-'));
const videoPath = path.join(scratch, 'video.mp4');
execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc2=size=320x180:rate=12', '-t', '2', '-c:v', 'libx264', '-threads', '1', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', videoPath]);
const video = await readFile(videoPath);
const server = await createServer({ configFile: false, root, plugins: [vue(), tailwindcss()],
    optimizeDeps: { noDiscovery: true, include: ['vue', 'pinia', 'vue-router', 'vue-i18n', 'axios', 'howler', 'mitt', 'autolinker', 'hotkeys-js', 'hls.js', 'recordrtc', '@giphy/js-fetch-api', '@headlessui/vue'] },
    resolve: { alias: { '@': path.join(root, 'resources/js/spa'), '@M': path.join(root, 'resources/js/spa/apps/mobile'), '@D': path.join(root, 'resources/js/spa/apps/desktop') } },
    server: { host: '127.0.0.1', port: 5178, strictPort: false, hmr: false },
});
let browser;
try {
    await server.listen();
    const origin = `http://127.0.0.1:${server.httpServer.address().port}`;
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    for (const platform of (process.env.PUBLICATION_WORKER_ONLY ? [] : process.env.PUBLICATION_PLATFORM ? [process.env.PUBLICATION_PLATFORM] : ['mobile', 'desktop'])) for (const kind of ['post', 'story', 'chat']) for (const media of ['image', 'video']) {
        const bytes = media === 'image' ? png : video;
        const mime = media === 'image' ? 'image/png' : 'video/mp4';
        const context = await browser.newContext({ viewport: platform === 'mobile' ? { width: 390, height: 844 } : { width: 1365, height: 900 } });
        const requests = [];
        await context.route('**/api/**', route => {
            const request = route.request(); requests.push({ url: request.url(), method: request.method() });
            const data = request.url().endsWith('/capabilities') ? { enabled: true, user_id: 1, kinds: ['post', 'story', 'chat'], privacy_options: ['all'], upload_concurrency: 2 }
                : request.url().includes('/media-publications') ? [] : request.url().includes('/draft') ? { draft: null } : {};
            return route.fulfill({ json: { data } });
        });
        const page = await context.newPage();
        page.setDefaultTimeout(60000);
        const errors = [];
        page.on('pageerror', error => { errors.push(error.message); console.error(error.message); });
        await page.goto(`${origin}/tests/browser/media-publications.html?platform=${platform}&kind=${kind}`, { waitUntil: 'domcontentloaded', timeout: 90000 });
        await page.waitForFunction(() => window.publicationTest);
        if (kind === 'post') await page.waitForFunction(() => window.publicationTest.store.draftPost.content !== undefined);
        if (kind === 'story') await page.evaluate(({ bytes, mime }) => window.publicationTest.selectStory(bytes, mime), { bytes: [...bytes], mime });
        else await page.locator(`input[type=file][accept^="${media}/"]`).first().setInputFiles({ name: media === 'image' ? 'photo.png' : 'video.mp4', mimeType: mime, buffer: bytes });
        const preview = media === 'image' ? 'img' : 'video';
        await page.waitForFunction(preview => [...document.querySelectorAll(preview)].some(element => element.src.startsWith('blob:')), preview);
        if (kind !== 'chat') {
            const audience = page.getByRole('group', { name: 'Audience', exact: true });
            await audience.waitFor();
            assert.equal((await audience.textContent()).trim(), 'Everyone');
            assert.equal(await audience.getAttribute('data-publication-audience'), 'all');
            assert.equal(await page.getByText('story.story_privacy_alert.desc', { exact: true }).count(), 0, 'canary Story must not claim subscriber-only privacy');
        }
        if (media === 'video') {
            await page.waitForFunction(() => document.querySelector('video')?.readyState >= 2);
            await page.locator('video').first().evaluate(async element => { element.muted = true; await element.play(); });
            await page.waitForFunction(() => document.querySelector('video')?.currentTime > 0.15);
            const pixels = await page.locator('video').first().evaluate(element => {
                const canvas = document.createElement('canvas'); canvas.width = 32; canvas.height = 18;
                const ctx = canvas.getContext('2d'); ctx.drawImage(element, 0, 0, 32, 18);
                return new Set(ctx.getImageData(0, 0, 32, 18).data).size;
            });
            assert.ok(pixels > 20, 'selected video renders a decoded frame');
        }
        assert.equal(requests.some(request => request.method !== 'GET'), false, `${platform}/${kind}: selecting media must be local`);
        await page.locator('textarea').first().fill('Durable caption');
        await page.evaluate(() => {
            const db = window.publicationTest.db;
            window.restoreEnqueue = db.enqueue.bind(db);
            db.enqueue = async () => { throw new DOMException('Full', 'QuotaExceededError'); };
        });
        await page.evaluate(() => window.publicationTest.submit());
        assert.equal(await page.evaluate(() => window.publicationTest.rows.value.length), 0);
        assert.equal(await page.locator('textarea').first().inputValue(), 'Durable caption');
        assert.ok(await page.locator(`${preview}[src^="blob:"]`).count(), 'quota error retains selected media');
        await page.evaluate(() => { window.publicationTest.db.enqueue = window.restoreEnqueue; });
        await page.evaluate(() => window.publicationTest.submit());
        const result = await page.evaluate(async () => {
            const rows = await window.publicationTest.db.list('1');
            const row = rows[0];
            const file = await window.publicationTest.db.file(`${row.key}:${row.descriptor.items[0].client_uid}`);
            return { count: rows.length, kind: row.descriptor.kind, content: row.descriptor.content, privacy: row.descriptor.privacy, selected_user_ids: row.descriptor.selected_user_ids, status: row.status, size: file?.size };
        });
        assert.deepEqual(result, { count: 1, kind, content: 'Durable caption', privacy: 'all', selected_user_ids: [], status: 'queued', size: bytes.length });
        assert.equal(requests.some(request => request.method !== 'GET'), false, 'Publish saves locally before the worker sends HTTP');
        await page.evaluate(() => window.publicationTest.close());
        await page.getByRole('status').filter({ hasText: 'Queued' }).waitFor();
        await page.screenshot({ path: path.join(tmpdir(), `zulors-publication-${platform}-${kind}.png`), fullPage: true });
        const bounds = await page.locator('.publication-row').evaluate(element => ({ width: element.getBoundingClientRect().width, viewport: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth }));
        assert.ok(bounds.width <= bounds.viewport); assert.equal(bounds.overflow, false);
        await page.goto(`${origin}/tests/browser/media-publications.html?platform=${platform}&kind=${kind}&outbox`);
        await page.waitForFunction(() => window.publicationTest?.rows.value.length === 1);
        assert.equal(await page.evaluate(() => window.publicationTest.rows.value[0].descriptor.content), 'Durable caption');
        assert.deepEqual(errors, [], `${platform}/${kind}: browser errors`);
        assert.equal(await page.evaluate(() => window.fixtureError || null), null);
        console.log(`${platform}/${kind}/${media}: local preview, quota retention, durable publish, reload and pending row passed`);
        await context.close();
    }
    const context = await browser.newContext();
    await context.route('**/api/**', route => route.fulfill({ json: { data: route.request().url().endsWith('/capabilities') ? { enabled: true, user_id: 1, kinds: ['post'], upload_concurrency: 2 } : [] } }));
    const pages = await Promise.all([context.newPage(), context.newPage()]);
    await Promise.all(pages.map(page => page.goto(`${origin}/tests/browser/media-publications.html?outbox`)));
    await Promise.all(pages.map(page => page.waitForFunction(() => window.publicationTest)));
    const leases = await Promise.all(pages.map((page, i) => page.evaluate(i => window.publicationTest.db.lease('test-worker', `tab-${i}`), i)));
    assert.equal(leases.filter(Boolean).length, 1, 'IndexedDB lease grants ownership to exactly one tab');
    const first = pages[leases.findIndex(Boolean)];
    await first.evaluate(() => window.publicationTest.db.enqueue({ key: '1:blob-test', account: '1', descriptor: { items: [{ client_uid: 'file' }] } }, [{ key: '1:blob-test:file', blob: new Blob(['durable']) }]));
    assert.equal(await pages[1 - leases.findIndex(Boolean)].evaluate(async () => (await window.publicationTest.db.file('1:blob-test:file')).text()), 'durable');
    console.log('Cross-tab IndexedDB lease and shared Blob recovery passed');
    await first.evaluate(() => window.publicationTest.db.update('1:blob-test', row => ({ ...row, status: 'cancelled', discard_requested: true })));
    let serverPublication;
    let creates = 0;
    let puts = 0;
    let cancelConflict = false;
    const cancellationRequests = [];
    await context.addCookies([{ name: 'storage-cookie', value: 'must-not-send', domain: 'r2.test', path: '/' }, { name: 'XSRF-TOKEN', value: 'csrf-test', url: origin }, { name: 'session', value: 'authenticated', url: origin }]);
    await context.route('https://r2.test/**', async route => {
        if (route.request().method() === 'PUT') {
            puts++;
            const headers = await route.request().allHeaders();
            assert.equal(headers.cookie, undefined); assert.equal(headers.authorization, undefined); assert.equal(headers['x-xsrf-token'], undefined);
            assert.equal(route.request().postDataBuffer().length, png.length);
        }
        await route.fulfill({ status: route.request().method() === 'OPTIONS' ? 204 : 200, body: '', headers: { 'Access-Control-Allow-Origin': origin, 'Access-Control-Allow-Methods': 'PUT, OPTIONS', 'Access-Control-Allow-Headers': 'Content-Type', 'Access-Control-Expose-Headers': 'ETag', ETag: 'raw-etag' } });
    });
    await context.route('**/api/**', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (cancelConflict && url.pathname === '/api/media-publications/cancel-race') {
            cancellationRequests.push(request.method());
            if (request.method() === 'DELETE') return route.fulfill({ status: 409, json: { message: 'Already published' } });
        }
        let data;
        if (url.pathname.endsWith('/capabilities')) data = { enabled: true, user_id: 1, kinds: ['post'], upload_concurrency: 2 };
        else if (request.method() === 'POST' && url.pathname === '/api/media-publications') {
            creates++;
            assert.ok((await request.allHeaders()).cookie.includes('session=authenticated'));
            const descriptor = request.postDataJSON();
            serverPublication = { ...descriptor, id: 'server-publication', status: 'uploading', items: descriptor.items.map(item => ({ ...item, id: 'server-item', status: 'pending' })) };
            data = serverPublication;
        } else if (url.pathname.endsWith('/resume')) data = { status: 'uploading', generation: 7, completed_parts: [], upload: { upload_type: 'raw', upload_url: 'https://r2.test/photo', upload_method: 'PUT', upload_headers: { 'Content-Type': 'image/png', Authorization: 'api-secret', 'X-XSRF-TOKEN': 'api-secret' } } };
        else if (url.pathname.endsWith('/complete')) {
            assert.deepEqual(request.postDataJSON(), { generation: 7, parts: [] });
            serverPublication = { ...serverPublication, status: 'processing', items: serverPublication.items.map(item => ({ ...item, status: 'processing' })) };
            data = serverPublication;
        } else data = url.pathname === '/api/media-publications' ? (serverPublication ? [serverPublication] : []) : serverPublication;
        await route.fulfill({ json: { data } });
    });
    await first.evaluate(async bytes => {
        const file = new File([new Uint8Array(bytes)], 'photo.png', { type: 'image/png' });
        await window.publicationTest.manager.enqueue({ kind: 'post', content: 'XHR lifecycle' }, [{ file, client_uid: crypto.randomUUID(), name: file.name, size: file.size, mime: file.type, type: 'image' }]);
    }, [...png]);
    await Promise.all(pages.map(page => page.evaluate(() => window.publicationTest.manager.tick())));
    assert.equal(creates, 1, 'concurrent managers create exactly one publication');
    assert.equal(puts, 1, 'concurrent managers upload exactly once');
    await first.getByRole('status').filter({ hasText: 'Processing' }).waitFor();
    serverPublication = { ...serverPublication, status: 'published', result: { id: 42, type: 'post', url: '/posts/42' }, items: serverPublication.items.map(item => ({ ...item, status: 'processed' })) };
    await first.evaluate(() => window.publicationTest.manager.tick());
    const final = await first.evaluate(async () => {
        const row = window.publicationTest.rows.value.find(row => row.descriptor.content === 'XHR lifecycle');
        return { status: row.status, file: Boolean(await window.publicationTest.db.file(`${row.key}:${row.descriptor.items[0].client_uid}`)) };
    });
    assert.deepEqual(final, { status: 'published', file: false });
    console.log('Real XHR: authenticated API, credential-free R2, single worker, processing gate and terminal Blob cleanup passed');
    await first.evaluate(async bytes => {
        const file = new File([new Uint8Array(bytes)], 'photo.png', { type: 'image/png' });
        const row = await window.publicationTest.manager.enqueue({ kind: 'post', content: 'Retry and cancel' }, [{ file, client_uid: crypto.randomUUID(), name: file.name, size: file.size, mime: file.type, type: 'image' }]);
        await window.publicationTest.db.update(row.key, value => ({ ...value, status: 'failed', error: 'Connection interrupted' }));
        await window.publicationTest.manager.refresh();
    }, [...png]);
    await first.getByRole('button', { name: 'Retry upload' }).click();
    await first.getByRole('status').filter({ hasText: 'Queued' }).waitFor();
    await first.getByRole('button', { name: 'Cancel upload' }).click();
    await first.evaluate(() => window.publicationTest.manager.tick());
    assert.equal(await first.getByRole('button', { name: 'Cancel upload' }).count(), 0);
    assert.equal(creates, 1, 'cancelling a local-only entry does not create a publication');
    console.log('Pending-row retry and cancel buttons passed');
    const multi = await first.evaluate(async bytes => {
        const file = new File([new Uint8Array(bytes)], 'photo.png', { type: 'image/png' });
        return window.publicationTest.manager.enqueue({ kind: 'post', content: 'Two photos after reload' }, [1, 2].map(index => ({ file, client_uid: crypto.randomUUID(), name: `photo-${index}.png`, size: file.size, mime: file.type, type: 'image' })));
    }, [...png]);
    serverPublication = { ...multi.descriptor, id: 'multi-publication', status: 'processing', items: multi.descriptor.items.map((item, index) => ({ ...item, id: `multi-item-${index}`, status: index === 0 ? 'processed' : 'pending' })) };
    await first.evaluate(({ row, publication }) => window.publicationTest.manager.savePublication(row, publication), { row: multi, publication: serverPublication });
    await first.reload();
    await first.waitForFunction(() => window.publicationTest);
    const beforeResume = puts;
    await first.evaluate(() => window.publicationTest.manager.tick());
    assert.equal(puts, beforeResume + 1, 'reloaded processing publication uploads only its remaining photo');
    assert.equal(creates, 1, 'resumption does not create a new publication');
    console.log('Multi-image regression: processing publication resumes remaining attachment after reload');
    serverPublication = { ...serverPublication, status: 'cancelled', error: 'Upload expired. Start a new publication.' };
    await first.evaluate(() => window.publicationTest.manager.reconcile());
    const expired = await first.evaluate(async key => {
        const row = await window.publicationTest.db.get(key);
        return { status: row.status, originals: await Promise.all(row.descriptor.items.map(async item => Boolean(await window.publicationTest.db.file(`${row.key}:${item.client_uid}`)))) };
    }, multi.key);
    assert.deepEqual(expired, { status: 'expired', originals: [true, true] });
    await first.getByRole('status').filter({ hasText: 'Original media saved' }).waitFor();
    await first.getByRole('button', { name: 'Retry upload' }).click();
    await first.getByRole('status').filter({ hasText: 'Queued' }).waitFor();
    const restarted = await first.evaluate(async () => {
        const row = window.publicationTest.rows.value.find(row => row.status === 'queued');
        return { client_uid: row.client_uid, descriptor: row.descriptor, sizes: await Promise.all(row.descriptor.items.map(async item => (await window.publicationTest.db.file(`${row.key}:${item.client_uid}`))?.size)) };
    });
    assert.notEqual(restarted.client_uid, multi.client_uid);
    assert.equal(restarted.descriptor.content, 'Two photos after reload');
    assert.deepEqual(restarted.sizes, [png.length, png.length]);
    assert.ok(restarted.descriptor.items.every(item => !multi.descriptor.items.some(old => old.client_uid === item.client_uid)));
    await first.getByRole('button', { name: 'Cancel upload' }).click();
    await first.evaluate(() => window.publicationTest.manager.tick());
    const retained = await first.evaluate(async descriptor => {
        return Promise.all(descriptor.items.map(async item => Boolean(await window.publicationTest.db.file(`1:${descriptor.client_uid}:${item.client_uid}`))));
    }, restarted.descriptor);
    assert.deepEqual(retained, [false, false], 'only explicit discard removes restarted originals');
    console.log('Expiry regression: originals retained, Retry creates a new durable identity, explicit discard releases files');
    const racing = await first.evaluate(async bytes => {
        const file = new File([new Uint8Array(bytes)], 'photo.png', { type: 'image/png' });
        return window.publicationTest.manager.enqueue({ kind: 'post', content: 'Cancel versus publish' }, [{ file, client_uid: crypto.randomUUID(), name: file.name, size: file.size, mime: file.type, type: 'image' }]);
    }, [...png]);
    serverPublication = { ...racing.descriptor, id: 'cancel-race', status: 'publishing', items: racing.descriptor.items.map(item => ({ ...item, id: 'race-item', status: 'processed' })) };
    await first.evaluate(({ row, publication }) => window.publicationTest.manager.savePublication(row, publication).then(() => window.publicationTest.manager.refresh()), { row: racing, publication: serverPublication });
    await first.getByRole('button', { name: 'Cancel upload' }).click();
    cancelConflict = true;
    serverPublication = { ...serverPublication, status: 'published', result: { id: 43, type: 'post', url: '/posts/43' } };
    await first.evaluate(() => window.publicationTest.manager.tick());
    const raceResult = await first.evaluate(async key => {
        const row = await window.publicationTest.db.get(key);
        return { status: row.status, cancelling: row.cancel_requested, progress: row.progress, result: row.publication.result,
            hasFile: Boolean(await window.publicationTest.db.file(`${key}:${row.descriptor.items[0].client_uid}`)) };
    }, racing.key);
    assert.deepEqual(cancellationRequests, ['DELETE', 'GET']);
    assert.deepEqual(raceResult, { status: 'published', cancelling: false, progress: 100, result: { id: 43, type: 'post', url: '/posts/43' }, hasFile: false });
    assert.equal(await first.getByRole('button', { name: 'Cancel upload' }).count(), 0);
    await first.evaluate(() => window.publicationTest.manager.tick());
    assert.deepEqual(cancellationRequests, ['DELETE', 'GET'], 'published content is never automatically cancelled or deleted again');
    console.log('Cancel/publish race: DELETE 409 reconciles published, clears cancellation and releases local Blob without another DELETE');
    await context.close();
    for (const kind of ['post', 'chat']) {
        const nativeContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
        const serverRow = { id: `native-server-${kind}`, client_uid: `server-client-${kind}`, kind, content: `Native ${kind}`, chat_id: kind === 'chat' ? '7ec727ba-ed57-4b9f-a2ce-7822a86597cd' : null,
            status: 'uploading', items: [{ id: 'native-item', client_uid: 'native-file', type: 'image', size: png.length, status: 'pending' }] };
        const requests = [];
        await nativeContext.route('**/api/**', route => {
            const request = route.request();
            const pathname = new URL(request.url()).pathname;
            requests.push([request.method(), pathname]);
            if (request.method() !== 'GET' || !['/api/media-publications', '/api/media-publications/capabilities'].includes(pathname)) return route.fulfill({ status: 500, json: { message: 'Browser must not operate native publications' } });
            return route.fulfill({ json: { data: pathname.endsWith('/capabilities') ? { enabled: true, user_id: 1, kinds: ['post', 'story', 'chat'], privacy_options: ['all'], upload_concurrency: 2 } : [serverRow] } });
        });
        await nativeContext.addInitScript(row => {
            window.nativeCalls = [];
            window.nativeRow = { ...row, client_uid: `android-client-${row.kind}`, native_id: `android-job-${row.kind}`, user_id: '1', progress: 67 };
            window.ZulorsUploadBridge = { postMessage(raw) {
                const { id, method, payload } = JSON.parse(raw);
                window.nativeCalls.push({ method, payload });
                if (method === 'retry') window.nativeRow.status = 'uploading';
                if (method === 'cancel') window.nativeRow.status = 'cancelled';
                const result = method === 'capabilities' ? { enabled: true } : method === 'list' ? [window.nativeRow, { ...window.nativeRow }] : {};
                queueMicrotask(() => this.onmessage({ data: JSON.stringify({ id, result }) }));
            } };
        }, serverRow);
        const page = await nativeContext.newPage();
        await page.goto(`${origin}/tests/browser/media-publications.html?outbox&kind=${kind}`);
        await page.waitForFunction(() => window.publicationTest?.rows.value.length === 1);
        await page.getByRole('status').filter({ hasText: 'Uploading 67%' }).waitFor();
        assert.equal(await page.locator('.publication-row').count(), 1, 'native and server mirror render one row');
        assert.equal(await page.getByRole('progressbar').getAttribute('value'), '67', 'native progress wins over server mirror');
        const mirror = await page.evaluate(async () => {
            const { manager, db } = window.publicationTest;
            const row = (await db.list('1'))[0];
            await manager.tick();
            await manager.process(row, manager.epoch);
            return { remote: row.remote_only, owner: row.native_owner.native_id, native: manager.rows[0].native };
        });
        assert.deepEqual(mirror, { remote: true, owner: `android-job-${kind}`, native: true });
        await page.evaluate(async () => { window.nativeRow.status = 'failed'; await window.publicationTest.manager.refresh(); });
        await page.getByRole('button', { name: 'Retry upload' }).click();
        await page.getByRole('status').filter({ hasText: 'Uploading 67%' }).waitFor();
        await page.getByRole('button', { name: 'Cancel upload' }).click();
        await page.waitForFunction(() => !document.querySelector('.publication-row'));
        const actions = await page.evaluate(async () => ({
            calls: window.nativeCalls.filter(call => ['retry', 'cancel'].includes(call.method)).map(call => [call.method, call.payload.native_id]),
            cancelledByBrowser: Boolean((await window.publicationTest.db.list('1'))[0].cancel_requested),
        }));
        assert.deepEqual(actions, { calls: [['retry', `android-job-${kind}`], ['cancel', `android-job-${kind}`]], cancelledByBrowser: false });
        assert.ok(requests.every(([method, pathname]) => method === 'GET' && ['/api/media-publications', '/api/media-publications/capabilities'].includes(pathname)), 'browser never calls native publication transfer, retry, or cancel APIs');
        console.log(`Native ${kind === 'chat' ? 'Chat' : 'Home'}: preferred row deduplicated by server ID, native progress and actions, no browser transfer`);
        await nativeContext.close();
    }
} finally { await browser?.close(); await server.close(); await rm(scratch, { recursive: true, force: true }); }
