const CHANNEL_GROUPS = Object.freeze({
    public: Object.freeze({
        PUBLIC_TIMELINE: 'timeline.public',
        PUBLIC_REELS: 'reels.public',
        PUBLIC_STORIES: 'stories.public',
    }),
    private: Object.freeze({
        AUTH_USER: 'App.Models.User.{0}',
        AUTH_USER_NOTIFICATIONS: 'App.Models.User.{0}',
        AUTH_USER_FEED: 'App.Models.User.{0}.feed',
        CHAT: 'App.Models.Chat.{0}',
        CHAT_UPLOADS: 'App.Models.Chat.{0}.uploads',
    }),
    presence: Object.freeze({
        AUTH_USER_PRESENCE: 'presence.user.{0}',
        CHAT_PRESENCE: 'presence.chat.{0}',
        FRIEND_PRESENCE: 'presence.friend.{0}',
    })
});

const ACTIVE_CHANNEL_KEYS = new Set([
    'PUBLIC_TIMELINE',
    'AUTH_USER',
    'AUTH_USER_NOTIFICATIONS',
    'CHAT',
]);

const CHANNEL_ALIASES = Object.freeze({
    USER: 'AUTH_USER',
    USER_NOTIFICATIONS: 'AUTH_USER_NOTIFICATIONS',
    PRIVATE_CHAT: 'CHAT',
    TIMELINE: 'PUBLIC_TIMELINE',
});

const CHANNELS = Object.freeze(
    Object.values(CHANNEL_GROUPS).reduce((carry, group) => {
        return Object.assign(carry, group);
    }, {})
);

const CHANNEL_META = Object.freeze(
    Object.entries(CHANNEL_GROUPS).reduce((carry, [groupName, groupChannels]) => {
        Object.entries(groupChannels).forEach(([key, channelName]) => {
            carry[key] = Object.freeze({
                key: key,
                name: channelName,
                group: groupName,
                scope: groupName,
                status: (ACTIVE_CHANNEL_KEYS.has(key) ? 'active' : 'planned'),
            });
        });

        return carry;
    }, {})
);

export {
    CHANNELS,
    CHANNEL_META,
    CHANNEL_GROUPS,
    CHANNEL_ALIASES,
    ACTIVE_CHANNEL_KEYS,
};
