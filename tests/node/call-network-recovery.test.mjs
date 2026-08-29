import test from 'node:test';
import assert from 'node:assert/strict';

import {
    shouldForceReconnectHangup,
    shouldWatchDegradedCallRecovery,
} from '../../resources/js/spa/kernel/stores/calls/network-recovery.js';

test('degraded recovery watches only when connected audio is already missing', () => {
    assert.equal(shouldWatchDegradedCallRecovery({
        status: 'connected',
        networkState: 'poor',
        hasLiveRemoteAudio: false
    }), true);

    assert.equal(shouldWatchDegradedCallRecovery({
        status: 'connected',
        networkState: 'reconnecting',
        hasLiveRemoteAudio: false
    }), true);

    assert.equal(shouldWatchDegradedCallRecovery({
        status: 'connected',
        networkState: 'poor',
        hasLiveRemoteAudio: true
    }), false);

    assert.equal(shouldWatchDegradedCallRecovery({
        status: 'connecting',
        networkState: 'poor',
        hasLiveRemoteAudio: false
    }), false);

    assert.equal(shouldWatchDegradedCallRecovery({
        status: 'connected',
        networkState: 'poor',
        hasLiveRemoteAudio: false,
        remoteMuted: true
    }), false);

    assert.equal(shouldWatchDegradedCallRecovery({
        status: 'connected',
        networkState: 'poor',
        hasLiveRemoteAudio: false,
        audioPlaybackBlocked: true
    }), false);
});

test('reconnect timeout hangs up only after audio is gone', () => {
    assert.equal(shouldForceReconnectHangup({
        isActive: true,
        networkState: 'reconnecting',
        hasLiveRemoteAudio: false
    }), true);

    assert.equal(shouldForceReconnectHangup({
        isActive: true,
        networkState: 'reconnecting',
        hasLiveRemoteAudio: true
    }), false);

    assert.equal(shouldForceReconnectHangup({
        isActive: true,
        networkState: 'poor',
        hasLiveRemoteAudio: false
    }), false);

    assert.equal(shouldForceReconnectHangup({
        isActive: true,
        networkState: 'reconnecting',
        hasLiveRemoteAudio: false,
        remoteMuted: true
    }), false);

    assert.equal(shouldForceReconnectHangup({
        isActive: true,
        networkState: 'reconnecting',
        hasLiveRemoteAudio: false,
        audioPlaybackBlocked: true
    }), false);
});
