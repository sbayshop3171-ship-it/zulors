import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

import { useInboxStore } from '@M/store/chats/inbox.store.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

const CHAT_CACHE_TTL = 1000 * 60 * 60;
const CHAT_CACHE_NAMESPACE = 'colibri:mobile:messenger-chat:';

function chatCacheKey(chatId) {
	return `${CHAT_CACHE_NAMESPACE}${chatId}`;
}

function readChatCache(chatId) {
	if(typeof window === 'undefined' || ! window.localStorage || ! chatId) {
		return null;
	}

	try {
		let payload = JSON.parse(window.localStorage.getItem(chatCacheKey(chatId)));

		if(! payload || ! Object.prototype.hasOwnProperty.call(payload, 'data')) {
			return null;
		}

		return payload;
	}
	catch(error) {
		return null;
	}
}

function writeChatCache(chatId, data) {
	if(typeof window === 'undefined' || ! window.localStorage || ! chatId) {
		return;
	}

	try {
		window.localStorage.setItem(chatCacheKey(chatId), JSON.stringify({
			timestamp: Date.now(),
			data: data
		}));
	}
	catch(error) {}
}

function clearChatCache(chatId) {
	if(typeof window === 'undefined' || ! window.localStorage || ! chatId) {
		return;
	}

	try {
		window.localStorage.removeItem(chatCacheKey(chatId));
	}
	catch(error) {}
}

function isFresh(timestamp) {
	return timestamp && ((Date.now() - timestamp) < CHAT_CACHE_TTL);
}

