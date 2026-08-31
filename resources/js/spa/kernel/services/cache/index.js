const canUseStorage = () => {
    const storage = typeof window !== 'undefined' ? window.localStorage : globalThis.localStorage;

    return Boolean(storage);
};

const now = () => {
    return Date.now();
};

function readLocalFirstSnapshot(key, fallback = null, staleAfterMs = (1000 * 60 * 5), maxAgeMs = (1000 * 60 * 60 * 24)) {
    if(! canUseStorage()) {
        return {
            data: fallback,
            timestamp: 0,
            isFresh: false,
            isStale: false,
            isExpired: false,
        };
    }

    try {
        const storage = typeof window !== 'undefined' ? window.localStorage : globalThis.localStorage;
        const cacheItem = JSON.parse(storage.getItem(key));

        if(! cacheItem || ! cacheItem.timestamp) {
            return {
                data: fallback,
                timestamp: 0,
                isFresh: false,
                isStale: false,
                isExpired: false,
            };
        }

        const observedAge = now() - Number(cacheItem.timestamp);

        if(observedAge > maxAgeMs) {
            const storage = typeof window !== 'undefined' ? window.localStorage : globalThis.localStorage;
            storage.removeItem(key);

            return {
                data: fallback,
                timestamp: Number(cacheItem.timestamp),
                isFresh: false,
                isStale: false,
                isExpired: true,
            };
        }

        return {
            data: cacheItem.data ?? fallback,
            timestamp: Number(cacheItem.timestamp),
            isFresh: observedAge <= staleAfterMs,
            isStale: observedAge > staleAfterMs,
            isExpired: false,
        };
    }
    catch (error) {
        return {
            data: fallback,
            timestamp: 0,
            isFresh: false,
            isStale: false,
            isExpired: false,
        };
    }
}

function writeLocalFirstSnapshot(key, data, timestamp = now()) {
    if(! canUseStorage()) {
        return;
    }

    try {
        const storage = typeof window !== 'undefined' ? window.localStorage : globalThis.localStorage;
        storage.setItem(key, JSON.stringify({
            data: data,
            timestamp: timestamp
        }));
    }
    catch (error) {
        // Browser storage can be unavailable or full. Cache is only a speed-up.
    }
}

function mergeLocalFeed(previousPosts = [], nextPosts = [], limit = 30) {
    const merged = [];
    const seen = new Set();
    const candidates = [];

    if(Array.isArray(previousPosts)) {
        candidates.push(...previousPosts);
    }

    if(Array.isArray(nextPosts)) {
        candidates.push(...nextPosts);
    }

    for(const post of candidates) {
        if(! post || typeof post !== 'object') {
            continue;
        }

        const uniqueKey = post.id ?? post.hash_id ?? post.meta?.client_id ?? null;

        if(uniqueKey === null || seen.has(String(uniqueKey))) {
            continue;
        }

        seen.add(String(uniqueKey));
        merged.push(post);

        if(merged.length >= limit) {
            break;
        }
    }

    return merged;
}

function readCache(key, fallback = null, ttl = (1000 * 60 * 60)) {
    if(! canUseStorage()) {
        return fallback;
    }

    try {
        const storage = typeof window !== 'undefined' ? window.localStorage : globalThis.localStorage;
        const cacheItem = JSON.parse(storage.getItem(key));

        if(! cacheItem || ! cacheItem.timestamp) {
            return fallback;
        }

        if((now() - cacheItem.timestamp) > ttl) {
            storage.removeItem(key);

            return fallback;
        }

        return cacheItem.data ?? fallback;
    }
    catch (error) {
        return fallback;
    }
}

function readCacheEntry(key, ttl = (1000 * 60 * 60)) {
    if(! canUseStorage()) {
        return null;
    }

    try {
        const storage = typeof window !== 'undefined' ? window.localStorage : globalThis.localStorage;
        const cacheItem = JSON.parse(storage.getItem(key));

        if(! cacheItem || ! cacheItem.timestamp) {
            return null;
        }

        if((now() - cacheItem.timestamp) > ttl) {
            storage.removeItem(key);

            return null;
        }

        return cacheItem;
    }
    catch (error) {
        return null;
    }
}

function writeCache(key, data) {
    writeLocalFirstSnapshot(key, data);
}

export {
    readCache,
    readCacheEntry,
    readLocalFirstSnapshot,
    writeCache,
    writeLocalFirstSnapshot,
    mergeLocalFeed,
};
