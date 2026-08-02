import { computed, onMounted, onUnmounted, watch } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const telemetryQueue = [];
const maxBatchSize = 20;
const flushDelayMs = 1500;
const impressionDelayMs = 800;
const minDwellMs = 500;

let flushTimer = null;
let isFlushing = false;

function unwrap(value) {
    return value && typeof value === 'object' && 'value' in value ? value.value : value;
}

function scheduleFlush() {
    if(flushTimer) {
        return;
    }

    flushTimer = window.setTimeout(flushTelemetryQueue, flushDelayMs);
}

function enqueueTelemetryEvent(eventData) {
    telemetryQueue.push(eventData);

    if(telemetryQueue.length >= maxBatchSize) {
        flushTelemetryQueue();
    }
    else {
        scheduleFlush();
    }
}

function flushTelemetryQueue() {
    if(flushTimer) {
        window.clearTimeout(flushTimer);
        flushTimer = null;
    }

    if(isFlushing || ! telemetryQueue.length) {
        return;
    }

    isFlushing = true;
    const events = telemetryQueue.splice(0, maxBatchSize);

    colibriAPI().userTimeline().with({
        events: events
    }).sendTo('telemetry/events').catch(() => {
        // Telemetry is best-effort and should never interrupt reading the feed.
    }).finally(() => {
        isFlushing = false;

        if(telemetryQueue.length) {
            scheduleFlush();
        }
    });
}

export function useFeedPostTelemetry(options = {}) {
    const targetRef = options.targetRef;
    const postData = options.postData;
    const feedSessionId = options.feedSessionId;
    const feedType = options.feedType;
    const position = options.position;
    const source = options.source;
    const refreshReason = options.refreshReason;

    let observer = null;
    let visibleStartedAt = null;
    let visibleMs = 0;
    let impressionTimer = null;
    let impressionSent = false;
    let lastViewportRatio = 0;

    const currentPost = computed(() => unwrap(postData) || {});
    const trackingEnabled = computed(() => {
        return Boolean(
            currentPost.value?.id &&
            unwrap(feedSessionId) &&
            ! currentPost.value?.meta?.is_optimistic
        );
    });

    const payload = function(eventType, extra = {}) {
        return {
            event_type: eventType,
            post_id: currentPost.value.id,
            session_id: unwrap(feedSessionId),
            feed_type: unwrap(feedType) || 'for_you',
            source: unwrap(source) || 'timeline',
            position: Number(unwrap(position) || 0),
            refresh_reason: unwrap(refreshReason) || null,
            viewport_ratio: Math.round(lastViewportRatio * 1000) / 1000,
            ...extra
        };
    };

    const sendEvent = function(eventType, extra = {}) {
        if(! trackingEnabled.value) {
            return;
        }

        enqueueTelemetryEvent(payload(eventType, extra));
    };

    const clearImpressionTimer = function() {
        if(impressionTimer) {
            window.clearTimeout(impressionTimer);
            impressionTimer = null;
        }
    };

    const collectVisibleTime = function() {
        if(visibleStartedAt) {
            visibleMs += Date.now() - visibleStartedAt;
            visibleStartedAt = null;
        }
    };

    const scheduleImpression = function() {
        clearImpressionTimer();

        impressionTimer = window.setTimeout(() => {
            if(visibleStartedAt && ! impressionSent) {
                impressionSent = true;
                sendEvent('post_impression', {
                    visible_ms: Date.now() - visibleStartedAt
                });
            }
        }, impressionDelayMs);
    };

    const startVisibility = function() {
        if(visibleStartedAt || ! trackingEnabled.value) {
            return;
        }

        visibleStartedAt = Date.now();
        scheduleImpression();
    };

    const stopVisibility = function() {
        collectVisibleTime();
        clearImpressionTimer();

        if(visibleMs < minDwellMs || ! trackingEnabled.value) {
            visibleMs = 0;
            return;
        }

        const dwellSeconds = Math.round((visibleMs / 1000) * 10) / 10;

        sendEvent(dwellSeconds < 2 ? 'post_quick_skip' : 'post_dwell', {
            dwell_time_seconds: dwellSeconds,
            visible_ms: Math.round(visibleMs)
        });

        visibleMs = 0;
    };

    const disconnectObserver = function() {
        if(observer) {
            observer.disconnect();
            observer = null;
        }
    };

    const observeTarget = function() {
        disconnectObserver();

        if(! targetRef?.value || ! trackingEnabled.value) {
            return;
        }

        observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                lastViewportRatio = entry.intersectionRatio || 0;

                if(entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                    startVisibility();
                }
                else {
                    stopVisibility();
                }
            });
        }, {
            threshold: [0, 0.5, 0.75],
            rootMargin: '0px'
        });

        observer.observe(targetRef.value);
    };

    const resetPostState = function() {
        stopVisibility();
        clearImpressionTimer();
        visibleStartedAt = null;
        visibleMs = 0;
        impressionSent = false;
        lastViewportRatio = 0;
    };

    const handleVisibilityChange = function() {
        if(document.visibilityState === 'hidden') {
            stopVisibility();
            flushTelemetryQueue();
        }
    };

    onMounted(() => {
        observeTarget();
        document.addEventListener('visibilitychange', handleVisibilityChange);
    });

    onUnmounted(() => {
        stopVisibility();
        flushTelemetryQueue();
        clearImpressionTimer();
        disconnectObserver();
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    });

    watch(() => currentPost.value?.id, () => {
        resetPostState();
        observeTarget();
    });

    return {
        flushTelemetryQueue
    };
}
