const clampNumber = (value, min, max, fallback = min) => {
    const numericValue = Number(value);

    if(! Number.isFinite(numericValue)) {
        return fallback;
    }

    return Math.min(max, Math.max(min, numericValue));
};

const now = () => {
    return Date.now();
};

const createRealtimeEventId = () => {
    if(globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    return `evt_${now()}_${Math.random().toString(36).slice(2, 10)}`;
};

const buildRealtimeEnvelope = (data = {}, meta = {}) => {
    return {
        data: data,
        meta: {
            event_id: meta.event_id ?? createRealtimeEventId(),
            sent_at: meta.sent_at ?? now(),
            source: meta.source ?? 'websocket',
            optimistic: Boolean(meta.optimistic),
            ...meta
        }
    };
};

const buildAckPayload = ({
    entity_id = null,
    entity_type = 'message',
    status = 'received',
    user_id = null,
    device_id = null,
    ...extra
} = {}) => {
    return buildRealtimeEnvelope({
        entity_id: entity_id,
        entity_type: entity_type,
        status: status,
        user_id: user_id,
        device_id: device_id,
        ...extra
    }, {
        source: 'ack'
    });
};

const buildUploadProgressPayload = ({
    upload_id = null,
    progress = 0,
    status = 'started',
    media_type = 'file',
    ...extra
} = {}) => {
    return buildRealtimeEnvelope({
        upload_id: upload_id,
        progress: clampNumber(progress, 0, 100, 0),
        status: status,
        media_type: media_type,
        ...extra
    }, {
        source: 'upload'
    });
};

const defaultFingerprint = (payload) => {
    if(payload?.meta?.event_id) {
        return payload.meta.event_id;
    }

    if(payload?.event_id) {
        return payload.event_id;
    }

    if(payload?.data?.event_id) {
        return payload.data.event_id;
    }

    if(payload?.data?.message_id) {
        return `message:${payload.data.message_id}`;
    }

    if(payload?.data?.post_id) {
        return `post:${payload.data.post_id}`;
    }

    return JSON.stringify(payload ?? {});
};

const createEventDeduper = (options = {}) => {
    const ttlMs = Math.max(Number(options.ttlMs ?? 120000), 1000);
    const fingerprint = options.fingerprint ?? defaultFingerprint;
    const registry = new Map();

    const prune = () => {
        const currentTime = now();

        registry.forEach((expiresAt, key) => {
            if(expiresAt <= currentTime) {
                registry.delete(key);
            }
        });
    };

    const remember = (payload) => {
        prune();

        const key = fingerprint(payload);

        registry.set(key, now() + ttlMs);

        return key;
    };

    return {
        has(payload) {
            prune();

            return registry.has(fingerprint(payload));
        },
        add(payload) {
            return remember(payload);
        },
        seen(payload) {
            const key = fingerprint(payload);

            prune();

            if(registry.has(key)) {
                return true;
            }

            registry.set(key, now() + ttlMs);

            return false;
        },
        clear() {
            registry.clear();
        },
        size() {
            prune();

            return registry.size;
        }
    };
};

export {
    createRealtimeEventId,
    buildRealtimeEnvelope,
    buildAckPayload,
    buildUploadProgressPayload,
    createEventDeduper,
};
