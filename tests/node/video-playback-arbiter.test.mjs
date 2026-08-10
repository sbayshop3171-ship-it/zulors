import test from 'node:test';
import assert from 'node:assert/strict';

import {
    registerVideoPlaybackCandidate,
    requestVideoPlayback,
    setVideoPlaybackManualPause,
    unregisterVideoPlaybackCandidate,
    updateVideoPlaybackCandidate,
} from '../../resources/js/spa/kernel/services/media/video-playback-arbiter/index.js';

function createCandidate(id, events) {
    const unregister = registerVideoPlaybackCandidate({
        id,
        activate: () => {
            events.push(`${id}:play`);
        },
        deactivate: () => {
            events.push(`${id}:pause`);
        }
    });

    return {
        id,
        unregister,
    };
}

test('video arbiter activates only the most visible candidate', () => {
    const events = [];
    const first = createCandidate('first', events);
    const second = createCandidate('second', events);

    updateVideoPlaybackCandidate(first.id, {
        isReady: true,
        isVisible: true,
        ratio: 0.78,
        rect: { top: 120, height: 420 }
    });

    updateVideoPlaybackCandidate(second.id, {
        isReady: true,
        isVisible: true,
        ratio: 0.52,
        rect: { top: 620, height: 420 }
    });

    assert.equal(events.includes('first:play'), true);
    assert.equal(events.includes('second:play'), false);

    updateVideoPlaybackCandidate(second.id, {
        isReady: true,
        isVisible: true,
        ratio: 0.92,
        rect: { top: 160, height: 420 }
    });

    assert.equal(events.at(-2), 'first:pause');
    assert.equal(events.at(-1), 'second:play');

    first.unregister();
    second.unregister();
});

test('manual pause hold prevents another visible video from auto-playing until hidden', () => {
    const events = [];
    const first = createCandidate('held', events);
    const second = createCandidate('backup', events);

    updateVideoPlaybackCandidate(first.id, {
        isReady: true,
        isVisible: true,
        ratio: 0.8,
        rect: { top: 140, height: 420 }
    });
    updateVideoPlaybackCandidate(second.id, {
        isReady: true,
        isVisible: true,
        ratio: 0.7,
        rect: { top: 580, height: 420 }
    });

    setVideoPlaybackManualPause(first.id, true);

    const playEventsWhileHeld = events.filter((eventName) => eventName === 'backup:play').length;
    assert.equal(playEventsWhileHeld, 0);

    updateVideoPlaybackCandidate(first.id, {
        isReady: true,
        isVisible: false,
        ratio: 0,
        rect: { top: -600, height: 420 }
    });

    assert.equal(events.at(-1), 'backup:play');

    first.unregister();
    second.unregister();
});

test('explicit playback request promotes the requested candidate', () => {
    const events = [];
    const first = createCandidate('first', events);
    const second = createCandidate('second', events);

    updateVideoPlaybackCandidate(first.id, {
        isReady: true,
        isVisible: true,
        ratio: 0.65,
        rect: { top: 120, height: 420 }
    });
    updateVideoPlaybackCandidate(second.id, {
        isReady: true,
        isVisible: true,
        ratio: 0.63,
        rect: { top: 180, height: 420 }
    });

    requestVideoPlayback(second.id);

    assert.equal(events.at(-2), 'first:pause');
    assert.equal(events.at(-1), 'second:play');

    first.unregister();
    second.unregister();
});
