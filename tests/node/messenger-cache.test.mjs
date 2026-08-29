import test from 'node:test';
import assert from 'node:assert/strict';

import { readMessengerCache, writeMessengerCache } from '../../resources/js/spa/kernel/services/cache/messenger-cache.js';

test('messenger cache is safe when browser storage is unavailable', () => {
    assert.equal(writeMessengerCache('chat', 'abc', { messages: [] }), false);
    assert.equal(readMessengerCache('chat', 'abc', 'fallback'), 'fallback');
});
