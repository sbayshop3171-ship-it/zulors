import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useInboxStore } from '@D/store/chats/inbox.store.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';

const useChatStore = defineStore('chats_chat', {
	state: () => {
		return {
			chatId: null,
			chatData: {},
			chatMessages: [],
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
		isDirect: function() {
			return this.chatData.type == 'direct';
		},
		hasDescription: function() {
            if(! this.chatData.chat_info.description) {
                return false;
            }
            else {
                return this.chatData.chat_info.description.length > 0;
            }
		},
		otherParticipants: function() {
			return this.chatData.relations.participants;
		}
	},
	actions: {
		fetchChatData: async function(chatId) {
			let state = this;

			await colibriAPI().messenger().getFrom(`chat/${chatId}`).then(function(response) {
				state.chatData = response.data.data;

				state.chatId = chatId;
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		fetchChatParticipants: async function() {
			let state = this;

			await colibriAPI().messenger().getFrom(`chat/${state.chatId}/participants`).then(function(response) {
				state.chatParticipants = response.data.data;
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		markMessagesAsRead: function() {
			let state = this;

			state.inboxStore.markChatAsRead(state.chatId);

			colibriAPI().messenger().getFrom(`chat/${state.chatId}/read`).then(function() {
				state.inboxStore.fetchUnreadCount();
			}).catch(function(error) {
				alert(error.response.data.message);
			});
		},
		fetchChatMessages: async function() {
			let state = this;

			await colibriAPI().messenger().getFrom(`chat/${state.chatId}/messages`).then(function(response) {
				state.chatMessages = response.data.data;
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		sendMessage: async function(messageData = {}) {
			let state = this;

			await colibriAPI().messenger().with({
				chat_id: state.chatId,
				...messageData
			}).sendTo('send').then(function(response) {
				if(response.data.data) {
					state.upsertMessage(response.data.data);
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
		deleteMessage: async function(messageId, deleteForAll = false) {
			let state = this;

			await colibriAPI().messenger().with({
				message_id: messageId,
				payload: {
					delete_for_all: deleteForAll
				}
			}).delete('chat/message/delete').then((response) => {
				if(! response.data.data.is_global_delete) {
					let messageIndex = state.chatMessages.findIndex((item) => {
						return item.id == messageId;
					});

					if(messageIndex !== -1) {
						state.chatMessages.splice(messageIndex, 1);
					}
				}
			}).catch(function(error) {
				if(error.response) {
					alert(error.response.data.message);
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
		clearChatConversation: async function() {
			let state = this;

			await colibriAPI().messenger().delete(`chat/${state.chatId}/clear`).then(function(response) {
				state.chatMessages = [];
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		deleteChat: async function() {
			let state = this;

			await colibriAPI().messenger().delete(`chat/${state.chatId}/delete`).then(function(response) {
				state.chatMessages = [];

				state.inboxStore.removeChatFromHistory(state.chatId);
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
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
			}
		},
		addReaction: function(reactionId, messageId) {
			let state = this;

			colibriAPI().messenger().with({
				unified_id: reactionId,
				message_id: messageId
			}).sendTo('chat/message/add-reaction').then((response) => {
				let reactableMessage = state.chatMessages.find((item) => {
					return item.id == messageId;
				});

				if (reactableMessage) {
					reactableMessage.relations.reactions = response.data.data;
				}

			}).catch((error) => {
				if (error.response) {
					alert(error.response.data.message);
				}
			});
		},
		updateLastReadMessageForParticipant: function(data) {
			if(! this.chatData.relations?.participants?.length) {
				return false;
			}

			let participantData = this.chatData.relations.participants.find((p) => {
				return data.user_id == p.user_id;
			});

			if(participantData) {
				participantData.last_read_message_id = data.last_read_message_id;
			}
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
			}
		},
		appendMessage: function(messageData = {}) {
			let state = this;

			if(state.chatMessages.some((item) => {
				return item.id == messageData.id;
			})) {
				state.upsertMessage(messageData);

				return false;
			}

			state.chatMessages.push(messageData);
			state.inboxStore.updateChatFromMessage(messageData, useAuthStore().userData.id, state.chatId);
		}
	}
});

export { useChatStore };
