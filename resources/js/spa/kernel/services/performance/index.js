const SAMPLE_KEY = 'zulors.performance.sample.v1';
const ALLOWED_METRICS = new Set([
    'lcp',
    'cls',
    'inp',
    'route',
    'api_slow',
    'api_duration',
    'startup_shell',
    'startup_visual',
    'first_input',
    'long_task',
    'media_first_frame',
    'upload_start',
    'media_play_request',
    'media_stall',
    'media_recovery',
    'media_fallback',
    'media_terminal_error',
    'media_rendition'
]);
const ALLOWED_CATEGORIES = new Set([
    'bootstrap',
    'timeline',
    'explore',
    'profile',
    'messenger',
    'notifications',
    'stories',
    'media-publications',
    'uploads',
    'downloads',
    'calls',
    'api'
]);

let installed = false;
let sampled = false;
let pendingEvents = [];
let flushTimer = 0;
let latestLcp = 0;
let clsSessionValue = 0;
let clsSessionStartedAt = 0;
let clsLastShiftAt = 0;
let maximumClsSession = 0;
const interactionDurations = new Map();
let firstInputDelay = 0;
let routeStartedAt = null;
let activeRouteName = '';
let runtimeContext = null;

function readRuntimeContext() {
    if (runtimeContext) {
        return runtimeContext;
    }

    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    let nativeContext = {};

    try {
        const rawContext = window.ZulorsStartup?.performanceContext?.();
        nativeContext = rawContext ? JSON.parse(rawContext) : {};
    }
    catch (error) {
        nativeContext = {};
    }

    const deviceMemory = Number(navigator.deviceMemory || 0);
    const deviceMemoryBucket = !deviceMemory
        ? 'unknown'
        : (deviceMemory <= 1 ? '1' : (deviceMemory <= 2 ? '2' : (deviceMemory <= 4 ? '4' : '8p')));
    const effectiveType = navigator.onLine === false
        ? 'offline'
        : String(connection?.effectiveType || 'unknown').toLowerCase();

    runtimeContext = {
        device_memory: ['1', '2', '4', '8p'].includes(deviceMemoryBucket) ? deviceMemoryBucket : 'unknown',
        network: ['offline', 'slow-2g', '2g', '3g', '4g'].includes(effectiveType) ? effectiveType : 'unknown',
        android_api: Number(nativeContext.android_api || 0),
        low_ram: typeof nativeContext.low_ram === 'boolean' ? nativeContext.low_ram : null
    };

    return runtimeContext;
}

function isSampledSession() {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        const cachedSample = window.sessionStorage.getItem(SAMPLE_KEY);

        if (cachedSample === '1' || cachedSample === '0') {
            return cachedSample === '1';
        }

        const shouldSample = import.meta.env.DEV || Math.random() < 0.05;
        window.sessionStorage.setItem(SAMPLE_KEY, shouldSample ? '1' : '0');

        return shouldSample;
    }
    catch (error) {
        return import.meta.env.DEV || Math.random() < 0.05;
    }
}

function roundMetricValue(value) {
    return Math.round(Number(value) * 1000) / 1000;
}

function queueMetric(metric, value, details = {}) {
    if (!installed || !sampled || !ALLOWED_METRICS.has(metric)) {
        return;
    }

    const numericValue = Number(value);

    if (!Number.isFinite(numericValue) || numericValue < 0) {
        return;
    }

    const event = {
        metric,
        value: roundMetricValue(numericValue),
        variant: document.documentElement.classList.contains('zulors-android-app') || /Android|LocalApp/.test(navigator.userAgent)
            ? 'mobile'
            : (document.documentElement.clientWidth < 768 ? 'mobile' : 'desktop')
    };
    const context = readRuntimeContext();

    if (context.device_memory !== 'unknown') {
        event.device_memory = context.device_memory;
    }

    if (context.network !== 'unknown') {
        event.network = context.network;
    }

    if (context.android_api >= 24 && context.android_api <= 99) {
        event.android_api = context.android_api;
    }

    if (context.low_ram !== null) {
        event.low_ram = context.low_ram;
    }

    if (details.route && /^[a-z0-9_]{1,60}$/.test(details.route)) {
        event.route = details.route;
    }
    else if (activeRouteName && /^[a-z0-9_]{1,60}$/.test(activeRouteName)) {
        event.route = activeRouteName;
    }

    const serverDuration = Number(details.server_ms);
    if (Number.isFinite(serverDuration) && serverDuration >= 0 && serverDuration <= 600000) {
        event.server_ms = roundMetricValue(serverDuration);
    }

    if (details.category && ALLOWED_CATEGORIES.has(details.category)) {
        event.category = details.category;
    }

    const rendition = String(details.rendition || '');
    if (['hls', 'mp4', 'direct', '240p', '360p', '480p', '720p'].includes(rendition)) {
        event.rendition = rendition;
    }

    pendingEvents.push(event);

    if (pendingEvents.length >= 10) {
        flushMetrics();
    }
    else if (!flushTimer) {
        flushTimer = window.setTimeout(() => {
            flushTimer = 0;
            flushMetrics();
        }, 15000);
    }
}

