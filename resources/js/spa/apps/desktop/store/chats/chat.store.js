import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import {
    buildPendingAudioLocalState,
    createPendingAudioMessage,
    mergeIncomingAudioMessage,
    withLocalAudioState,
} from '@/kernel/helpers/chat/pending-audio-message.js';
import { useInboxStore } from '@D/store/chats/inbox.store.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';
import {
	createOptimisticOutgoingMessage,
	findPendingOutgoingMessageIndex,
} from '@/kernel/helpers/chat/pending-outgoing-message.js';
import { readMessengerCache, writeMessengerCache } from '@/kernel/services/cache/messenger-cache.js';

const CHAT_CACHE_TTL = 1000 * 60 * 60 * 24;

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
		hydrateChatCache: function(chatId = this.chatId) {
			if(! chatId) {
				return false;
			}

			const chatEntry = readMessengerCache('chat', `${chatId}:data`, null, CHAT_CACHE_TTL);
			const messagesEntry = readMessengerCache('chat', `${chatId}:messages`, null, CHAT_CACHE_TTL);
			const participantsEntry = readMessengerCache('chat', `${chatId}:participants`, null, CHAT_CACHE_TTL);
			let hydrated = false;

			if(chatEntry?.data) {
				this.chatData = chatEntry.data;
				hydrated = true;
			}

			if(Array.isArray(messagesEntry?.data)) {
				this.chatMessages = messagesEntry.data;
				hydrated = true;
			}

			if(Array.isArray(participantsEntry?.data)) {
				this.chatParticipants = participantsEntry.data;
				hydrated = true;
			}

			this.chatId = chatId;

			return hydrated;
		},
		persistChatCache: function() {
			if(! this.chatId) {
				return false;
			}

			writeMessengerCache('chat', `${this.chatId}:data`, this.chatData);
			writeMessengerCache('chat', `${this.chatId}:messages`, this.chatMessages.slice(-100));
			writeMessengerCache('chat', `${this.chatId}:participants`, this.chatParticipants);

			return true;
		},
		fetchChatData: async function(chatId, options = {}) {
			const { preferCache = true } = options;

			if(preferCache) {
				this.hydrateChatCache(chatId);
			}

			let state = this;

			await colibriAPI().messenger().getFrom(`chat/${chatId}`).then(function(response) {
				state.chatData = response.data.data;

				state.chatId = chatId;
				state.persistChatCache();
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
				state.persistChatCache();
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
		fetchChatMessages: async function(options = {}) {
			const { preferCache = true } = options;

			if(preferCache) {
				this.hydrateChatCache(this.chatId);
			}

			let state = this;

			await colibriAPI().messenger().getFrom(`chat/${state.chatId}/messages`).then(function(response) {
				state.chatMessages = response.data.data;
				state.persistChatCache();
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		sendMessage: async function(messageData = {}) {
			const clientUid = messageData.client_uid || `msg-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
			const optimisticMessage = createOptimisticOutgoingMessage({
				chatId: this.chatId,
				userData: useAuthStore().userData || {},
				content: messageData.content || '',
				messageType: messageData.message_type || messageData.type || 'text',
				parentMessage: messageData.parent_message || null,
				parentId: messageData.parent_id || null,
				clientUid: clientUid,
			});

			this.appendMessage(optimisticMessage);

			try {
				const response = await colibriAPI().messenger().with({
					chat_id: this.chatId,
					...messageData,
					client_uid: clientUid,
				}).sendTo('send');

				if(response.data.data) {
					this.upsertMessage(response.data.data);
				}
			}
			catch(error) {
				this.removeMessage(optimisticMessage.id);

				if(error.response) {
					throw new Error(error.response.data.message);
				}

				throw error;
			}
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
        sendAudioMessage: async function(audioData = {}) {
            const authStore = useAuthStore();
            const durationSeconds = Math.max(1, Math.ceil(Number(audioData.duration) || 1));
            const extension = audioData.extension || 'webm';
            const fileName = audioData.name || `voice-note-${Date.now()}.${extension}`;
            const clientUid = `audio-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
            const localMessageId = `local-${clientUid}`;
            const pendingState = buildPendingAudioLocalState({
                stage: 'uploading',
                uploadProgress: 0,
                clientUid: clientUid,
            });

            this.appendMessage(createPendingAudioMessage({
                localId: localMessageId,
                chatId: this.chatId,
                userData: authStore.userData || {},
                durationSeconds: durationSeconds,
                extension: extension,
                fileName: fileName,
                blobSize: audioData.file?.size || 0,
                parentMessage: audioData.parent_message || null,
                clientUid: clientUid,
            }));

            let serverMessageId = null;

            try {
                const initResponse = await colibriAPI().messenger().with({
                    chat_id: this.chatId,
                    parent_id: audioData.parent_id || null,
                    duration_seconds: durationSeconds,
                    extension: extension,
                    mime_type: audioData.mime_type || audioData.file?.type || `audio/${extension}`,
                    file_name: fileName,
                }).sendTo('audio/init');

                const initializedMessage = withLocalAudioState(initResponse.data.data, pendingState);

                serverMessageId = initializedMessage.id;
                this.replaceTemporaryMessage(localMessageId, initializedMessage);

                const formData = new FormData();
                formData.append('audio', audioData.file, fileName);

                const uploadResponse = await colibriAPI().messenger().with(formData).withHeaders({
                    'Content-Type': 'multipart/form-data'
                }).uploadProgress((progressEvent) => {
                    const total = Number(progressEvent?.total || 0);
                    const loaded = Number(progressEvent?.loaded || 0);
                    const uploadProgress = total > 0 ? Math.round((loaded / total) * 100) : 0;

                    this.setLocalAudioState(serverMessageId, buildPendingAudioLocalState({
                        stage: 'uploading',
                        uploadProgress: Math.max(uploadProgress, 1),
                        clientUid: clientUid,
                    }));
                }).sendTo(`audio/${serverMessageId}/upload`);

                if(uploadResponse.status === 202) {
                    this.setLocalAudioState(serverMessageId, buildPendingAudioLocalState({
                        stage: 'processing',
                        uploadProgress: 100,
                        clientUid: clientUid,
                    }));
                }

                if(uploadResponse.data?.data) {
                    this.upsertMessage(uploadResponse.data.data);
                }

                return uploadResponse.data?.data || null;
            }
            catch(error) {
                if(serverMessageId) {
                    try {
                        await colibriAPI().messenger().with({}).sendTo(`audio/${serverMessageId}/fail`);
                    }
                    catch(requestError) {}

                    this.markMessageAsDeleted(serverMessageId);
                }
                else {
                    this.removeMessage(localMessageId);
                }

                this.inboxStore.scheduleUnreadStateSync(0);

                if(error.response) {
                    throw new Error(error.response.data.message);
                }

                throw error;
            }
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
        removeMessage: function(messageId) {
            const messageIndex = this.chatMessages.findIndex((item) => {
                return item.id == messageId;
            });

            if(messageIndex !== -1) {
                this.chatMessages.splice(messageIndex, 1);
            }
        },
        replaceTemporaryMessage: function(tempMessageId, messageData = {}) {
            const tempIndex = this.chatMessages.findIndex((item) => {
                return item.id == tempMessageId;
            });
            const existingIndex = this.chatMessages.findIndex((item) => {
                return item.id == messageData.id;
            });
            const sourceMessage = existingIndex !== -1
                ? this.chatMessages[existingIndex]
                : (tempIndex !== -1 ? this.chatMessages[tempIndex] : null);
            const nextMessage = mergeIncomingAudioMessage(sourceMessage, messageData);

            if(existingIndex !== -1) {
                this.chatMessages.splice(existingIndex, 1, nextMessage);
            }
            else if(tempIndex !== -1) {
                this.chatMessages.splice(tempIndex, 1, nextMessage);
            }
            else {
                this.chatMessages.push(nextMessage);
            }

            const residualTempIndex = this.chatMessages.findIndex((item) => {
                return item.id == tempMessageId;
            });

            if(residualTempIndex !== -1 && tempMessageId != nextMessage.id) {
                this.chatMessages.splice(residualTempIndex, 1);
            }

            this.inboxStore.updateChatFromMessage(nextMessage, useAuthStore().userData.id, this.chatId);
        },
        setLocalAudioState: function(messageId, localAudioState = null) {
            const messageIndex = this.chatMessages.findIndex((item) => {
                return item.id == messageId;
            });

            if(messageIndex === -1) {
                return false;
            }

            this.chatMessages.splice(messageIndex, 1, withLocalAudioState(this.chatMessages[messageIndex], localAudioState));

            return true;
        },
		upsertMessage: function(messageData = {}) {
			let messageIndex = findPendingOutgoingMessageIndex(this.chatMessages, messageData);

			if(messageIndex !== -1) {
				this.replaceTemporaryMessage(this.chatMessages[messageIndex].id, messageData);

				return;
			}

			messageIndex = this.chatMessages.findIndex((item) => {
				return item.id == messageData.id;
			});
            const nextMessage = messageIndex === -1
                ? messageData
                : mergeIncomingAudioMessage(this.chatMessages[messageIndex], messageData);

			if(messageIndex === -1) {
				this.appendMessage(nextMessage);
			}
			else {
				this.chatMessages.splice(messageIndex, 1, nextMessage);
				this.inboxStore.updateChatFromMessage(nextMessage, useAuthStore().userData.id, this.chatId);
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
