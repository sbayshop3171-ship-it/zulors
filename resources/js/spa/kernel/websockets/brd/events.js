const EVENT_GROUPS = Object.freeze({
    presence: Object.freeze({
        USER_ONLINE: '.user.online',
        USER_OFFLINE: '.user.offline',
        USER_LAST_SEEN_UPDATED: '.user.last-seen.updated',
        USER_ACTIVE_NOW: '.user.active-now',
        FRIEND_PRESENCE_UPDATED: '.friend.presence.updated',
        CONVERSATION_PRESENCE_UPDATED: '.conversation.presence.updated',
    }),
    chat: Object.freeze({
        CHAT_MESSAGE_SENT: '.chat.message.sent',
        CHAT_MESSAGE_RECEIVED: '.chat.message.received',
        CHAT_MESSAGE_DELIVERED: '.chat.message.delivered',
        CHAT_MESSAGE_READ: '.chat.message.read',
        CHAT_MESSAGE_EDITED: '.chat.message.edited',
        CHAT_MESSAGE_DELETED: '.chat.message.deleted',
        CHAT_MESSAGE_REACTION_ADDED: '.chat.message.reactions.updated',
        CHAT_MESSAGE_REACTION_REMOVED: '.chat.message.reactions.updated',
        CHAT_MESSAGE_REACTIONS_UPDATED: '.chat.message.reactions.updated',
        CHAT_MESSAGE_TYPING: '.chat.message.typing',
        CHAT_MESSAGE_TYPING_START: '.chat.message.typing',
        CHAT_MESSAGE_TYPING_STOP: '.chat.message.typing',
        CHAT_REPLY_CREATED: '.chat.reply.created',
        CHAT_REPLY_REMOVED: '.chat.reply.removed',
        CHAT_MESSAGE_FORWARDED: '.chat.message.forwarded',
    }),
    uploads: Object.freeze({
        CHAT_UPLOAD_STARTED: '.chat.upload.started',
        CHAT_UPLOAD_PROGRESS: '.chat.upload.progress',
        CHAT_UPLOAD_COMPLETED: '.chat.upload.completed',
        CHAT_UPLOAD_FAILED: '.chat.upload.failed',
        CHAT_MEDIA_PROCESSED: '.chat.media.processed',
        CHAT_MEDIA_READY: '.chat.media.ready',
    }),
    calls: Object.freeze({
        CALL_INCOMING: '.call.incoming',
        CALL_ANSWERED: '.call.answered',
        CALL_DECLINED: '.call.declined',
        CALL_ENDED: '.call.ended',
        CALL_BUSY: '.call.busy',
        CALL_SIGNAL: '.call.signal',
        CALL_RINGING: '.call.ringing',
        CALL_ACCEPTED: '.call.accepted',
    }),
    timeline: Object.freeze({
        TIMELINE_MEDIA_PROCESSED: '.timeline.media.processed',
        TIMELINE_MEDIA_UPDATED: '.timeline.media.updated',
        TIMELINE_POST_CREATED: '.timeline.post.created',
        TIMELINE_POST_UPDATED: '.timeline.post.updated',
        POST_REACTION_UPDATED: '.timeline.post.reaction.updated',
        POST_COMMENT_COUNT_UPDATED: '.timeline.post.comment-count.updated',
        TIMELINE_FEED_REFRESHED: '.timeline.feed.refreshed',
    }),
    stories: Object.freeze({
        STORY_VIEWED: '.story.viewed',
        STORY_REACTED: '.story.reacted',
        STORY_LIKED: '.story.liked',
        STORY_EXPIRED: '.story.expired',
    }),
    reels: Object.freeze({
        REEL_VIEW_PROGRESS: '.reel.view.progress',
        REEL_COMPLETED: '.reel.completed',
        REEL_NOT_INTERESTED: '.reel.not-interested',
        REEL_REACTION_UPDATED: '.reel.reaction.updated',
    }),
    social: Object.freeze({
        FOLLOW_CREATED: '.follow.created',
        FOLLOW_ACCEPTED: '.follow.accepted',
        FOLLOW_REQUEST_RECEIVED: '.follow.request.received',
        FOLLOW_REQUEST_CANCELLED: '.follow.request.cancelled',
    }),
    notifications: Object.freeze({
        NOTIFICATION_CREATED: '.notification.created',
        NOTIFICATION_READ: '.notification.read',
        NOTIFICATION_BADGE_UPDATED: '.notification.badge.updated',
        PUSH_FALLBACK_REQUIRED: '.notification.push-fallback.required',
    }),
    sync: Object.freeze({
        UNREAD_COUNT_UPDATED: '.sync.unread-count.updated',
        INBOX_ORDER_UPDATED: '.sync.inbox-order.updated',
        LAST_MESSAGE_PREVIEW_UPDATED: '.sync.last-message-preview.updated',
        PIN_STATE_UPDATED: '.sync.pin-state.updated',
        MUTE_STATE_UPDATED: '.sync.mute-state.updated',
        ARCHIVE_STATE_UPDATED: '.sync.archive-state.updated',
        MULTI_DEVICE_SYNC: '.sync.multi-device.updated',
    }),
    telemetry: Object.freeze({
        WEBSOCKET_DROPPED_EVENT: '.telemetry.websocket.dropped-event',
        WEBSOCKET_RECONNECT_RATE_UPDATED: '.telemetry.websocket.reconnect-rate.updated',
        DELIVERY_LATENCY_UPDATED: '.telemetry.delivery-latency.updated',
    })
});

