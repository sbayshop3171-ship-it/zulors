import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

const root = fileURLToPath(new URL('../../', import.meta.url));
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const scratch = await mkdtemp(path.join(tmpdir(), 'zulors-preview-test-'));
const fixtures = [
    { orientation: 'landscape', path: path.join(scratch, 'landscape.mp4'), source: 'testsrc2=size=640x360:rate=24' },
    { orientation: 'portrait', path: path.join(scratch, 'portrait.mp4'), source: 'testsrc2=size=360x640:rate=24' },
];
let server;
let browser;
try {
    for (const fixture of fixtures) {
        execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-f', 'lavfi', '-i', fixture.source,
            '-t', '4', '-c:v', 'libx264', '-threads', '1', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', fixture.path]);
    }
    server = await createServer({
        configFile: false, root, cacheDir: path.join(scratch, 'vite-cache'), plugins: [vue(), tailwindcss()],
        optimizeDeps: { noDiscovery: true, include: ['vue'] },
        resolve: { alias: {
            '@': path.join(root, 'resources/js/spa'),
            '@M': path.join(root, 'resources/js/spa/apps/mobile'),
            '@D': path.join(root, 'resources/js/spa/apps/desktop'),
        } },
        server: { host: '127.0.0.1', port: 0 },
    });
    await server.listen();
    const origin = `http://127.0.0.1:${server.httpServer.address().port}`;
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    for(const [name, viewport] of [['mobile', { width: 390, height: 844 }], ['desktop', { width: 1365, height: 900 }]]) {
        for (const fixture of fixtures) {
        const page = await browser.newPage({ viewport });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(`${origin}/tests/browser/post-video-preview.html${name === 'desktop' ? '?desktop' : ''}`);
        await page.locator('input').setInputFiles(fixture.path);
        await page.waitForFunction(() => document.querySelector('video')?.readyState >= 2);
        await page.evaluate(async () => {
            window.originalVideo = document.querySelector('video');
            window.originalVideo.muted = true;
            await window.originalVideo.play();
        });
        await page.waitForFunction(() => window.originalVideo.currentTime > 0.2);
        await page.evaluate(() => window.previewTest.complete());
        await page.waitForTimeout(200);
        const result = await page.evaluate(async () => {
            const video = document.querySelector('video');
            const frame = document.querySelector('.post-video-preview-frame');
            const stage = document.querySelector('.preview-stage');
            const canvas = document.createElement('canvas');
            canvas.width = 32; canvas.height = 18;
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, 32, 18);
            const colors = new Set(context.getImageData(0, 0, 32, 18).data);
            const data = { sameElement: video === window.originalVideo, duration: video.duration, time: video.currentTime,
                src: video.currentSrc, count: document.querySelectorAll('video').length, colors: colors.size,
                width: video.getBoundingClientRect().width, viewport: innerWidth,
                videoWidth: video.videoWidth, videoHeight: video.videoHeight,
                objectFit: getComputedStyle(video).objectFit,
                frameWidth: frame.getBoundingClientRect().width, frameHeight: frame.getBoundingClientRect().height,
                frameParentWidth: frame.parentElement.getBoundingClientRect().width,
                backdropIsDataUrl: frame.parentElement.querySelector('.post-video-preview-backdrop')?.src.startsWith('data:image/jpeg') || false,
                stageWidth: stage.getBoundingClientRect().width, stageHeight: stage.getBoundingClientRect().height,
                error: video.error?.message || null };
            video.currentTime = 2;
            return data;
        });
        assert.equal(result.sameElement, true, `${name}: completing upload must not remount the player`);
        assert.equal(result.count, 1);
        assert.ok(result.src.startsWith('blob:'));
        assert.ok(result.duration >= 3.9 && result.time > 0.2);
        assert.ok(result.colors > 20, 'decoded video frame must not be blank');
        assert.ok(result.width > 100 && result.width <= result.viewport, JSON.stringify(result));
        assert.equal(result.objectFit, 'contain', `${name}/${fixture.orientation}: show the whole video frame`);
        assert.ok(result.frameWidth <= result.stageWidth + 2, `${name}: preview stays within the available media width`);
        assert.ok(result.frameHeight <= result.stageHeight + 2, `${name}: preview stays within the available media height`);
        assert.ok(result.backdropIsDataUrl, `${name}/${fixture.orientation}: blurred frame fills space around the uncropped video`);
        assert.ok(result.frameParentWidth >= result.frameWidth, `${name}/${fixture.orientation}: video backdrop fills the preview row`);
        assert.ok(Math.abs(result.frameWidth / result.frameHeight - result.videoWidth / result.videoHeight) < 0.03,
            `${name}/${fixture.orientation}: preview frame keeps the video aspect ratio`);
        assert.equal(await page.getByRole('button', { name: /video speed/i }).count(), 0, `${name}: speed +/- controls stay hidden`);
        assert.equal(result.error, null);
        await page.waitForFunction(() => document.querySelector('video').currentTime >= 2 && ! document.querySelector('video').seeking);
        const screenshot = path.join(tmpdir(), `zulors-post-preview-${name}-${fixture.orientation}.png`);
        await page.screenshot({ path: screenshot, fullPage: true });
        await page.locator('video').evaluate(video => video.pause());
        await page.evaluate(() => window.previewTest.reopen());
        await page.waitForFunction(() => ! document.querySelector('video'));
        assert.equal(await page.locator('[role="status"]').count(), 1, 'reopened draft shows a placeholder, not a 0:00 player');
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({ viewport: `${name}/${fixture.orientation}`, ...result, src: 'blob:[redacted]', seek: 'passed', reopenedPlaceholder: true, screenshot }));
        await page.close();
        }
    }
}
finally {
    await browser?.close();
    await server?.close();
    await rm(scratch, { recursive: true, force: true });
}
