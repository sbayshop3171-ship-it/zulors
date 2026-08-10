import { Str } from '../../helpers/javascript/index.js';
import { EVENTS, EVENT_ALIASES, EVENT_GROUPS, EVENT_META, WHISPER_EVENT_KEYS } from './events.js';
import { CHANNELS, CHANNEL_ALIASES, CHANNEL_GROUPS, CHANNEL_META } from './channels.js';
import { CONNECTION_STATUS_EVENT, CONNECTION_STATES, createConnectionSnapshot } from './connection.js';
import {
    createRealtimeEventId,
    buildRealtimeEnvelope,
    buildAckPayload,
    buildUploadProgressPayload,
    createEventDeduper,
} from './reliability.js';
import {
    EMPTY_TYPING_STATE,
    createEmptyTypingState,
    createTypingSessionId,
    buildTypingPayload,
    normalizeTypingPayload,
    createIncomingTypingController,
    createOutgoingTypingController,
} from './typing.js';

const normalizeLookupKey = (key) => {
    if(typeof key !== 'string') {
        return null;
    }

    return key
        .trim()
        .replace(/[\s.-]+/g, '_')
        .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
        .toUpperCase();
};

const resolveCatalogKey = (catalog, aliases, key) => {
    if(typeof key === 'string') {
        const directMatch = Object.entries(catalog).find(([, value]) => value === key);

        if(directMatch) {
            return directMatch[0];
        }
    }

    const normalizedKey = normalizeLookupKey(key);

    if(! normalizedKey) {
        return null;
    }

    if(catalog[normalizedKey]) {
        return normalizedKey;
    }

    if(aliases[normalizedKey] && catalog[aliases[normalizedKey]]) {
        return aliases[normalizedKey];
    }

    return null;
};

const formatChannel = (channelTemplate, args = [], defaultVal = null) => {
    if(! channelTemplate) {
        return defaultVal;
    }

    return Str.make(channelTemplate).format(...args).value() ?? defaultVal;
};

const getEchoInstance = () => {
    return globalThis.window?.ColibriBRD ?? null;
};

const getEchoChannel = (channelMeta, channelName) => {
    const echo = getEchoInstance();

    if(! echo || ! channelMeta || ! channelName) {
        return null;
    }

    if(channelMeta.scope === 'private') {
        return echo.private(channelName);
    }

    if(channelMeta.scope === 'presence') {
        return echo.join(channelName);
    }

    return echo.channel(channelName);
};

const resolveEventKey = (key) => {
    return resolveCatalogKey(EVENTS, EVENT_ALIASES, key);
};

const resolveChannelKey = (key) => {
    return resolveCatalogKey(CHANNELS, CHANNEL_ALIASES, key);
};

const pickGroupEntries = (groupSource, catalog, groupKey) => {
    const normalizedGroupKey = String(groupKey || '').trim().toLowerCase();

    if(! groupSource[normalizedGroupKey]) {
        return {};
    }

    return Object.keys(groupSource[normalizedGroupKey]).reduce((carry, key) => {
        carry[key] = catalog[key];

        return carry;
    }, {});
};

