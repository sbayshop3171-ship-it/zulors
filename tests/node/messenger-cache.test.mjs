import test from 'node:test';
import assert from 'node:assert/strict';

import { readMessengerCache, writeMessengerCache } from '../../resources/js/spa/kernel/services/cache/messenger-cache.js';

test('messenger cache is safe when browser storage is unavailable', () => {
    assert.equal(writeMessengerCache('chat', 'abc', { messages: [] }), false);
    assert.equal(readMessengerCache('chat', 'abc', 'fallback'), 'fallback');
});

test('messenger content cache requires a viewer-scoped key', () => {
    const originalWindow = globalThis.window;
    const entries = new Map();

    globalThis.window = {
        localStorage: {
            get length() { return entries.size; },
            key(index) { return [...entries.keys()][index] ?? null; },
            getItem(key) { return entries.get(key) ?? null; },
            setItem(key, value) { entries.set(key, value); },
            removeItem(key) { entries.delete(key); }
        }
    };

    try {
        assert.equal(writeMessengerCache('chat', 'abc', { messages: ['private'] }), false);
        assert.equal(readMessengerCache('chat', 'abc', 'fallback'), 'fallback');
        assert.equal(writeMessengerCache('viewer-42:chat', 'abc', { messages: ['private'] }), true);
        assert.deepEqual(readMessengerCache('viewer-42:chat', 'abc', 'fallback').data, { messages: ['private'] });
        assert.equal(readMessengerCache('viewer-84:chat', 'abc', 'fallback'), 'fallback');
    }
    finally {
        if(originalWindow === undefined) {
            delete globalThis.window;
        }
        else {
            globalThis.window = originalWindow;
        }
    }
});
