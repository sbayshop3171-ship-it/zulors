import test from 'node:test';
import assert from 'node:assert/strict';

import { createAdaptiveVideoPlaybackController } from '../../resources/js/spa/kernel/services/media/adaptive-video/playback.js';

class FakeHls {
    static Events = { ERROR: 'error', LEVEL_SWITCHED: 'level-switched', MEDIA_ATTACHED: 'media-attached' };
    static ErrorTypes = { NETWORK_ERROR: 'networkError', MEDIA_ERROR: 'mediaError' };
    static instances = [];
    static isSupported() { return true; }

    constructor(options) {
        this.options = options;
        this.handlers = new Map();
        this.calls = [];
        FakeHls.instances.push(this);
    }

    on(name, handler) { this.handlers.set(name, handler); }
    trigger(name, data) { this.handlers.get(name)?.(name, data); }
    attachMedia(video) { this.video = video; this.trigger(FakeHls.Events.MEDIA_ATTACHED); }
    loadSource(url) { this.calls.push(['loadSource', url]); }
    startLoad(position) { this.calls.push(['startLoad', position]); }
    stopLoad() { this.calls.push(['stopLoad']); }
    recoverMediaError() { this.calls.push(['recoverMediaError']); }
    destroy() { this.calls.push(['destroy']); }
}

function createVideoElement() {
    const listeners = new Map();
    return {
        currentTime: 0,
        preload: '',
        src: '',
        canPlayType: () => '',
        pause() {},
        load() {},
        removeAttribute(name) { if(name === 'src') this.src = ''; },
        addEventListener(name, handler, options = {}) {
            const items = listeners.get(name) || [];
            items.push({ handler, once: Boolean(options.once) });
            listeners.set(name, items);
        },
        removeEventListener(name, handler) {
            listeners.set(name, (listeners.get(name) || []).filter((item) => item.handler !== handler));
        },
        dispatch(name) {
            const items = listeners.get(name) || [];
            for(const item of [...items]) {
                item.handler();
                if(item.once) this.removeEventListener(name, item.handler);
            }
        },
        getAttribute() { return null; },
        setAttribute() {},
    };
}

const hlsSource = { url: 'https://media.example.test/master.m3u8', type: 'application/vnd.apple.mpegurl', transport: 'hls' };
const mp4Source = { url: 'https://media.example.test/source.mp4', type: 'video/mp4', transport: 'direct' };

test('adaptive playback starts on a low rendition with bounded buffers for slow networks', () => {
    FakeHls.instances = [];
    const video = createVideoElement();
    const controller = createAdaptiveVideoPlaybackController(video, {
        HlsClass: FakeHls,
        getNetworkProfile: () => ({ profile: 'slow', reelsBackBufferLength: 6, reelsMaxBufferLength: 14, reelsMaxMaxBufferLength: 20 }),
    });

    controller.attach(hlsSource, mp4Source);

    assert.equal(FakeHls.instances[0].options.autoStartLoad, false);
    assert.equal(FakeHls.instances[0].options.startLevel, 0);
    assert.equal(FakeHls.instances[0].options.maxBufferLength, 14);
    assert.ok(FakeHls.instances[0].calls.some(([name]) => name === 'startLoad'));
    controller.destroy();
});

test('fatal HLS network errors retry once then fall back to the canonical MP4', () => {
    FakeHls.instances = [];
    const video = createVideoElement();
    let fallbackCount = 0;
    const controller = createAdaptiveVideoPlaybackController(video, {
        HlsClass: FakeHls,
        getNetworkProfile: () => ({ profile: 'normal' }),
        onFallback: () => fallbackCount++,
    });

    controller.attach(hlsSource, mp4Source);
    const hls = FakeHls.instances[0];
    hls.trigger(FakeHls.Events.ERROR, { fatal: true, type: FakeHls.ErrorTypes.NETWORK_ERROR });
    assert.equal(hls.calls.filter(([name]) => name === 'startLoad').length, 2);

    hls.trigger(FakeHls.Events.ERROR, { fatal: true, type: FakeHls.ErrorTypes.NETWORK_ERROR });

    assert.equal(video.src, mp4Source.url);
    assert.equal(fallbackCount, 1);
    controller.destroy();
});

test('suspended off-screen playback releases HLS and restores its position on return', () => {
    FakeHls.instances = [];
    const video = createVideoElement();
    const controller = createAdaptiveVideoPlaybackController(video, {
        HlsClass: FakeHls,
        getNetworkProfile: () => ({ profile: 'normal' }),
    });

    controller.attach(hlsSource, mp4Source);
    const firstHls = FakeHls.instances[0];
    controller.suspend(12.5);
    assert.equal(video.src, '');
    assert.ok(firstHls.calls.some(([name]) => name === 'destroy'));

    controller.resume();
    assert.equal(FakeHls.instances.length, 2);
    video.dispatch('loadedmetadata');
    assert.equal(video.currentTime, 12.5);
    assert.ok(FakeHls.instances[1].calls.some(([name]) => name === 'startLoad'));
    controller.destroy();
});
