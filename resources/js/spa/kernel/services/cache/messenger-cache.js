const CACHE_NAMESPACE = 'colibri:messenger:v2:';
const DEFAULT_TTL = 1000 * 60 * 60 * 24;

const canUseStorage = () => {
    return typeof window !== 'undefined' && Boolean(window.localStorage);
};

const cacheKey = (scope, key) => {
    return `${CACHE_NAMESPACE}${scope}:${key}`;
};

const readMessengerCache = (scope, key, fallback = null, ttl = DEFAULT_TTL) => {
    if(! canUseStorage()) {
        return fallback;
    }

    try {
        const entry = JSON.parse(window.localStorage.getItem(cacheKey(scope, key)));

        if(! entry || typeof entry !== 'object' || ! Object.prototype.hasOwnProperty.call(entry, 'data')) {
            return fallback;
        }

        if(entry.timestamp && (Date.now() - entry.timestamp) > ttl) {
            return fallback;
        }

        return entry;
    }
    catch(error) {
        return fallback;
    }
};

const writeMessengerCache = (scope, key, data) => {
    if(! canUseStorage()) {
        return false;
    }

    try {
        window.localStorage.setItem(cacheKey(scope, key), JSON.stringify({
            timestamp: Date.now(),
            data: data,
        }));

        return true;
    }
    catch(error) {
        return false;
    }
};

export {
    readMessengerCache,
    writeMessengerCache,
};

export default {
    read: readMessengerCache,
    write: writeMessengerCache,
};