function telemetryUrl() {
    const apiPrefix = String(import.meta.env.VITE_APP_API_PREFIX || 'api').replace(/^\/+|\/+$/g, '');
    return `${window.location.origin}/${apiPrefix}/performance/events`;
}

function flushMetrics() {
    if (!pendingEvents.length || typeof window === 'undefined') {
        return;
    }

    const events = pendingEvents.splice(0, 20);
    const body = JSON.stringify({ events });

    if (navigator.sendBeacon) {
        const sent = navigator.sendBeacon(telemetryUrl(), new Blob([body], { type: 'application/json' }));

        if (sent) {
            return;
        }
    }

    fetch(telemetryUrl(), {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body
    }).catch(() => {});
}

function observePerformanceEntries() {
    if (typeof PerformanceObserver !== 'function') {
        return;
    }

    const supportedEntryTypes = PerformanceObserver.supportedEntryTypes || [];

    const observe = (type, callback, options = {}) => {
        try {
            const observer = new PerformanceObserver((list) => {
                callback(list.getEntries());
            });
            observer.observe({ type, buffered: true, ...options });
        }
        catch (error) {
            // Older Android System WebView versions may not support every entry type.
        }
    };

    observe('largest-contentful-paint', (entries) => {
        for (const entry of entries) {
            latestLcp = Math.max(latestLcp, entry.startTime || 0);
        }
    });

    observe('layout-shift', (entries) => {
        for (const entry of entries) {
            if (!entry.hadRecentInput) {
                const shiftAt = entry.startTime || 0;

                if (!clsSessionStartedAt || shiftAt - clsLastShiftAt > 1000 || shiftAt - clsSessionStartedAt > 5000) {
                    clsSessionValue = 0;
                    clsSessionStartedAt = shiftAt;
                }

                clsSessionValue += entry.value || 0;
                clsLastShiftAt = shiftAt;
                maximumClsSession = Math.max(maximumClsSession, clsSessionValue);
            }
        }
    });

    if (supportedEntryTypes.includes('event')) {
        observe('event', (entries) => {
            for (const entry of entries) {
                if (entry.interactionId && entry.duration) {
                    interactionDurations.set(
                        entry.interactionId,
                        Math.max(interactionDurations.get(entry.interactionId) || 0, entry.duration)
                    );
                }
            }
        }, { durationThreshold: 16 });
    }
    else {
        observe('first-input', (entries) => {
            for (const entry of entries) {
                if (entry.duration) {
                    firstInputDelay = Math.max(firstInputDelay, entry.duration);
                }
            }
        });
    }

    observe('longtask', (entries) => {
        for (const entry of entries) {
            if (entry.duration >= 50) {
                queueMetric('long_task', entry.duration, { route: activeRouteName });
            }
        }
    });
}

function observeMediaStartup() {
    if (typeof document === 'undefined' || typeof HTMLVideoElement === 'undefined') {
        return;
    }

    const playbackStartedAt = new WeakMap();
    const firstFrameReported = new WeakSet();

    const reportFirstFrame = (videoElement) => {
        if(firstFrameReported.has(videoElement)) {
            return;
        }

        const startedAt = playbackStartedAt.get(videoElement);

        if(Number.isFinite(startedAt)) {
            playbackStartedAt.delete(videoElement);
            firstFrameReported.add(videoElement);
            queueMetric('media_first_frame', performance.now() - startedAt, {
                route: activeRouteName,
                category: activeRouteName === 'explore_reels' ? 'explore' : 'timeline'
            });
        }
    };

    document.addEventListener('play', (event) => {
        if (event.target instanceof HTMLVideoElement && !firstFrameReported.has(event.target)) {
            playbackStartedAt.set(event.target, performance.now());
        }
    }, true);

    document.addEventListener('playing', (event) => {
        if (!(event.target instanceof HTMLVideoElement)) {
            return;
        }

        if (typeof event.target.requestVideoFrameCallback === 'function') {
            event.target.requestVideoFrameCallback(() => reportFirstFrame(event.target));
        }
        else {
            reportFirstFrame(event.target);
        }
    }, true);
}