const BRD = {
    events: EVENTS,
    channels: CHANNELS,
    eventGroups: EVENT_GROUPS,
    channelGroups: CHANNEL_GROUPS,
    connectionEvent: CONNECTION_STATUS_EVENT,
    connectionStates: CONNECTION_STATES,
    emptyTypingState: EMPTY_TYPING_STATE,
    getEvent(key, defaultVal = null) {
        const resolvedKey = resolveEventKey(key);

        return (resolvedKey ? EVENTS[resolvedKey] : defaultVal);
    },
    getChannel(key, args = [], defaultVal = null) {
        const resolvedKey = resolveChannelKey(key);

        return formatChannel((resolvedKey ? CHANNELS[resolvedKey] : null), args, defaultVal);
    },
    hasEvent(key) {
        return Boolean(resolveEventKey(key));
    },
    hasChannel(key) {
        return Boolean(resolveChannelKey(key));
    },
    getEventMeta(key) {
        const resolvedKey = resolveEventKey(key);

        return (resolvedKey ? EVENT_META[resolvedKey] : null);
    },
    getChannelMeta(key) {
        const resolvedKey = resolveChannelKey(key);

        return (resolvedKey ? CHANNEL_META[resolvedKey] : null);
    },
    getEventsByGroup(groupKey) {
        return pickGroupEntries(EVENT_GROUPS, EVENTS, groupKey);
    },
    getChannelsByGroup(groupKey) {
        return pickGroupEntries(CHANNEL_GROUPS, CHANNELS, groupKey);
    },
    listEventKeys(groupKey = null) {
        if(groupKey) {
            return Object.keys(this.getEventsByGroup(groupKey));
        }

        return Object.keys(EVENTS);
    },
    listChannelKeys(groupKey = null) {
        if(groupKey) {
            return Object.keys(this.getChannelsByGroup(groupKey));
        }

        return Object.keys(CHANNELS);
    },
    isWhisperEvent(key) {
        const resolvedKey = resolveEventKey(key);

        return Boolean(resolvedKey && WHISPER_EVENT_KEYS.has(resolvedKey));
    },
    getConnectionSnapshot() {
        return globalThis.window?.ColibriBRState ?? createConnectionSnapshot({
            connected: globalThis.window?.ColibriBRConnected === true,
        });
    },
    publicChannel(channelKey, args = []) {
        const channelMeta = this.getChannelMeta(channelKey);
        const channelName = this.getChannel(channelKey, args);

        return getEchoChannel({
            ...channelMeta,
            scope: 'public'
        }, channelName);
    },
    privateChannel(channelKey, args = []) {
        const channelMeta = this.getChannelMeta(channelKey);
        const channelName = this.getChannel(channelKey, args);

        return getEchoChannel({
            ...channelMeta,
            scope: 'private'
        }, channelName);
    },
    presenceChannel(channelKey, args = []) {
        const channelMeta = this.getChannelMeta(channelKey);
        const channelName = this.getChannel(channelKey, args);

        return getEchoChannel({
            ...channelMeta,
            scope: 'presence'
        }, channelName);
    },
    listen(channelKey, args = [], eventKey, callback, options = {}) {
        const channelMeta = this.getChannelMeta(channelKey);
        const channelName = this.getChannel(channelKey, args);
        const eventName = this.getEvent(eventKey);
        const resolvedScope = options.scope ?? channelMeta?.scope ?? 'public';
        const channel = getEchoChannel({
            ...channelMeta,
            scope: resolvedScope
        }, channelName);

        if(channel && eventName) {
            channel.listen(eventName, callback);
        }

        return channel;
    },
    stopListening(channelKey, args = [], eventKey, callback = null, options = {}) {
        const channelMeta = this.getChannelMeta(channelKey);
        const channelName = this.getChannel(channelKey, args);
        const eventName = this.getEvent(eventKey);
        const resolvedScope = options.scope ?? channelMeta?.scope ?? 'public';
        const channel = getEchoChannel({
            ...channelMeta,
            scope: resolvedScope
        }, channelName);

        if(channel && eventName) {
            channel.stopListening(eventName, callback ?? undefined);
        }

        return channel;
    },
    listenForWhisper(channelKey, args = [], eventKey, callback, options = {}) {
        const channel = this.privateChannel(channelKey, args);
        const eventName = this.getEvent(eventKey);

        if(channel && eventName) {
            channel.listenForWhisper(eventName, callback);
        }

        return channel;
    },
    stopListeningForWhisper(channelKey, args = [], eventKey) {
        const channel = this.privateChannel(channelKey, args);
        const eventName = this.getEvent(eventKey);

        if(channel && eventName) {
            channel.stopListeningForWhisper(eventName);
        }

        return channel;
    },
    whisper(channelKey, args = [], eventKey, payload) {
        const channel = this.privateChannel(channelKey, args);
        const eventName = this.getEvent(eventKey);

        if(channel && eventName) {
            channel.whisper(eventName, payload);
        }

        return channel;
    },
    createRealtimeEventId: createRealtimeEventId,
    buildRealtimeEnvelope: buildRealtimeEnvelope,
    buildAckPayload: buildAckPayload,
    buildUploadProgressPayload: buildUploadProgressPayload,
    createEventDeduper: createEventDeduper,
    createTypingSessionId: createTypingSessionId,
    createEmptyTypingState: createEmptyTypingState,
    buildTypingPayload: buildTypingPayload,
    normalizeTypingPayload: normalizeTypingPayload,
    createIncomingTypingController: createIncomingTypingController,
    createOutgoingTypingController: createOutgoingTypingController,
};

export default BRD;
