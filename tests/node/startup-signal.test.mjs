import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    signalFirstVisualReadyAfterPaint
} from '../../resources/js/spa/kernel/services/startup/index.js';

test('first visual ready waits for a painted frame before notifying native Android', () => {
    const previousWindow = globalThis.window;
    const previousPerformance = globalThis.performance;
    const callbacks = [];
    const nativeCalls = [];
    let now = 0;

    globalThis.performance = {
        now: () => ++now,
        mark: () => {}
    };

    globalThis.window = {
        requestAnimationFrame: (callback) => {
            callbacks.push(callback);

            return callbacks.length;
        },
        setTimeout: (callback) => {
            callback();

            return 1;
        },
        dispatchEvent: () => {},
        ZulorsStartup: {
            firstVisualReady: (detailJson) => {
                nativeCalls.push(JSON.parse(detailJson));
            }
        }
    };

    try {
        assert.equal(signalFirstVisualReadyAfterPaint({
            route: 'home',
            homePosts: 12
        }), true);
        assert.equal(nativeCalls.length, 0);
        assert.equal(callbacks.length, 1);

        callbacks.shift()();
        assert.equal(nativeCalls.length, 0);
        assert.equal(callbacks.length, 1);

        callbacks.shift()();
        assert.equal(nativeCalls.length, 1);
        assert.equal(nativeCalls[0].route, 'home');
        assert.equal(nativeCalls[0].homePosts, 12);
    }
    finally {
        if (previousWindow === undefined) {
            delete globalThis.window;
        }
        else {
            globalThis.window = previousWindow;
        }

        if (previousPerformance === undefined) {
            delete globalThis.performance;
        }
        else {
            globalThis.performance = previousPerformance;
        }
    }
});
