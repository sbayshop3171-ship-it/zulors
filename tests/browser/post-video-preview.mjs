import assert from 'node:assert/strict';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

const root = fileURLToPath(new URL('../../', import.meta.url));
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const scratch = await mkdtemp(path.join(tmpdir(), 'zulors-preview-test-'));
const fixtures = [
    { orientation: 'landscape', width: 640, height: 360, path: path.join(scratch, 'landscape.webm') },
    { orientation: 'portrait', width: 360, height: 640, path: path.join(scratch, 'portrait.webm') },
];
let server;
let browser;
try {
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const fixturePage = await browser.newPage();
    for (const fixture of fixtures) {
        const recording = await fixturePage.evaluate(async ({ width, height }) => {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');
            const stream = canvas.captureStream(24);
            const recorder = new MediaRecorder(stream, { mimeType: 'video/webm;codecs=vp8' });
            const chunks = [];
            let frame = 0;
            const draw = () => {
                context.fillStyle = `hsl(${(frame * 29) % 360} 70% 45%)`;
                context.fillRect(0, 0, width, height);
                context.fillStyle = 'white';
                context.fillRect(frame % Math.max(1, width - 80), height / 3, 80, height / 3);
                frame++;
            };
            draw();
            const interval = setInterval(draw, 100);
            return await new Promise(resolve => {
                recorder.ondataavailable = event => { if (event.data.size) chunks.push(event.data); };
                recorder.onstop = async () => {
                    clearInterval(interval);
                    stream.getTracks().forEach(track => track.stop());
                    const bytes = new Uint8Array(await new Blob(chunks, { type: recorder.mimeType }).arrayBuffer());
                    resolve({ mimeType: recorder.mimeType, bytes: Array.from(bytes) });
                };
                recorder.start(250);
                setTimeout(() => recorder.stop(), 4500);
            });
        }, fixture);
        assert.equal(recording.mimeType, 'video/webm;codecs=vp8');
        await writeFile(fixture.path, Buffer.from(recording.bytes));
    }
    await fixturePage.close();
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
            const pixels = context.getImageData(0, 0, 32, 18).data;
            const colors = new Set();
            for(let index = 0; index < pixels.length; index += 4) colors.add(`${pixels[index]},${pixels[index + 1]},${pixels[index + 2]}`);
            const data = { sameElement: video === window.originalVideo, duration: Number.isFinite(video.duration) ? video.duration : 'unbounded', time: video.currentTime,
                src: video.currentSrc, count: document.querySelectorAll('video').length, colors: colors.size,
                width: video.getBoundingClientRect().width, viewportWidth: innerWidth,
                videoWidth: video.videoWidth, videoHeight: video.videoHeight,
                objectFit: getComputedStyle(video).objectFit,
                frameWidth: frame.getBoundingClientRect().width, frameHeight: frame.getBoundingClientRect().height,
                frameParentWidth: frame.parentElement.getBoundingClientRect().width,
                backdropExists: Boolean(frame.parentElement.querySelector('.post-video-preview-backdrop')),
                stageWidth: stage.getBoundingClientRect().width, stageHeight: stage.getBoundingClientRect().height,
                error: video.error?.message || null };
            video.currentTime = 2;
            return data;
        });
        assert.equal(result.sameElement, true, `${name}: completing upload must not remount the player`);
        assert.equal(result.count, 1);
        assert.ok(result.src.startsWith('blob:'));
        assert.ok((result.duration === 'unbounded' || result.duration >= 3.9) && result.time > 0.2);
        assert.ok(result.colors > 1, 'decoded video frame must not be blank');
        assert.ok(result.width > 100 && result.width <= result.viewportWidth, JSON.stringify(result));
        assert.equal(result.objectFit, 'cover', `${name}/${fixture.orientation}: fill the preview without side gutters`);
        assert.ok(result.frameWidth <= result.stageWidth + 2, `${name}: preview stays within the available media width`);
        assert.ok(result.frameHeight <= result.stageHeight + 2, `${name}: preview stays within the available media height`);
        assert.ok(Math.abs(result.frameWidth - result.frameParentWidth) < 2,
            `${name}/${fixture.orientation}: video preview uses the full available width`);
        assert.equal(result.backdropExists, false, `${name}/${fixture.orientation}: no blurred side gutters`);
        assert.ok(result.frameHeight <= result.stageHeight + 2, `${name}/${fixture.orientation}: tall videos remain within the editor viewport`);
        assert.equal(await page.getByRole('button', { name: /video speed/i }).count(), 0, `${name}: speed +/- controls stay hidden`);
        assert.equal(result.error, null);
        await page.waitForFunction(() => document.querySelector('video').currentTime >= 2 && ! document.querySelector('video').seeking);
        await page.waitForTimeout(2300);
        const hiddenControls = await page.locator('.post-video-preview-frame [aria-live="polite"]').evaluate(element => getComputedStyle(element).opacity);
        assert.equal(hiddenControls, '0', `${name}: playback badges fade after playback begins`);
        await page.locator('.post-video-preview-frame video').dispatchEvent('pointermove');
        await page.waitForFunction(() => getComputedStyle(document.querySelector('.post-video-preview-frame [aria-live="polite"]')).opacity === '1');
        const screenshot = path.join(tmpdir(), `zulors-post-preview-${name}-${fixture.orientation}.png`);
        await page.screenshot({ path: screenshot, fullPage: true });
        await page.locator('video').evaluate(video => video.pause());
        await page.evaluate(() => window.previewTest.reopen());
        await page.waitForFunction(() => ! document.querySelector('video'));
        assert.equal(await page.locator('[role="status"]').count(), 1, 'reopened draft shows a placeholder, not a 0:00 player');
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({ ...result, viewport: `${name}/${fixture.orientation}`, src: 'blob:[redacted]', seek: 'passed', controlsAutoHide: true, reopenedPlaceholder: true, screenshot }));
        await page.close();
        }
    }
}
finally {
    await browser?.close();
    await server?.close();
    await rm(scratch, { recursive: true, force: true });
}
