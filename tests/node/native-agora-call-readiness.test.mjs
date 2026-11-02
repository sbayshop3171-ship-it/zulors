import test from 'node:test';
import assert from 'node:assert/strict';

import { createNativeAgoraAudioCallPeer } from '../../resources/js/spa/kernel/services/calls/native-agora-audio-call.js';

const makeNativeEvent = (detail) => {
    const event = new Event('zulors:native-call');

    Object.defineProperty(event, 'detail', {
        value: detail,
    });

    return event;
};

const waitUntil = async (predicate) => {
    for(let attempt = 0; attempt < 20 && ! predicate(); attempt += 1) {
        await new Promise((resolve) => setImmediate(resolve));
    }

    assert.equal(predicate(), true, 'Expected native call bridge to start.');
};

const setupNativeWindow = (overrides = {}) => {
    const nativeWindow = new EventTarget();
    let started = false;

    Object.assign(nativeWindow, {
        setTimeout: globalThis.setTimeout,
        clearTimeout: globalThis.clearTimeout,
        ZulorsCallAudio: {
            nativeRtcSupported: () => true,
            startNativeAgoraCall: () => {
                started = true;

                return true;
            },
            ...overrides,
        },
    });
    globalThis.window = nativeWindow;

    return {
        window: nativeWindow,
        hasStarted: () => started,
    };
};

const makePeer = (callbacks = {}, options = {}) => {
    return createNativeAgoraAudioCallPeer(callbacks, {
        mediaSession: {
            provider: 'agora',
            app_id: 'app-id',
            channel: 'test-channel',
        },
        ...options,
    });
};

test('native Agora readiness waits for joined channel and published local audio', async () => {
    const native = setupNativeWindow();
    const peer = makePeer();
    const readiness = peer.ensurePeerConnection();

    await waitUntil(native.hasStarted);
    native.window.dispatchEvent(makeNativeEvent({ type: 'state', state: 'connected' }));

    assert.equal(await Promise.race([
        readiness.then(() => 'ready'),
        Promise.resolve('pending'),
    ]), 'pending');

    native.window.dispatchEvent(makeNativeEvent({
        type: 'media-ready',
        channel_join_duration_ms: 320,
        local_audio_publish_duration_ms: 710,
    }));

    assert.deepEqual(await readiness, {
        channel_join_duration_ms: 320,
        local_audio_publish_duration_ms: 710,
    });

    peer.close();
    delete globalThis.window;
});

test('native Agora join or publish failure rejects readiness instead of signaling success', async () => {
    const native = setupNativeWindow();
    const peer = makePeer();
    const readiness = peer.ensurePeerConnection();

    await waitUntil(native.hasStarted);
    native.window.dispatchEvent(makeNativeEvent({
        type: 'error',
        message: 'Agora could not publish microphone audio.',
    }));

    await assert.rejects(readiness, /could not publish microphone audio/);
    peer.close();
    delete globalThis.window;
});

test('native Agora bridge start failure rejects readiness immediately', async () => {
    const native = setupNativeWindow({
        startNativeAgoraCall: () => false,
    });
    const peer = makePeer();

    await assert.rejects(peer.ensurePeerConnection(), /Unable to start native audio engine/);
    peer.close();
    delete globalThis.window;
});
