const canUseStorage = () => {
    return typeof window !== 'undefined' && window.localStorage;
};

const now = () => {
    return Date.now();
};

function readCache(key, fallback = null, ttl = (1000 * 60 * 60)) {
    if(! canUseStorage()) {
        return fallback;
    }

    try {
        const cacheItem = JSON.parse(window.localStorage.getItem(key));

        if(! cacheItem || ! cacheItem.timestamp) {
            return fallback;
        }

        if((now() - cacheItem.timestamp) > ttl) {
            window.localStorage.removeItem(key);

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
        const cacheItem = JSON.parse(window.localStorage.getItem(key));

        if(! cacheItem || ! cacheItem.timestamp) {
            return null;
        }

        if((now() - cacheItem.timestamp) > ttl) {
            window.localStorage.removeItem(key);

            return null;
        }

        return cacheItem;
    }
    catch (error) {
        return null;
    }
}

function writeCache(key, data) {
    if(! canUseStorage()) {
        return;
    }

    try {
        window.localStorage.setItem(key, JSON.stringify({
            data: data,
            timestamp: now()
        }));
    }
    catch (error) {
        // Browser storage can be unavailable or full. Cache is only a speed-up.
    }
}

export { readCache, readCacheEntry, writeCache };
