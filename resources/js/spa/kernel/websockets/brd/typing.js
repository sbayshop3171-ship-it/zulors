const EMPTY_TYPING_STATE = Object.freeze({
    is_typing: false,
    user: null,
    session_id: null,
    sent_at: null,
    ttl_ms: null,
    expires_at: null,
});

const now = () => {
    return Date.now();
};

const createTypingSessionId = () => {
    if(globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    return `typing_${now()}_${Math.random().toString(36).slice(2, 10)}`;
};

const createEmptyTypingState = () => {
    return {
        ...EMPTY_TYPING_STATE
    };
};

const clampPositive = (value, fallback) => {
    const numericValue = Number(value);

    if(! Number.isFinite(numericValue) || numericValue <= 0) {
        return fallback;
    }

    return numericValue;
};

const buildTypingPayload = ({
    user = null,
    is_typing = false,
    session_id = null,
    sent_at = null,
    ttl_ms = 3500,
} = {}) => {
    const normalizedTtlMs = clampPositive(ttl_ms, 3500);
    const normalizedSentAt = clampPositive(sent_at, now());

    return {
        data: {
            user: user,
            is_typing: Boolean(is_typing),
            session_id: session_id ?? createTypingSessionId(),
            sent_at: normalizedSentAt,
            ttl_ms: normalizedTtlMs,
        }
    };
};

const normalizeTypingPayload = (payload = {}, fallbackTtlMs = 3500) => {
    const eventData = payload?.data ?? payload ?? {};
    const normalizedTtlMs = clampPositive(eventData.ttl_ms, fallbackTtlMs);
    const normalizedSentAt = clampPositive(eventData.sent_at, now());

    return {
        user: eventData.user ?? null,
        is_typing: Boolean(eventData.is_typing),
        session_id: eventData.session_id ?? null,
        sent_at: normalizedSentAt,
        ttl_ms: normalizedTtlMs,
        expires_at: normalizedSentAt + normalizedTtlMs,
    };
};

const createIncomingTypingController = (onChange, options = {}) => {
    const fallbackTtlMs = clampPositive(options.ttlMs ?? options.idleMs, 3500);
    const timerHost = globalThis.window ?? globalThis;
    let expiryTimer = null;

    const apply = (nextState) => {
        const resolvedState = nextState ?? createEmptyTypingState();

        if(typeof onChange === 'function') {
            onChange(resolvedState);
        }

        return resolvedState;
    };

    const reset = () => {
        if(expiryTimer) {
            timerHost.clearTimeout(expiryTimer);
            expiryTimer = null;
        }

        return apply(createEmptyTypingState());
    };

    return {
        receive(payload) {
            const nextState = normalizeTypingPayload(payload, fallbackTtlMs);

            if(expiryTimer) {
                timerHost.clearTimeout(expiryTimer);
                expiryTimer = null;
            }

            if(! nextState.is_typing) {
                return reset();
            }

            apply(nextState);

            expiryTimer = timerHost.setTimeout(() => {
                reset();
            }, nextState.ttl_ms);

            return nextState;
        },
        reset: reset,
        stop: reset
    };
};

const createOutgoingTypingController = (dispatch, options = {}) => {
    const idleMs = clampPositive(options.idleMs, 1000);
    const ttlMs = clampPositive(options.ttlMs, 3500);
    const sessionId = options.session_id ?? createTypingSessionId();
    const timerHost = globalThis.window ?? globalThis;
    let stopTimer = null;
    let isActive = false;

    const emit = (user, isTyping) => {
        if(typeof dispatch !== 'function') {
            return false;
        }

        dispatch(buildTypingPayload({
            user: user,
            is_typing: isTyping,
            session_id: sessionId,
            ttl_ms: ttlMs,
        }));

        isActive = Boolean(isTyping);

        return true;
    };

    const clearStopTimer = () => {
        if(stopTimer) {
            timerHost.clearTimeout(stopTimer);
            stopTimer = null;
        }
    };

    return {
        bump(user = null) {
            if(! isActive) {
                emit(user, true);
            }

            clearStopTimer();

            stopTimer = timerHost.setTimeout(() => {
                emit(user, false);
            }, idleMs);
        },
        stop(user = null, options = {}) {
            clearStopTimer();

            if(options.silent) {
                isActive = false;
                return false;
            }

            if(! isActive) {
                return false;
            }

            return emit(user, false);
        },
        flush(user = null) {
            clearStopTimer();

            return emit(user, false);
        }
    };
};

export {
    EMPTY_TYPING_STATE,
    createEmptyTypingState,
    createTypingSessionId,
    buildTypingPayload,
    normalizeTypingPayload,
    createIncomingTypingController,
    createOutgoingTypingController,
};
