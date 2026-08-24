import test from 'node:test';
import assert from 'node:assert/strict';

import {
    buildReelInteractionSignal,
    reorderReelsBySignalEntries,
} from '../../resources/js/spa/kernel/services/feed-session/reels-session-signals.js';

test('short reel exposure is normalized into an immediate skip signal', () => {
    const signal = buildReelInteractionSignal({
        postId: 44,
        eventType: 'video_watch',
        totalWatchMs: 800,
        durationSeconds: 12,
        completionRate: 0.07
    });

    assert.equal(signal?.eventType, 'video_skip');
    assert.equal(signal?.immediateRerank, true);
    assert.equal(signal?.hardSuppress, false);
    assert.ok(signal?.rerankWeight > 3);
});

test('recently suppressed reels are pushed behind fresh reels and hard feedback can drop from the early window', () => {
    const currentTimeMs = Date.UTC(2026, 7, 24, 12, 0, 0);
    const reordered = reorderReelsBySignalEntries([
        { id: 11 },
        { id: 22 },
        { id: 33 }
    ], {
        '11': {
            suppressUntil: currentTimeMs + (1000 * 60 * 30),
            hardSuppress: false,
            priority: 20,
            lastSignalAt: currentTimeMs - 5000
        },
        '22': {
            suppressUntil: currentTimeMs + (1000 * 60 * 60 * 24),
            hardSuppress: true,
            priority: 40,
            lastSignalAt: currentTimeMs - 1000
        }
    }, {
        currentTimeMs: currentTimeMs,
        minimumFresh: 1
    });

    assert.deepEqual(reordered.map((postData) => postData.id), [33, 11]);
});