const useChatStore = defineStore('mobile_chats_chat', {
	state: () => {
		return {
			chatId: null,
			chatData: {},
			chatMessages: [],
			chatMessagesLoaded: false,
			chatMessagesFetchedAt: 0,
			chatParticipants: [],
			inboxStore: useInboxStore(),
            messageForm: {
                videoRecorder: {
                    elapsed: 0,
                }
            }
		};
	},
	getters: {
		otherParticipants: function() {
			return this.chatData.relations?.participants || [];
		}
	},
	actions: {
		hydrateChatMessagesCache: function(chatId = this.chatId) {
			let payload = readChatCache(chatId);

			if(! payload) {
				return false;
			}

			if(payload.data?.chatData?.chat_info) {
				this.chatData = payload.data.chatData;
			}

			this.chatMessages = Array.isArray(payload.data?.messages) ? payload.data.messages : [];
			this.chatMessagesLoaded = true;
			this.chatMessagesFetchedAt = payload.timestamp || Date.now();

			return true;
		},
		primeChatDataFromInbox: function(chatData = {}) {
			if(! chatData?.chat_id) {
				return false;
			}

			this.chatId = chatData.chat_id;
			this.chatData = chatData;

			let payload = readChatCache(chatData.chat_id);
			let messages = Array.isArray(payload?.data?.messages) ? payload.data.messages : this.chatMessages;

			writeChatCache(chatData.chat_id, {
				chatData: chatData,
				messages: messages.slice(-50)
			});

			return true;
		},
		persistChatMessagesCache: function(chatId = this.chatId) {
			if(! chatId) {
				return false;
			}

			this.chatMessagesLoaded = true;
			this.chatMessagesFetchedAt = Date.now();

			writeChatCache(chatId, {
				chatData: this.chatData,
				messages: this.chatMessages.slice(-50)
			});
		},
		fetchChatData: async function(chatId) {
			await colibriAPI().messenger().getFrom(`chat/${chatId}`).then((response) => {
				this.chatData = response.data.data;

				this.chatId = chatId;
				this.persistChatMessagesCache(chatId);
			}).catch((error) => {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		fetchChatParticipants: async function() {
			await colibriAPI().messenger().getFrom(`chat/${this.chatId}/participants`).then((response) => {
				this.chatParticipants = response.data.data;
			}).catch((error) => {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		fetchChatMessages: async function(options = {}) {
			let { force = false, preferCache = true } = options;
			let hasCache = false;

			if(preferCache && ! force) {
				if(this.chatMessagesLoaded && isFresh(this.chatMessagesFetchedAt) && this.chatMessages.length) {
					hasCache = true;
				}
				else {
					hasCache = this.hydrateChatMessagesCache(this.chatId);
				}
			}

			return await colibriAPI().messenger().getFrom(`chat/${this.chatId}/messages`).then((response) => {
				this.chatMessages = response.data.data;
				this.persistChatMessagesCache();

				return this.chatMessages;
			}).catch((error) => {
				if(! hasCache && ! this.chatMessagesLoaded) {
					this.chatMessages = [];
				}

				if(error.response && ! hasCache) {
					throw new Error(error.response.data.message);
				}

				return this.chatMessages;
			});
		},
		deleteMessage: async function(messageId, deleteForAll = false) {
			await colibriAPI().messenger().with({
				message_id: messageId,
				payload: {
					delete_for_all: deleteForAll
				}
			}).delete('chat/message/delete').then((response) => {
				if(! response.data.data.is_global_delete) {
					let messageIndex = this.chatMessages.findIndex((item) => {
						return item.id == messageId;
					});

					if(messageIndex !== -1) {
						this.chatMessages.splice(messageIndex, 1);
						this.persistChatMessagesCache();
					}
				}
			}).catch(function(error) {
				if(error.response) {
					alert(error.response.data.message);
				}
			});
		},
		sendMessage: async function(messageData = {}) {
			await colibriAPI().messenger().with({
				chat_id: this.chatId,
				...messageData
			}).sendTo('send').then((response) => {
				if(response.data.data) {
					this.upsertMessage(response.data.data);
				}
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
        sendMediaMessage: async function(mediaData) {
            const formData = new FormData();
            const dateTime = new Date().toISOString();

            formData.append('chat_id', this.chatId);
            formData.append('media_type', mediaData.type);

            // In case if it's audio or video, we need to add the duration.
            if(mediaData.duration) {
                formData.append('media_duration', mediaData.duration);
            }

            if(mediaData.parent_id) {
                formData.append('parent_id', mediaData.parent_id);
            }

            formData.append('media', mediaData.file, mediaData.name || `Media-${dateTime}.${mediaData.extension}`);

            await colibriAPI().messenger().with(formData).withHeaders({
				'Content-Type': 'multipart/form-data'
			}).sendTo('send').then((response) => {
                if(response.data.data) {
                    this.upsertMessage(response.data.data);
                }
            }).catch(function(error) {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
		syncMessageReactions: function(messageId, reactions = [], actorUserId = null) {
			let reactableMessage = this.chatMessages.find((item) => {
				return item.id == messageId;
			});

			if(reactableMessage) {
				const currentUserId = useAuthStore().userData.id;
				const currentReactions = reactableMessage.relations.reactions || [];

				reactableMessage.relations.reactions = reactions.map((reactionItem) => {
					const currentReaction = currentReactions.find((item) => {
						return item.unified_id === reactionItem.unified_id;
					});

					return {
						...reactionItem,
						has_reacted: (actorUserId == currentUserId) ? reactionItem.has_reacted : (currentReaction?.has_reacted || false)
					};
				});

				this.persistChatMessagesCache();
			}
		},
		upsertMessage: function(messageData = {}) {
			let messageIndex = this.chatMessages.findIndex((item) => {
				return item.id == messageData.id;
			});

			if(messageIndex === -1) {
				this.appendMessage(messageData);
			}
			else {
				this.chatMessages.splice(messageIndex, 1, messageData);
				this.inboxStore.updateChatFromMessage(messageData, useAuthStore().userData.id, this.chatId);
				this.persistChatMessagesCache();
			}
		},
		appendMessage: function(messageData = {}) {
			if(this.chatMessages.some((item) => {
				return item.id == messageData.id;
			})) {
				this.upsertMessage(messageData);

				return false;
			}

			this.chatMessages.push(messageData);
			this.inboxStore.updateChatFromMessage(messageData, useAuthStore().userData.id, this.chatId);
			this.persistChatMessagesCache();
		},
		markMessageAsDeleted: async function(messageId) {
			let messageIndex = this.chatMessages.findIndex((item) => {
				return item.id == messageId;
			});

			if (messageIndex !== -1) {
				let deletedMessage = this.chatMessages[messageIndex];

				this.chatMessages.splice(messageIndex, 1, {
					...deletedMessage,
					content: '',
					relations: {
						...deletedMessage.relations,
						reactions: [],
						media: [],
						link_snapshot: null
					},
					meta: {
						...deletedMessage.meta,
						is_deleted: true
					}
				});

				this.inboxStore.markChatMessageAsDeleted(this.chatId, messageId);
				this.persistChatMessagesCache();
			}
		},
		updateLastReadMessageForParticipant: function(data) {
			if(! this.otherParticipants?.length) {
				return false;
			}

			this.otherParticipants.forEach((participant) => {
				if(participant.user_id == data.user_id) {
					participant.last_read_message_id = data.last_read_message_id;
				}
			});
		},
		markMessagesAsRead: function() {
			this.inboxStore.markChatAsRead(this.chatId);

			colibriAPI().messenger().getFrom(`chat/${this.chatId}/read`).then(() => {
				this.inboxStore.fetchUnreadCount();
			}).catch(function(error) {
				alert(error.response.data.message);
			});
		},
		addReaction: function(reactionId, messageId) {
			colibriAPI().messenger().with({
				unified_id: reactionId,
				message_id: messageId
			}).sendTo('chat/message/add-reaction').then((response) => {
				let reactableMessage = this.chatMessages.find((item) => {
					return item.id == messageId;
				});

				if (reactableMessage) {
					reactableMessage.relations.reactions = response.data.data;
					this.persistChatMessagesCache();
				}

			}).catch((error) => {
				if (error.response) {
					alert(error.response.data.message);
				}
			});
		},
		clearChat: async function() {
			await colibriAPI().messenger().delete(`chat/${this.chatId}/clear`).then((response) => {
				this.chatMessages = [];
				this.persistChatMessagesCache();
			}).catch((error) => {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		deleteChat: async function() {
			await colibriAPI().messenger().delete(`chat/${this.chatId}/delete`).then((response) => {
				this.chatMessages = [];
				clearChatCache(this.chatId);

				this.inboxStore.removeChatFromHistory(this.chatId);
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		archiveChat: async function(chatId) {
			await colibriAPI().messenger().delete(`chat/${chatId}/archive`).then((response) => {
				this.inboxStore.removeChatFromHistory(chatId);
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		unarchiveChat: async function(chatId) {
			await colibriAPI().messenger().delete(`chat/${chatId}/unarchive`).then((response) => {
				this.inboxStore.fetchChatsHistory();
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
	}
});

export { useChatStore };
