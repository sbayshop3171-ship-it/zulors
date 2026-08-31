import test from 'node:test';
import assert from 'node:assert/strict';

import {
  mergeLocalFeed,
  readLocalFirstSnapshot,
  writeLocalFirstSnapshot,
} from '../../resources/js/spa/kernel/services/cache/index.js';

const makeStorage = () => {
  const store = new Map();

  return {
    getItem(key) {
      return store.has(key) ? store.get(key) : null;
    },
    setItem(key, value) {
      store.set(key, String(value));
    },
    removeItem(key) {
      store.delete(key);
    },
    clear() {
      store.clear();
    }
  };
};

test('readLocalFirstSnapshot keeps stale data available while a background refresh is pending', () => {
  const storage = makeStorage();
  globalThis.localStorage = storage;

  const key = 'local-first-feed';
  const staleTimestamp = Date.now() - (1000 * 60 * 10);

  storage.setItem(key, JSON.stringify({
    data: { posts: [{ id: 101, text: 'cached post' }] },
    timestamp: staleTimestamp,
  }));

  const snapshot = readLocalFirstSnapshot(key, { posts: [] }, 1000 * 60 * 5, 1000 * 60 * 30);

  assert.equal(snapshot.isStale, true);
  assert.equal(snapshot.data.posts[0].id, 101);
  assert.equal(snapshot.data.posts.length, 1);
});

test('writeLocalFirstSnapshot and mergeLocalFeed keep feed entries deduplicated and quickly refreshed', () => {
  const storage = makeStorage();
  globalThis.localStorage = storage;

  const previousPosts = [{ id: 1 }, { id: 2 }];
  const nextPosts = [{ id: 2 }, { id: 3 }];

  writeLocalFirstSnapshot('merged-feed', previousPosts);
  const merged = mergeLocalFeed(previousPosts, nextPosts, 10);

  assert.deepEqual(merged.map((post) => post.id), [1, 2, 3]);
  assert.equal(Array.isArray(readLocalFirstSnapshot('merged-feed', []).data), true);
});
