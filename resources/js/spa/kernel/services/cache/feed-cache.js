import {
    readLocalFirstSnapshot,
    writeLocalFirstSnapshot,
    mergeLocalFeed,
} from './index.js';

const FEED_CACHE_DB_NAME = 'ZulorsAppCacheDB';
const FEED_CACHE_DB_VERSION = 1;
const FEED_SNAPSHOT_STORE = 'feed_snapshots';
const FEED_CACHE_SCHEMA_VERSION = 1;

const feedSnapshotStaleAfterMs = 1000 * 60 * 5;
const feedSnapshotMaxAgeMs = 1000 * 60 * 60 * 24 * 7;
const feedSnapshotIdbLimit = 90;
const feedSnapshotHotLimit = 30;

let feedCacheDbPromise = null;

const now = () => Date.now();

const canUseIndexedDB = () => {
    return typeof indexedDB !== 'undefined';
};

const storage = () => {
    return typeof window !== 'undefined' ? window.localStorage : globalThis.localStorage;
};

const canUseStorage = () => {
    return Boolean(storage());
};

const normalizeViewerKey = (viewerKey = 'guest') => {
    return String(viewerKey || 'guest');
};

const normalizeFeedKey = (feedKey = 'home') => {
    return String(feedKey || 'home');
};

const snapshotKey = (viewerKey, feedKey) => {
    return `${normalizeViewerKey(viewerKey)}::${normalizeFeedKey(feedKey)}`;
};

const localFeedMetaKey = (feedKey) => {
    return `${normalizeFeedKey(feedKey)}.swr_meta`;
};

const normalizePosts = (posts = [], limit = feedSnapshotIdbLimit) => {
    return Array.isArray(posts) ? posts.filter(Boolean).slice(0, limit) : [];
};

const responseHeader = (response, key) => {
    const headers = response?.headers ?? {};
    const lowerKey = String(key).toLowerCase();

    if(typeof headers.get === 'function') {
        return headers.get(lowerKey);
    }

    return headers[lowerKey] ?? headers[key] ?? null;
};

const responseEtag = (response) => {
    return responseHeader(response, 'etag');
};

const responseSnapshotHash = (response) => {
    return responseHeader(response, 'x-zulors-feed-snapshot');
};

const buildSnapshot = (viewerKey, feedKey, data = {}) => {
    const timestamp = Number(data.timestamp || data.cached_at || now());
    const posts = normalizePosts(data.posts ?? data.data ?? []);

    return {
        key: snapshotKey(viewerKey, feedKey),
        viewer_key: normalizeViewerKey(viewerKey),
        feed_key: normalizeFeedKey(feedKey),
        posts: posts,
        meta: data.meta ?? null,
        etag: data.etag ?? null,
        snapshot_hash: data.snapshot_hash ?? data.snapshotHash ?? null,
        cached_at: timestamp,
        stale_after_ms: Number(data.stale_after_ms || feedSnapshotStaleAfterMs),
        max_age_ms: Number(data.max_age_ms || feedSnapshotMaxAgeMs),
        schema_version: FEED_CACHE_SCHEMA_VERSION,
    };
};

const withSnapshotState = (snapshot, fallbackPosts = []) => {
    if(! snapshot || ! Array.isArray(snapshot.posts)) {
        return {
            posts: normalizePosts(fallbackPosts),
            meta: null,
            etag: null,
            snapshotHash: null,
            timestamp: 0,
            isFresh: false,
            isStale: false,
            isExpired: false,
        };
    }

    const timestamp = Number(snapshot.cached_at || 0);
    const age = now() - timestamp;
    const staleAfterMs = Number(snapshot.stale_after_ms || feedSnapshotStaleAfterMs);
    const maxAgeMs = Number(snapshot.max_age_ms || feedSnapshotMaxAgeMs);

    return {
        posts: normalizePosts(snapshot.posts),
        meta: snapshot.meta ?? null,
        etag: snapshot.etag ?? null,
        snapshotHash: snapshot.snapshot_hash ?? null,
        timestamp: timestamp,
        isFresh: Boolean(timestamp && age <= staleAfterMs),
        isStale: Boolean(timestamp && age > staleAfterMs && age <= maxAgeMs),
        isExpired: Boolean(! timestamp || age > maxAgeMs),
    };
};

