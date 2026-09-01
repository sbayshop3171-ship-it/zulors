import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const CACHE_TTL = 1000 * 60 * 5;
const CACHE_NAMESPACE = 'colibri:desktop:messenger-inbox:';
let unreadStateSyncTimer = null;

function cacheKey(key) {
    return `${CACHE_NAMESPACE}${key}`;
}

function readCache(key) {
    if(typeof window === 'undefined' || ! window.localStorage) {
        return null;
    }

    try {
        let payload = JSON.parse(window.localStorage.getItem(cacheKey(key)));

        if(! payload || ! Object.prototype.hasOwnProperty.call(payload, 'data')) {
            return null;
        }

        return payload;
    }
    catch(error) {
        return null;
    }
}

function writeCache(key, data) {
    if(typeof window === 'undefined' || ! window.localStorage) {
        return;
    }

    try {
        window.localStorage.setItem(cacheKey(key), JSON.stringify({
            timestamp: Date.now(),
            data: data
        }));
    }
    catch(error) {}
}

function isFresh(timestamp) {
    return timestamp && ((Date.now() - timestamp) < CACHE_TTL);
}

const useInboxStore = defineStore('chats_inbox', {
    state: () => {
        return {
            chatsHistory: [],
            chatRequests: [],
            chatsArchive: [],
            chatRequestsCount: 0,
            activeChatId: null,
            chatsHistoryLoaded: false,
            chatsHistoryFetchedAt: 0,
            chatsArchiveLoaded: false,
            chatsArchiveFetchedAt: 0,
            chatRequestsLoaded: false,
            chatRequestsFetchedAt: 0,
            chatRequestsCountLoaded: false,
            chatRequestsCountFetchedAt: 0,
            messengerSearchLoaded: false,
            messengerSearchFetchedAt: 0,
            unreadCount: {
				formatted: 0,
				raw: 0
			},
            handledIncomingMessageIds: [],
            messengerSearch: {
                recents: [],
                suggestions: []
            }
        };
    },
    actions: {
        setActiveChatId: function(chatId = null) {
            this.activeChatId = chatId || null;
        },
        findChatById: function(chatId) {
            if(! chatId) {
                return null;
            }

            return this.chatsHistory.find((chatData) => {
                return chatData.chat_id == chatId;
            }) || this.chatsArchive.find((chatData) => {
                return chatData.chat_id == chatId;
            }) || null;
        },
        hydrateChatsHistoryCache: function() {
            let payload = readCache('chats-history');

            if(! payload) {
                return false;
            }

            this.chatsHistory = Array.isArray(payload.data) ? payload.data : [];
            this.chatsHistoryLoaded = true;
            this.chatsHistoryFetchedAt = payload.timestamp || Date.now();

            return true;
        },
        hydrateChatsArchiveCache: function() {
            let payload = readCache('chats-archive');

            if(! payload) {
                return false;
            }

            this.chatsArchive = Array.isArray(payload.data) ? payload.data : [];
            this.chatsArchiveLoaded = true;
            this.chatsArchiveFetchedAt = payload.timestamp || Date.now();

            return true;
        },
        hydrateChatRequestsCache: function() {
            let payload = readCache('chat-requests');

            if(! payload) {
                return false;
            }

            this.chatRequests = Array.isArray(payload.data) ? payload.data : [];
            this.chatRequestsLoaded = true;
            this.chatRequestsFetchedAt = payload.timestamp || Date.now();

            return true;
        },
        hydrateChatRequestsCountCache: function() {
            let payload = readCache('chat-requests-count');

            if(! payload) {
                return false;
            }

            this.chatRequestsCount = payload.data || 0;
            this.chatRequestsCountLoaded = true;
            this.chatRequestsCountFetchedAt = payload.timestamp || Date.now();

            return true;
        },
        hydrateMessengerSearchCache: function() {
            let payload = readCache('messenger-search');

            if(! payload) {
                return false;
            }

            this.messengerSearch = {
                recents: payload.data?.recents || [],
                suggestions: payload.data?.suggestions || []
            };
            this.messengerSearchLoaded = true;
            this.messengerSearchFetchedAt = payload.timestamp || Date.now();

            return true;
        },
        persistChatsHistoryCache: function() {
            this.chatsHistoryLoaded = true;
            this.chatsHistoryFetchedAt = Date.now();
            writeCache('chats-history', this.chatsHistory);
        },
        persistChatRequestsCache: function() {
            this.chatRequestsLoaded = true;
            this.chatRequestsFetchedAt = Date.now();
            writeCache('chat-requests', this.chatRequests);
        },
        persistMessengerSearchCache: function() {
            this.messengerSearchLoaded = true;
            this.messengerSearchFetchedAt = Date.now();
            writeCache('messenger-search', this.messengerSearch);
        },
        fetchChatRequests: async function(options = {}) {
            let { force = false, preferCache = true } = options;

            if(preferCache && ! force) {
                if(this.chatRequestsLoaded && isFresh(this.chatRequestsFetchedAt)) {
                    return this.chatRequests;
                }

                if(! this.chatRequestsLoaded) {
                    this.hydrateChatRequestsCache();
                }
            }

            return await colibriAPI().messenger().getFrom('chats/requests').then((response) => {
                this.chatRequests = response.data.data;
                this.persistChatRequestsCache();

                return this.chatRequests;
            }).catch(() => {
                if(! this.chatRequestsLoaded) {
                    this.chatRequests = [];
                }

                return this.chatRequests;
            });
        },
        fetchChatRequestsCount: async function(options = {}) {
            let { force = false, preferCache = true } = options;

            if(preferCache && ! force) {
                if(this.chatRequestsCountLoaded && isFresh(this.chatRequestsCountFetchedAt)) {
                    return this.chatRequestsCount;
                }

                if(! this.chatRequestsCountLoaded) {
                    this.hydrateChatRequestsCountCache();
                }
            }

            return await colibriAPI().messenger().getFrom('chats/requests/count').then((response) => {
                this.chatRequestsCount = response.data.data.count;
                this.chatRequestsCountLoaded = true;
                this.chatRequestsCountFetchedAt = Date.now();
                writeCache('chat-requests-count', this.chatRequestsCount);

                return this.chatRequestsCount;
            }).catch(() => {
                if(! this.chatRequestsCountLoaded) {
                    this.chatRequestsCount = 0;
                }

                return this.chatRequestsCount;
            });
        },
        fetchChatsHistory: async function(options = {}) {
            let { force = false, preferCache = true } = options;

            if(preferCache && ! force) {
                if(this.chatsHistoryLoaded && isFresh(this.chatsHistoryFetchedAt)) {
                    return this.chatsHistory;
                }

                if(! this.chatsHistoryLoaded) {
                    this.hydrateChatsHistoryCache();
                }
            }

            return await colibriAPI().messenger().getFrom('chats').then((response) => {
                this.chatsHistory = response.data.data;
                this.persistChatsHistoryCache();

                return this.chatsHistory;
            }).catch(() => {
                if(! this.chatsHistoryLoaded) {
                    this.chatsHistory = [];
                }

                return this.chatsHistory;
            });
        },
        fetchChatsArchive: async function(options = {}) {
            let { force = false, preferCache = true } = options;

            if(preferCache && ! force) {
                if(this.chatsArchiveLoaded && isFresh(this.chatsArchiveFetchedAt)) {
                    return this.chatsArchive;
                }

                if(! this.chatsArchiveLoaded) {
                    this.hydrateChatsArchiveCache();
                }
            }

            return await colibriAPI().messenger().getFrom('archive').then((response) => {
                this.chatsArchive = response.data.data;
                this.chatsArchiveLoaded = true;
                this.chatsArchiveFetchedAt = Date.now();
                writeCache('chats-archive', this.chatsArchive);

                return this.chatsArchive;
            }).catch(() => {
                if(! this.chatsArchiveLoaded) {
                    this.chatsArchive = [];
                }

                return this.chatsArchive;
            });
        },
        fetchUnreadCount: function() {
            return colibriAPI().messenger().getFrom('unread/count').then((response) => {
                this.unreadCount = response.data.data || {
                    formatted: 0,
                    raw: 0
                };

                return this.unreadCount;
            }).catch(() => {
                this.unreadCount = {
                    formatted: 0,
                    raw: 0
                };

                return this.unreadCount;
            });
        },
        syncUnreadState: async function() {
            await Promise.all([
                this.fetchUnreadCount(),
                this.fetchChatsHistory({
                    force: true,
                    preferCache: false
                })
            ]);

            return {
                unreadCount: this.unreadCount,
                chatsHistory: this.chatsHistory
            };
        },
        scheduleUnreadStateSync: function(delay = 1200) {
            if(typeof window === 'undefined') {
                return this.syncUnreadState();
            }

            if(unreadStateSyncTimer) {
                window.clearTimeout(unreadStateSyncTimer);
            }

            unreadStateSyncTimer = window.setTimeout(() => {
                this.syncUnreadState();
            }, delay);
        },
        rememberIncomingMessage: function(messageId) {
            if(! messageId) {
                return true;
            }

            if(this.handledIncomingMessageIds.includes(messageId)) {
                return false;
            }

            this.handledIncomingMessageIds.push(messageId);
            this.handledIncomingMessageIds = this.handledIncomingMessageIds.slice(-80);

            return true;
        },
        handleIncomingMessageNotification: function(messageData, currentUserId, activeChatId = null) {
            if(! messageData?.id || ! messageData?.chat_uuid) {
                this.scheduleUnreadStateSync(0);

                return false;
            }

            let senderId = messageData.user_id ?? messageData.user?.id ?? messageData.relations?.sender?.id;

            if(senderId == currentUserId) {
                return false;
            }

            let isActiveChat = activeChatId == messageData.chat_uuid;
            let isNewMessage = this.rememberIncomingMessage(messageData.id);
            let chatExists = this.chatsHistory.some((item) => {
                return item.chat_id == messageData.chat_uuid;
            });

            if(chatExists) {
                this.updateChatFromMessage(messageData, currentUserId, activeChatId);
                this.scheduleUnreadStateSync(1800);
            }
            else {
                if(isNewMessage && ! isActiveChat) {
                    this.unreadCount.raw = Number(this.unreadCount?.raw || 0) + 1;
                    this.unreadCount.formatted = this.formatCounter(this.unreadCount.raw);
                }

                this.scheduleUnreadStateSync(250);
            }

            return isNewMessage && ! isActiveChat;
        },
        markChatAsRead: function(chatId) {
            let chatData = this.chatsHistory.find((item) => {
                return item.chat_id == chatId;
            });

            let chatUnreadCount = Number(chatData?.unread_messages_count?.raw || 0);

            if(chatData && chatUnreadCount) {
                this.unreadCount.raw = Math.max(0, Number(this.unreadCount?.raw || 0) - chatUnreadCount);
                this.unreadCount.formatted = this.formatCounter(this.unreadCount.raw);
                chatData.unread_messages_count.raw = 0;
                chatData.unread_messages_count.formatted = 0;
                this.persistChatsHistoryCache();
            }
        },
        updateChatFromMessage: function(messageData, currentUserId, activeChatId = null) {
            let chatIndex = this.chatsHistory.findIndex((item) => {
                return item.chat_id == messageData.chat_uuid;
            });

            if(chatIndex === -1) {
                this.syncUnreadState();

                return false;
            }

            let chatData = this.chatsHistory[chatIndex];
            let senderId = messageData.user_id ?? messageData.user?.id ?? messageData.relations?.sender?.id;
            let isSender = senderId == currentUserId;
            let isActiveChat = activeChatId == messageData.chat_uuid;
            let chatUnreadCount = Number(chatData.unread_messages_count?.raw || 0);
            let totalUnreadCount = Number(this.unreadCount?.raw || 0);
            let alreadyCurrentMessage = chatData.last_message_id == messageData.id;

            chatData.unread_messages_count = {
                raw: chatUnreadCount,
                formatted: this.formatCounter(chatUnreadCount)
            };

            this.unreadCount = {
                raw: totalUnreadCount,
                formatted: this.formatCounter(totalUnreadCount)
            };

            chatData.last_message_id = messageData.id;
            chatData.last_message = this.getMessagePreview(messageData, isSender);
            chatData.last_message_type = messageData.type || null;
            chatData.last_message_is_mine = isSender;
            chatData.is_deleted = messageData.meta?.is_deleted || false;
            chatData.last_activity = {
                time_ago: '0s',
                raw: Date.now(),
                formatted: ''
            };

            if(! isSender && ! isActiveChat && ! alreadyCurrentMessage) {
                chatData.unread_messages_count.raw = chatData.unread_messages_count.raw + 1;
                chatData.unread_messages_count.formatted = this.formatCounter(chatData.unread_messages_count.raw);
                this.unreadCount.raw = this.unreadCount.raw + 1;
                this.unreadCount.formatted = this.formatCounter(this.unreadCount.raw);
            }

            this.chatsHistory.splice(chatIndex, 1);
            this.chatsHistory.unshift(chatData);
            this.persistChatsHistoryCache();
        },
        markChatMessageAsDeleted: function(chatId, messageId) {
            let chatIndex = this.chatsHistory.findIndex((item) => {
                return item.chat_id == chatId;
            });

            if(chatIndex === -1) {
                return false;
            }

            let chatData = this.chatsHistory[chatIndex];

            if(chatData.last_message_id && chatData.last_message_id != messageId) {
                return false;
            }

            chatData.last_message_id = messageId;
            chatData.last_message = '';
            chatData.last_message_type = null;
            chatData.is_deleted = true;

            this.chatsHistory.splice(chatIndex, 1, chatData);
            this.persistChatsHistoryCache();
        },
        getMessagePreview: function(messageData, isSender = false) {
            let previewText = '';

            if(messageData.content) {
                previewText = messageData.content;
            }
            else if(messageData.type === 'image') {
                previewText = __t('labels.image');
            }
            else if(messageData.type === 'audio') {
                previewText = __t('labels.audio');
            }
            else if(['video_circle', 'video'].includes(messageData.type)) {
                previewText = __t('labels.video');
            }
            else if(messageData.type === 'document') {
                previewText = __t('labels.document');
            }
            else if(messageData.type === 'location') {
                previewText = __t('labels.location');
            }
            else if(messageData.type === 'call') {
                previewText = messageData.content || 'Voice call';
            }

            if(isSender && previewText) {
                return `${__t('labels.you')}: ${previewText}`;
            }

            return previewText;
        },
        formatCounter: function(count) {
            if(count >= 1000000) {
                return `${Math.floor(count / 1000000)}M`;
            }

            if(count >= 1000) {
                return `${Math.floor(count / 1000)}K`;
            }

            return count;
        },
        fetchSearchBootstrap: async function(options = {}) {
            let { force = false, preferCache = true } = options;

            if(preferCache && ! force) {
                if(this.messengerSearchLoaded && isFresh(this.messengerSearchFetchedAt)) {
                    return this.messengerSearch;
                }

                if(! this.messengerSearchLoaded) {
                    this.hydrateMessengerSearchCache();
                }
            }

            return await colibriAPI().messenger().getFrom('search/bootstrap').then((response) => {
                this.messengerSearch.recents = response.data.data.recents;
                this.messengerSearch.suggestions = response.data.data.suggestions;
                this.persistMessengerSearchCache();

                return response.data.data;
            }).catch(() => {
                if(! this.messengerSearchLoaded) {
                    this.messengerSearch.recents = [];
                    this.messengerSearch.suggestions = [];
                }

                return this.messengerSearch;
            });
        },
        searchMessenger: async function(query) {
            return await colibriAPI().messenger().params({
                q: query
            }).getFrom('search').then((response) => {
                return response.data.data;
            }).catch(() => {
                return {
                    chats: [],
                    users: []
                };
            });
        },
        storeSearchRecent: async function(userId) {
            return await colibriAPI().messenger().with({
                user_id: userId
            }).sendTo('search/recent').then((response) => {
                this.messengerSearch.recents = response.data.data.recents;
                this.persistMessengerSearchCache();

                return response.data.data.recents;
            });
        },
        deleteSearchRecent: async function(userId) {
            return await colibriAPI().messenger().delete(`search/recent/${userId}`).then((response) => {
                this.messengerSearch.recents = response.data.data.recents;
                this.persistMessengerSearchCache();

                return response.data.data.recents;
            });
        },
        clearSearchRecents: async function() {
            return await colibriAPI().messenger().delete('search/recent').then((response) => {
                this.messengerSearch.recents = [];
                this.persistMessengerSearchCache();

                return response.data.data.recents;
            });
        },
        removeRequestFromHistory: function(chatId) {
            let requestIndex = this.chatRequests.findIndex((item) => {
                return item.relations.chat.chat_id == chatId;
            });

            if(requestIndex !== -1) {
                this.chatRequestsCount--;
                this.chatRequests.splice(requestIndex, 1);
                this.persistChatRequestsCache();
            }
        },
        removeChatFromHistory: function(chatId) {
            let chatIndex = this.chatsHistory.findIndex((item) => {
                return item.chat_id == chatId;
            });

            if(chatIndex !== -1) {
                this.chatsHistory.splice(chatIndex, 1);
                this.persistChatsHistoryCache();
            }
        }
    }
});

export { useInboxStore };