const ACTIVE_EVENT_KEYS = new Set([
    'TIMELINE_MEDIA_PROCESSED',
    'TIMELINE_MEDIA_UPDATED',
    'TIMELINE_POST_CREATED',
    'TIMELINE_POST_UPDATED',
    'CHAT_MESSAGE_RECEIVED',
    'CHAT_MESSAGE_DELETED',
    'CHAT_MESSAGE_REACTIONS_UPDATED',
    'CHAT_MESSAGE_REACTION_ADDED',
    'CHAT_MESSAGE_REACTION_REMOVED',
    'CHAT_MESSAGE_READ',
    'CHAT_MESSAGE_TYPING',
    'CHAT_MESSAGE_TYPING_START',
    'CHAT_MESSAGE_TYPING_STOP',
    'CALL_INCOMING',
    'CALL_ANSWERED',
    'CALL_DECLINED',
    'CALL_ENDED',
    'CALL_BUSY',
    'CALL_SIGNAL',
]);

const WHISPER_EVENT_KEYS = new Set([
    'CHAT_MESSAGE_TYPING',
    'CHAT_MESSAGE_TYPING_START',
    'CHAT_MESSAGE_TYPING_STOP',
]);

const EPHEMERAL_EVENT_KEYS = new Set([
    'USER_ONLINE',
    'USER_OFFLINE',
    'USER_ACTIVE_NOW',
    'FRIEND_PRESENCE_UPDATED',
    'CONVERSATION_PRESENCE_UPDATED',
    'CHAT_MESSAGE_TYPING',
    'CHAT_MESSAGE_TYPING_START',
    'CHAT_MESSAGE_TYPING_STOP',
    'CHAT_UPLOAD_PROGRESS',
    'CALL_SIGNAL',
    'REEL_VIEW_PROGRESS',
    'WEBSOCKET_DROPPED_EVENT',
    'WEBSOCKET_RECONNECT_RATE_UPDATED',
    'DELIVERY_LATENCY_UPDATED',
]);

const EVENT_ALIASES = Object.freeze({
    DIRECT_MESSAGE_RECEIVED: 'CHAT_MESSAGE_RECEIVED',
    DIRECT_MESSAGE_READ: 'CHAT_MESSAGE_READ',
    DIRECT_MESSAGE_DELETED: 'CHAT_MESSAGE_DELETED',
    CHAT_TYPING: 'CHAT_MESSAGE_TYPING',
    DM_TYPING: 'CHAT_MESSAGE_TYPING',
    MESSAGE_REACTIONS_UPDATED: 'CHAT_MESSAGE_REACTIONS_UPDATED',
    TIMELINE_CREATED: 'TIMELINE_POST_CREATED',
    TIMELINE_UPDATED: 'TIMELINE_POST_UPDATED',
    STORY_REACTION_UPDATED: 'STORY_REACTED',
    REEL_HIDE: 'REEL_NOT_INTERESTED',
});

const EVENTS = Object.freeze(
    Object.values(EVENT_GROUPS).reduce((carry, group) => {
        return Object.assign(carry, group);
    }, {})
);

const EVENT_META = Object.freeze(
    Object.entries(EVENT_GROUPS).reduce((carry, [groupName, groupEvents]) => {
        Object.entries(groupEvents).forEach(([key, eventName]) => {
            carry[key] = Object.freeze({
                key: key,
                name: eventName,
                group: groupName,
                status: (ACTIVE_EVENT_KEYS.has(key) ? 'active' : 'planned'),
                whisper: WHISPER_EVENT_KEYS.has(key),
                ephemeral: EPHEMERAL_EVENT_KEYS.has(key),
            });
        });

        return carry;
    }, {})
);

export {
    EVENTS,
    EVENT_META,
    EVENT_GROUPS,
    EVENT_ALIASES,
    ACTIVE_EVENT_KEYS,
    WHISPER_EVENT_KEYS,
    EPHEMERAL_EVENT_KEYS,
};