const openFeedCacheDB = async () => {
    if(! canUseIndexedDB()) {
        return null;
    }

    if(feedCacheDbPromise) {
        return feedCacheDbPromise;
    }

    feedCacheDbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(FEED_CACHE_DB_NAME, FEED_CACHE_DB_VERSION);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            if(! db.objectStoreNames.contains(FEED_SNAPSHOT_STORE)) {
                const store = db.createObjectStore(FEED_SNAPSHOT_STORE, { keyPath: 'key' });

                store.createIndex('viewer_key', 'viewer_key', { unique: false });
                store.createIndex('feed_key', 'feed_key', { unique: false });
                store.createIndex('cached_at', 'cached_at', { unique: false });
            }
        };
    }).catch(() => {
        feedCacheDbPromise = null;

        return null;
    });

    return feedCacheDbPromise;
};

const idbRequest = (request) => {
    return new Promise((resolve, reject) => {
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
};

const readFeedSnapshotSync = (viewerKey, feedKey, fallbackPosts = []) => {
    const localSnapshot = readLocalFirstSnapshot(
        normalizeFeedKey(feedKey),
        normalizePosts(fallbackPosts, feedSnapshotHotLimit),
        feedSnapshotStaleAfterMs,
        feedSnapshotMaxAgeMs
    );
    const localPosts = normalizePosts(localSnapshot.data, feedSnapshotHotLimit);
    let localMeta = null;

    if(canUseStorage()) {
        try {
            localMeta = JSON.parse(storage().getItem(localFeedMetaKey(feedKey)));
        }
        catch (error) {
            localMeta = null;
        }
    }

    return {
        posts: localPosts,
        meta: localMeta?.meta ?? null,
        etag: localMeta?.etag ?? null,
        snapshotHash: localMeta?.snapshot_hash ?? null,
        timestamp: Number(localMeta?.timestamp || localSnapshot.timestamp || 0),
        isFresh: localSnapshot.isFresh,
        isStale: localSnapshot.isStale,
        isExpired: localSnapshot.isExpired,
        viewerKey: normalizeViewerKey(viewerKey),
        feedKey: normalizeFeedKey(feedKey),
    };
};

const readFeedSnapshot = async (viewerKey, feedKey, fallbackPosts = []) => {
    const db = await openFeedCacheDB();

    if(! db) {
        return readFeedSnapshotSync(viewerKey, feedKey, fallbackPosts);
    }

    try {
        const transaction = db.transaction([FEED_SNAPSHOT_STORE], 'readonly');
        const store = transaction.objectStore(FEED_SNAPSHOT_STORE);
        const snapshot = await idbRequest(store.get(snapshotKey(viewerKey, feedKey)));
        const state = withSnapshotState(snapshot, fallbackPosts);

        if(state.isExpired && snapshot?.key) {
            evictFeedSnapshot(viewerKey, feedKey).catch(() => {});
        }

        if(state.isExpired) {
            return readFeedSnapshotSync(viewerKey, feedKey, fallbackPosts);
        }

        return {
            ...state,
            viewerKey: normalizeViewerKey(viewerKey),
            feedKey: normalizeFeedKey(feedKey),
        };
    }
    catch (error) {
        return readFeedSnapshotSync(viewerKey, feedKey, fallbackPosts);
    }
};

const writeFeedSnapshot = async (viewerKey, feedKey, data = {}) => {
    const snapshot = buildSnapshot(viewerKey, feedKey, data);
    const hotPosts = snapshot.posts.slice(0, feedSnapshotHotLimit);

    writeLocalFirstSnapshot(normalizeFeedKey(feedKey), hotPosts, snapshot.cached_at);

    if(canUseStorage()) {
        try {
            storage().setItem(localFeedMetaKey(feedKey), JSON.stringify({
                viewer_key: snapshot.viewer_key,
                feed_key: snapshot.feed_key,
                etag: snapshot.etag,
                snapshot_hash: snapshot.snapshot_hash,
                meta: snapshot.meta,
                timestamp: snapshot.cached_at,
            }));
        }
        catch (error) {
            //
        }
    }

    const db = await openFeedCacheDB();

    if(! db) {
        return snapshot;
    }

    try {
        const transaction = db.transaction([FEED_SNAPSHOT_STORE], 'readwrite');
        const store = transaction.objectStore(FEED_SNAPSHOT_STORE);

        await idbRequest(store.put(snapshot));
    }
    catch (error) {
        //
    }

    return snapshot;
};

const writeFeedResponseSnapshot = async (viewerKey, feedKey, response, posts = [], meta = null) => {
    return await writeFeedSnapshot(viewerKey, feedKey, {
        posts: posts,
        meta: meta,
        etag: responseEtag(response),
        snapshot_hash: responseSnapshotHash(response),
    });
};

const evictFeedSnapshot = async (viewerKey, feedKey) => {
    if(canUseStorage()) {
        try {
            storage().removeItem(normalizeFeedKey(feedKey));
            storage().removeItem(localFeedMetaKey(feedKey));
        }
        catch (error) {
            //
        }
    }

    const db = await openFeedCacheDB();

    if(! db) {
        return false;
    }

    try {
        const transaction = db.transaction([FEED_SNAPSHOT_STORE], 'readwrite');
        const store = transaction.objectStore(FEED_SNAPSHOT_STORE);

        await idbRequest(store.delete(snapshotKey(viewerKey, feedKey)));

        return true;
    }
    catch (error) {
        return false;
    }
};

const knownViewerFeedStorageKeys = (viewerKey) => {
    const normalizedViewerKey = normalizeViewerKey(viewerKey);
    const userId = normalizedViewerKey.startsWith('user:')
        ? normalizedViewerKey.slice('user:'.length)
        : normalizedViewerKey;
    const suffix = normalizedViewerKey === 'guest' ? 'guest' : userId;
    const keys = [
        `colibri.desktop.timeline.public_feed.first_page.v2.${suffix}`,
        `colibri.mobile.timeline.public_feed.first_page.v2.${suffix}`,
    ];

    if(normalizedViewerKey === 'guest') {
        keys.push(
            'colibri.desktop.timeline.public_feed.first_page.shared.v1',
            'colibri.mobile.timeline.public_feed.first_page.shared.v1'
        );
    }

    return keys;
};

const purgeNavigationShellCache = () => {
    try {
        if(typeof navigator !== 'undefined' && navigator.serviceWorker?.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'ZULORS_PURGE_NAVIGATION_SHELL',
            });
        }
    }
    catch (error) {
        //
    }
};

