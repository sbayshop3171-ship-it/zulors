const SAMPLE_KEY = 'zulors.performance.sample.v1';
const ALLOWED_METRICS = new Set([
    'lcp',
    'cls',
    'inp',
    'route',
    'api_slow',
    'startup_shell',
    'startup_visual',
    'first_input'
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

    if (details.route && /^[a-z0-9_]{1,60}$/.test(details.route)) {
        event.route = details.route;
    }

    if (details.category && ALLOWED_CATEGORIES.has(details.category)) {
        event.category = details.category;
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
    router.beforeEach(() => {
        routeStartedAt = performance.now();
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
        const category = segments[prefixIndex >= 0 ? prefixIndex + prefixSegments.length : 0];

        return ALLOWED_CATEGORIES.has(category) ? category : 'api';
    }
    catch (error) {
        return 'api';
    }
}

function reportApiDuration(config, startedAt) {
    const duration = performance.now() - Number(startedAt || performance.now());

    if (duration >= 750 && !String(config?.url || '').includes('/performance/events')) {
        queueMetric('api_slow', duration, { category: getApiCategory(config) });
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
