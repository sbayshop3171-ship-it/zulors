import test from 'node:test';
import assert from 'node:assert/strict';

import {
    createRemoteAudioHealthState,
    evaluateNativeRemoteAudioHealth,
    evaluateWebRemoteAudioHealth,
} from '../../resources/js/spa/kernel/services/calls/remote-audio-health.js';

test('web remote audio becomes live only after real inbound progress', () => {
    const initial = createRemoteAudioHealthState({
        source: 'web',
    });
    const nowMs = 10_000;

    const healthy = evaluateWebRemoteAudioHealth(initial, {
        trackPresent: true,
        bytesReceived: 1_024,
        packetsReceived: 24,
        playbackActive: true,
        audioLevel: 0.12,
    }, {
        nowMs: nowMs,
    });

    assert.equal(healthy.live, true);
    assert.equal(healthy.lastActiveAtMs, nowMs);
});

test('web remote audio drops after repeated zero-progress windows', () => {
    let state = evaluateWebRemoteAudioHealth(createRemoteAudioHealthState({
        source: 'web',
    }), {
        trackPresent: true,
        bytesReceived: 1_024,
        packetsReceived: 24,
        playbackActive: true,
    }, {
        nowMs: 10_000,
    });

    state = evaluateWebRemoteAudioHealth(state, {
        trackPresent: true,
        bytesReceived: 1_024,
        packetsReceived: 24,
        playbackActive: false,
        audioLevel: 0,
    }, {
        nowMs: 14_000,
        zeroProgressTolerance: 2,
    });
    state = evaluateWebRemoteAudioHealth(state, {
        trackPresent: true,
        bytesReceived: 1_024,
        packetsReceived: 24,
        playbackActive: false,
        audioLevel: 0,
    }, {
        nowMs: 18_000,
        zeroProgressTolerance: 2,
    });

    assert.equal(state.live, false);
    assert.equal(state.reason, 'zero_progress');
});

test('web remote audio drops immediately when the remote track disappears', () => {
    const state = evaluateWebRemoteAudioHealth(createRemoteAudioHealthState({
        source: 'web',
        live: true,
        trackPresent: true,
        lastActiveAtMs: 10_000,
    }), {
        trackPresent: false,
        forceOffline: true,
        reason: 'user_left',
    }, {
        nowMs: 11_000,
    });

    assert.equal(state.live, false);
    assert.equal(state.reason, 'user_left');
});

test('native remote audio stays live when recent decode activity keeps arriving', () => {
    const state = evaluateNativeRemoteAudioHealth(createRemoteAudioHealthState({
        source: 'native',
    }), {
        trackPresent: true,
        remoteAudioLive: true,
        remoteAudioPlaying: true,
        receivedBitrate: 26,
        lastRemoteAudioActiveAtMs: 20_000,
    }, {
        nowMs: 20_000,
    });

    assert.equal(state.live, true);
    assert.equal(state.lastActiveAtMs, 20_000);
});

test('native remote audio becomes unhealthy when bitrate and activity both go stale', () => {
    const active = evaluateNativeRemoteAudioHealth(createRemoteAudioHealthState({
        source: 'native',
    }), {
        trackPresent: true,
        remoteAudioLive: true,
        remoteAudioPlaying: true,
        receivedBitrate: 28,
        lastRemoteAudioActiveAtMs: 20_000,
    }, {
        nowMs: 20_000,
    });

    const stale = evaluateNativeRemoteAudioHealth(active, {
        trackPresent: true,
        remoteAudioLive: false,
        remoteAudioPlaying: false,
        receivedBitrate: 0,
        reason: 'bitrate_stale',
    }, {
        nowMs: 34_500,
        freshnessWindowMs: 12_000,
        zeroProgressTolerance: 2,
    });

    assert.equal(stale.live, false);
    assert.equal(stale.reason, 'bitrate_stale');
});