const evictViewerFeedSnapshots = async (viewerKey) => {
    const normalizedViewerKey = normalizeViewerKey(viewerKey);

    purgeNavigationShellCache();

    if(canUseStorage()) {
        try {
            knownViewerFeedStorageKeys(normalizedViewerKey).forEach((key) => {
                storage().removeItem(key);
                storage().removeItem(localFeedMetaKey(key));
            });
        }
        catch (error) {
            //
        }
    }

    const db = await openFeedCacheDB();

    if(! db) {
        return 0;
    }

    return await new Promise((resolve) => {
        const transaction = db.transaction([FEED_SNAPSHOT_STORE], 'readwrite');
        const store = transaction.objectStore(FEED_SNAPSHOT_STORE);
        const index = store.index('viewer_key');
        const request = index.openCursor(IDBKeyRange.only(normalizedViewerKey));
        let deleted = 0;

        request.onerror = () => resolve(deleted);
        request.onsuccess = (event) => {
            const cursor = event.target.result;

            if(cursor) {
                cursor.delete();
                deleted++;
                cursor.continue();

                return;
            }

            resolve(deleted);
        };
    });
};

const currentFeedViewerKey = () => {
    if(typeof window === 'undefined') {
        return 'guest';
    }

    const userId = window.__zulorsBoot?.authUserId
        ?? window.__zulorsBoot?.cachedBootstrap?.auth?.user?.id
        ?? null;

    return userId ? `user:${userId}` : 'guest';
};

const evictCurrentFeedViewerSnapshots = async () => {
    return await evictViewerFeedSnapshots(currentFeedViewerKey());
};

const buildFeedValidatorHeaders = (etag) => {
    return etag ? { 'If-None-Match': etag } : {};
};

const isNotModifiedResponse = (response) => {
    return Number(response?.status || 0) === 304;
};

const isAuthCachePurgeError = (error) => {
    return [401, 403, 419].includes(Number(error?.response?.status || 0));
};

const mergeFeedSnapshots = (previousPosts = [], nextPosts = [], limit = feedSnapshotIdbLimit) => {
    return mergeLocalFeed(nextPosts, previousPosts, limit);
};

export {
    feedSnapshotStaleAfterMs,
    feedSnapshotMaxAgeMs,
    feedSnapshotIdbLimit,
    feedSnapshotHotLimit,
    readFeedSnapshotSync,
    readFeedSnapshot,
    writeFeedSnapshot,
    writeFeedResponseSnapshot,
    evictFeedSnapshot,
    evictViewerFeedSnapshots,
    evictCurrentFeedViewerSnapshots,
    currentFeedViewerKey,
    buildFeedValidatorHeaders,
    isNotModifiedResponse,
    isAuthCachePurgeError,
    mergeFeedSnapshots,
    responseEtag,
    responseSnapshotHash,
};
