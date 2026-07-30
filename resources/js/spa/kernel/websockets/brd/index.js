import { Str } from '@/kernel/helpers/javascript/index.js';

export default {
    events: {
        TIMELINE_MEDIA_PROCESSED: ".timeline.media.processed",
        TIMELINE_MEDIA_UPDATED: ".timeline.media.updated",
        TIMELINE_POST_CREATED: ".timeline.post.created",
        TIMELINE_POST_UPDATED: ".timeline.post.updated",
        CHAT_MESSAGE_RECEIVED: ".chat.message.received",
        CHAT_MESSAGE_DELETED: ".chat.message.deleted",
        CHAT_MESSAGE_REACTIONS_UPDATED: ".chat.message.reactions.updated",
        CHAT_MESSAGE_READ: ".chat.message.read",
        CHAT_MESSAGE_TYPING: ".chat.message.typing",
    },
    channels: {
        PUBLIC_TIMELINE: "timeline.public",
        AUTH_USER: "App.Models.User.{0}",
        CHAT: "App.Models.Chat.{0}"
    },
    getEvent: function(key) {
        return this.events[key];
    },
    getChannel: function(key, args = [], defaultVal = null) {
        return Str.make(this.channels[key]).format(...args).value() ?? defaultVal;
    }
};
