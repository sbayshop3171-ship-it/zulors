import test from 'node:test';
import assert from 'node:assert/strict';

import {
    buildFeedValidatorHeaders,
    evictViewerFeedSnapshots,
    feedSnapshotHotLimit,
    feedSnapshotMaxAgeMs,
    feedSnapshotStaleAfterMs,
    isNotModifiedResponse,
    mergeFeedSnapshots,
    readFeedSnapshot,
    readFeedSnapshotSync,
    writeFeedSnapshot,
} from '../../resources/js/spa/kernel/services/cache/feed-cache.js';

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
        },
        key(index) {
            return Array.from(store.keys())[index] ?? null;
        },
        get length() {
            return store.size;
        },
    };
};

test('feed snapshots keep a hot local copy with validators for instant Home render', async () => {
    globalThis.localStorage = makeStorage();
    delete globalThis.indexedDB;

    const posts = Array.from({ length: feedSnapshotHotLimit + 5 }, (_, index) => ({ id: index + 1 }));

    await writeFeedSnapshot('user:10', 'colibri.desktop.timeline.public_feed.first_page.v2.10', {
        posts: posts,
        meta: { feed: { type: 'for_you' } },
        etag: '"zulors-home-feed-abc"',
        snapshot_hash: 'abc',
    });

    const snapshot = readFeedSnapshotSync('user:10', 'colibri.desktop.timeline.public_feed.first_page.v2.10');

    assert.equal(snapshot.posts.length, feedSnapshotHotLimit);
    assert.equal(snapshot.posts[0].id, 1);
    assert.equal(snapshot.etag, '"zulors-home-feed-abc"');
    assert.equal(snapshot.snapshotHash, 'abc');
    assert.equal(snapshot.meta.feed.type, 'for_you');
});

test('stale Home feed snapshots remain readable until the offline max age expires', async () => {
    globalThis.localStorage = makeStorage();
    delete globalThis.indexedDB;

    const key = 'colibri.mobile.timeline.public_feed.first_page.v2.77';
    const staleTimestamp = Date.now() - feedSnapshotStaleAfterMs - 1000;

    globalThis.localStorage.setItem(key, JSON.stringify({
        data: [{ id: 77 }],
        timestamp: staleTimestamp,
    }));

    const snapshot = await readFeedSnapshot('user:77', key);

    assert.equal(snapshot.posts[0].id, 77);
    assert.equal(snapshot.isStale, true);
    assert.equal(snapshot.isExpired, false);

    globalThis.localStorage.setItem(key, JSON.stringify({
        data: [{ id: 88 }],
        timestamp: Date.now() - feedSnapshotMaxAgeMs - 1000,
    }));

    const expiredSnapshot = await readFeedSnapshot('user:77', key);

    assert.deepEqual(expiredSnapshot.posts, []);
    assert.equal(expiredSnapshot.isExpired, true);
});

test('feed validators and 304 responses support background SWR refreshes', () => {
    assert.deepEqual(buildFeedValidatorHeaders('"etag-1"'), {
        'If-None-Match': '"etag-1"',
    });
    assert.deepEqual(buildFeedValidatorHeaders(null), {});
    assert.equal(isNotModifiedResponse({ status: 304 }), true);
    assert.equal(isNotModifiedResponse({ status: 200 }), false);
});

test('feed snapshot merge keeps fresh posts first and deduplicates cached posts', () => {
    const merged = mergeFeedSnapshots(
        [{ id: 1 }, { id: 2 }],
        [{ id: 2 }, { id: 3 }],
        10
    );

    assert.deepEqual(merged.map((post) => post.id), [2, 3, 1]);
});

test('viewer feed eviction clears user-scoped hot cache entries', async () => {
    globalThis.localStorage = makeStorage();
    delete globalThis.indexedDB;

    await writeFeedSnapshot('user:55', 'colibri.desktop.timeline.public_feed.first_page.v2.55', {
        posts: [{ id: 55 }],
        etag: '"etag-55"',
    });

    assert.ok(globalThis.localStorage.getItem('colibri.desktop.timeline.public_feed.first_page.v2.55'));

    await evictViewerFeedSnapshots('user:55');

    assert.equal(globalThis.localStorage.getItem('colibri.desktop.timeline.public_feed.first_page.v2.55'), null);
    assert.equal(globalThis.localStorage.getItem('colibri.desktop.timeline.public_feed.first_page.v2.55.swr_meta'), null);
});
