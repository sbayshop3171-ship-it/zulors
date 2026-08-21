const performanceNow = () => {
    if (typeof performance !== 'undefined' && typeof performance.now === 'function') {
        return Math.round(performance.now());
    }

    return Date.now();
};

const readResponseHeader = (headers, name) => {
    if (!headers || !name) {
        return null;
    }

    if (typeof headers.get === 'function') {
        return headers.get(name);
    }

    return headers[name.toLowerCase()] ?? headers[name] ?? null;
};

const serializeDetail = (detail = {}) => {
    try {
        return JSON.parse(JSON.stringify(detail ?? {}));
    }
    catch (error) {
        return {};
    }
};

const getStartupState = () => {
    if (typeof window === 'undefined') {
        return {
            marks: {},
            nativeReadySent: false
        };
    }

    window.__zulorsStartup = window.__zulorsStartup || {
        launchedAt: Date.now(),
        perfStartedAt: performanceNow(),
        marks: {},
        nativeReadySent: false
    };

    if (!window.__zulorsStartup.marks || typeof window.__zulorsStartup.marks !== 'object') {
        window.__zulorsStartup.marks = {};
    }

    if (typeof window.__zulorsStartup.nativeReadySent !== 'boolean') {
        window.__zulorsStartup.nativeReadySent = false;
    }

    return window.__zulorsStartup;
};

const markStartupEvent = (name, detail = {}) => {
    if (typeof window === 'undefined' || !name) {
        return null;
    }

    const startupState = getStartupState();
    const safeDetail = serializeDetail(detail);
    const entry = {
        at: performanceNow(),
        detail: safeDetail
    };

    startupState.marks[name] = entry;

    if (typeof performance !== 'undefined' && typeof performance.mark === 'function') {
        try {
            performance.mark(`zulors:${name}`);
        }
        catch (error) {
            //
        }
    }

    try {
        window.dispatchEvent(new CustomEvent('zulors:startup', {
            detail: {
                name: name,
                at: entry.at,
                ...safeDetail
            }
        }));
    }
    catch (error) {
        //
    }

    return entry;
};

const markStartupResponse = (name, response, detail = {}) => {
    return markStartupEvent(name, {
        ...detail,
        serverTiming: readResponseHeader(response?.headers, 'server-timing'),
        cacheHeader: (
            readResponseHeader(response?.headers, 'x-zulors-cache')
            ?? readResponseHeader(response?.headers, 'x-zulors-home-feed-cache')
            ?? readResponseHeader(response?.headers, 'x-zulors-translations-cache')
        )
    });
};

const deferStartupTask = (callback, timeout = 400) => {
    if (typeof window === 'undefined' || typeof callback !== 'function') {
        return null;
    }

    if ('requestIdleCallback' in window) {
        return window.requestIdleCallback(callback, {
            timeout: timeout
        });
    }

    return window.setTimeout(callback, Math.min(timeout, 180));
};

const cancelDeferredStartupTask = (handle) => {
    if (typeof window === 'undefined' || !handle) {
        return;
    }

    if (typeof handle === 'number') {
        window.clearTimeout(handle);
        return;
    }

    if ('cancelIdleCallback' in window) {
        window.cancelIdleCallback(handle);
    }
};

const signalAppShellReady = (detail = {}) => {
    if (typeof window === 'undefined') {
        return false;
    }

    const startupState = getStartupState();
    const safeDetail = serializeDetail(detail);

    markStartupEvent('app_shell_ready', safeDetail);

    if (startupState.nativeReadySent) {
        return true;
    }

    if (window.ZulorsStartup && typeof window.ZulorsStartup.appShellReady === 'function') {
        try {
            window.ZulorsStartup.appShellReady(JSON.stringify(safeDetail));
            startupState.nativeReadySent = true;

            return true;
        }
        catch (error) {
            //
        }
    }

    return false;
};

export {
    cancelDeferredStartupTask,
    deferStartupTask,
    markStartupEvent,
    markStartupResponse,
    signalAppShellReady
};
