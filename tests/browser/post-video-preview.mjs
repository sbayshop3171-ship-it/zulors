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
const fixture = path.join(scratch, 'video.mp4');
let server;
let browser;
try {
    execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc2=size=640x360:rate=24',
        '-t', '4', '-c:v', 'libx264', '-threads', '1', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', fixture]);
    server = await createServer({
        configFile: false, root, plugins: [vue(), tailwindcss()],
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
        const page = await browser.newPage({ viewport });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(`${origin}/tests/browser/post-video-preview.html${name === 'desktop' ? '?desktop' : ''}`);
        await page.locator('input').setInputFiles(fixture);
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
            const canvas = document.createElement('canvas');
            canvas.width = 32; canvas.height = 18;
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, 32, 18);
            const colors = new Set(context.getImageData(0, 0, 32, 18).data);
            const data = { sameElement: video === window.originalVideo, duration: video.duration, time: video.currentTime,
                src: video.currentSrc, count: document.querySelectorAll('video').length, colors: colors.size,
                width: video.getBoundingClientRect().width, viewport: innerWidth, error: video.error?.message || null };
            video.currentTime = 2;
            return data;
        });
        assert.equal(result.sameElement, true, `${name}: completing upload must not remount the player`);
        assert.equal(result.count, 1);
        assert.ok(result.src.startsWith('blob:'));
        assert.ok(result.duration >= 3.9 && result.time > 0.2);
        assert.ok(result.colors > 20, 'decoded video frame must not be blank');
        assert.ok(result.width > 100 && result.width <= result.viewport);
        assert.equal(result.error, null);
        await page.waitForFunction(() => document.querySelector('video').currentTime >= 2 && ! document.querySelector('video').seeking);
        const screenshot = path.join(tmpdir(), `zulors-post-preview-${name}.png`);
        await page.screenshot({ path: screenshot, fullPage: true });
        await page.locator('video').evaluate(video => video.pause());
        await page.evaluate(() => window.previewTest.reopen());
        await page.waitForFunction(() => ! document.querySelector('video'));
        assert.equal(await page.locator('[role="status"]').count(), 1, 'reopened draft shows a placeholder, not a 0:00 player');
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({ viewport: name, ...result, src: 'blob:[redacted]', seek: 'passed', reopenedPlaceholder: true, screenshot }));
        await page.close();
    }
}
finally {
    await browser?.close();
    await server?.close();
    await rm(scratch, { recursive: true, force: true });
}