function reportPageVitals() {
    if (latestLcp > 0) {
        queueMetric('lcp', latestLcp);
        latestLcp = 0;
    }

    if (maximumClsSession > 0) {
        queueMetric('cls', maximumClsSession);
        maximumClsSession = 0;
        clsSessionValue = 0;
        clsSessionStartedAt = 0;
        clsLastShiftAt = 0;
    }

    if (interactionDurations.size) {
        const orderedInteractions = [...interactionDurations.values()].sort((a, b) => b - a);
        const p98Index = Math.min(Math.floor((orderedInteractions.length - 1) / 50), orderedInteractions.length - 1);
        queueMetric('inp', orderedInteractions[p98Index]);
        interactionDurations.clear();
    }

    if (firstInputDelay > 0) {
        queueMetric('first_input', firstInputDelay);
        firstInputDelay = 0;
    }

    flushMetrics();
}

function installRouterPerformance(router) {
    router.beforeEach((to) => {
        routeStartedAt = performance.now();
        activeRouteName = typeof to.name === 'string' ? to.name : '';
    });

    router.afterEach((to, from, failure) => {
        if (failure || routeStartedAt === null) {
            return;
        }

        const startedAt = routeStartedAt;
        routeStartedAt = null;

        requestAnimationFrame(() => requestAnimationFrame(() => {
            queueMetric('route', performance.now() - startedAt, {
                route: typeof to.name === 'string' ? to.name : ''
            });
        }));
    });
}

function getApiCategory(config) {
    try {
        const requestUrl = new URL(config?.url || '', config?.baseURL || window.location.origin);
        const segments = requestUrl.pathname.split('/').filter(Boolean);
        const prefixSegments = String(import.meta.env.VITE_APP_API_PREFIX || 'api').split('/').filter(Boolean);
        const prefixIndex = segments.findIndex((segment, index) => prefixSegments.every((prefixSegment, offset) => segments[index + offset] === prefixSegment));
        const apiSegments = segments.slice(prefixIndex >= 0 ? prefixIndex + prefixSegments.length : 0);
        const categoryAliases = new Map([
            ['bootstrap', 'bootstrap'],
            ['timeline', 'timeline'],
            ['feed', 'timeline'],
            ['explore', 'explore'],
            ['search', 'explore'],
            ['profile', 'profile'],
            ['messenger', 'messenger'],
            ['calls', 'calls'],
            ['uploads', 'uploads'],
            ['upload', 'uploads'],
            ['publications', 'media-publications']
        ]);
        const category = apiSegments
            .map((segment) => categoryAliases.get(segment))
            .find((candidate) => candidate && ALLOWED_CATEGORIES.has(candidate));

        return category || 'api';
    }
    catch (error) {
        return 'api';
    }
}

function parseServerTimingDuration(headers) {
    const serverTiming = typeof headers?.get === 'function'
        ? headers.get('server-timing')
        : (headers?.['server-timing'] || headers?.['Server-Timing']);

    if (typeof serverTiming !== 'string') {
        return 0;
    }

    const durations = [...serverTiming.matchAll(/(?:^|,)\s*([a-z0-9_-]+)\s*;\s*dur=([\d.]+)/gi)]
        .map((match) => ({ name: match[1].toLowerCase(), duration: Number(match[2]) }))
        .filter(({ duration }) => Number.isFinite(duration) && duration >= 0);
    const totalDuration = durations.find(({ name }) => name === 'app');

    if (totalDuration) {
        return totalDuration.duration;
    }

    return durations.length ? Math.max(...durations.map(({ duration }) => duration)) : 0;
}

function reportApiDuration(config, startedAt, headers = null) {
    const duration = performance.now() - Number(startedAt || performance.now());
    const serverDuration = parseServerTimingDuration(headers);

    if (!String(config?.url || '').includes('/performance/events')) {
        queueMetric('api_duration', duration, {
            category: getApiCategory(config),
            server_ms: serverDuration
        });
    }

    if ((duration >= 750 || serverDuration >= 250) && !String(config?.url || '').includes('/performance/events')) {
        queueMetric('api_slow', duration, {
            category: getApiCategory(config),
            server_ms: serverDuration
        });
    }
}

function installPerformanceMonitoring() {
    if (installed || typeof window === 'undefined') {
        return;
    }

    installed = true;
    sampled = isSampledSession();

    if (!sampled) {
        return;
    }

    observePerformanceEntries();
    observeMediaStartup();
    window.addEventListener('zulors:startup', (event) => {
        const detail = event.detail || {};
        const startupMetric = detail.name === 'app_shell_ready'
            ? 'startup_shell'
            : (detail.name === 'first_visual_ready' ? 'startup_visual' : null);

        if (!startupMetric) {
            return;
        }

        const startedAt = Number(window.__zulorsStartup?.perfStartedAt);
        const markedAt = Number(detail.at);

        if (Number.isFinite(startedAt) && Number.isFinite(markedAt) && markedAt >= startedAt) {
            queueMetric(startupMetric, markedAt - startedAt, { route: detail.route });
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            reportPageVitals();
        }
    });
    window.addEventListener('pagehide', reportPageVitals);
}

export {
    installPerformanceMonitoring,
    installRouterPerformance,
    queueMetric,
    reportApiDuration
};
