import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import {
    buildPendingAudioLocalState,
    createPendingAudioMessage,
    mergeIncomingAudioMessage,
    withLocalAudioState,
} from '@/kernel/helpers/chat/pending-audio-message.js';
import {
	createOptimisticOutgoingMessage,
	findPendingOutgoingMessageIndex,
} from '@/kernel/helpers/chat/pending-outgoing-message.js';

import { useInboxStore } from '@M/store/chats/inbox.store.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

const CHAT_CACHE_TTL = 1000 * 60 * 60;
const CHAT_CACHE_NAMESPACE = 'colibri:mobile:messenger-chat:';
const MESSAGE_PAGE_SIZE = 30;

function createMessagesPagination() {
	return {
		hasMore: false,
		nextBeforeId: null,
		isLoadingOlder: false
	};
}

function compareMessagesByTimeOrId(firstMessage = {}, secondMessage = {}) {
	const firstId = Number(firstMessage.id);
	const secondId = Number(secondMessage.id);

	if(Number.isFinite(firstId) && Number.isFinite(secondId)) {
		return firstId - secondId;
	}

	const firstTime = Date.parse(firstMessage.date?.iso || firstMessage.created_at || '');
	const secondTime = Date.parse(secondMessage.date?.iso || secondMessage.created_at || '');

	if(Number.isFinite(firstTime) && Number.isFinite(secondTime) && firstTime !== secondTime) {
		return firstTime - secondTime;
	}

	return 0;
}

function mergeMessageLists(currentMessages = [], incomingMessages = []) {
	const messageMap = new Map();

	currentMessages.concat(incomingMessages).forEach((messageData) => {
		const clientUid = messageData?.meta?.client_uid || messageData?.meta?.local_outgoing?.client_uid || null;
		const messageKey = clientUid ? `client:${clientUid}` : (messageData?.id ? `id:${messageData.id}` : null);

		if(messageKey) {
			messageMap.set(messageKey, messageData);
		}
	});

	return Array.from(messageMap.values()).sort(compareMessagesByTimeOrId);
}

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
			chatDataChatId: null,
			chatMessages: [],
			chatMessagesChatId: null,
			chatMessagesLoaded: false,
			chatMessagesFetchedAt: 0,
			chatMessagesPagination: createMessagesPagination(),
			chatParticipants: [],
			chatDataRequestToken: 0,
			chatMessagesRequestToken: 0,
			chatParticipantsRequestToken: 0,
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
		resetChatState: function(chatId = null) {
			this.chatId = chatId;
			this.chatData = {};
			this.chatDataChatId = null;
			this.chatMessages = [];
			this.chatMessagesChatId = chatId;
			this.chatMessagesLoaded = false;
			this.chatMessagesFetchedAt = 0;
			this.chatMessagesPagination = createMessagesPagination();
			this.chatParticipants = [];
		},
		clearChatSlicesForTarget: function(chatId) {
			if(this.chatDataChatId !== chatId) {
				this.chatData = {};
				this.chatDataChatId = null;
				this.chatParticipants = [];
			}

			if(this.chatMessagesChatId !== chatId) {
				this.chatMessages = [];
				this.chatMessagesChatId = chatId;
				this.chatMessagesLoaded = false;
				this.chatMessagesFetchedAt = 0;
				this.chatMessagesPagination = createMessagesPagination();
			}
		},
		prepareChatForRoute: function(chatId, options = {}) {
			const { preferCache = true, primeChatData = null } = options;

			if(! chatId) {
				this.resetChatState(null);
				this.inboxStore.setActiveChatId(null);

				return false;
			}

			this.chatDataRequestToken++;
			this.chatMessagesRequestToken++;
			this.chatParticipantsRequestToken++;
			this.chatId = chatId;
			this.inboxStore.setActiveChatId(chatId);
			this.clearChatSlicesForTarget(chatId);

			const hasCachedChat = preferCache ? this.hydrateChatMessagesCache(chatId) : false;

			if(primeChatData?.chat_id === chatId && ! this.chatData?.chat_info) {
				this.primeChatDataFromInbox(primeChatData);
			}

			return hasCachedChat || Boolean(primeChatData?.chat_id === chatId);
		},
		hydrateChatMessagesCache: function(chatId = this.chatId) {
			let payload = readChatCache(chatId);

			if(! payload) {
				return false;
			}

			if(payload.data?.chatData?.chat_info) {
				this.chatData = payload.data.chatData;
				this.chatDataChatId = chatId;
			}

			this.chatMessages = Array.isArray(payload.data?.messages) ? payload.data.messages : [];
			this.chatMessagesChatId = chatId;
			this.chatMessagesLoaded = true;
			this.chatMessagesFetchedAt = payload.timestamp || Date.now();
			this.chatMessagesPagination = {
				...createMessagesPagination(),
				...(payload.data?.pagination || {})
			};

			return true;
		},
		primeChatDataFromInbox: function(chatData = {}) {
			if(! chatData?.chat_id) {
				return false;
			}

			this.chatId = chatData.chat_id;
			this.chatData = chatData;
			this.chatDataChatId = chatData.chat_id;
			this.inboxStore.setActiveChatId(chatData.chat_id);

			let payload = readChatCache(chatData.chat_id);
			let messages = Array.isArray(payload?.data?.messages) ? payload.data.messages : [];

			if(! messages.length && this.chatMessagesChatId === chatData.chat_id) {
				messages = this.chatMessages;
			}

			writeChatCache(chatData.chat_id, {
				chatData: chatData,
				messages: messages.slice(-50),
				pagination: payload?.data?.pagination || createMessagesPagination()
			});

			return true;
		},
		persistChatMessagesCache: function(chatId = this.chatId) {
			if(! chatId) {
				return false;
			}

			let payload = readChatCache(chatId);
			let cachedChatData = payload?.data?.chatData || {};
			let cachedMessages = Array.isArray(payload?.data?.messages) ? payload.data.messages : [];
			let cachedPagination = payload?.data?.pagination || createMessagesPagination();
			let chatData = this.chatDataChatId === chatId ? this.chatData : cachedChatData;
			let messages = this.chatMessagesChatId === chatId ? this.chatMessages : cachedMessages;
			let pagination = this.chatMessagesChatId === chatId ? this.chatMessagesPagination : cachedPagination;

			this.chatMessagesLoaded = true;
			this.chatMessagesFetchedAt = Date.now();
			this.chatMessagesChatId = chatId;

			writeChatCache(chatId, {
				chatData: chatData,
				messages: messages.slice(-50),
				pagination: pagination
			});
		},
		fetchChatData: async function(chatId) {
			const targetChatId = chatId || this.chatId;
			const requestToken = ++this.chatDataRequestToken;

			if(! targetChatId) {
				return this.chatData;
			}

			await colibriAPI().messenger().getFrom(`chat/${targetChatId}`).then((response) => {
				if(requestToken !== this.chatDataRequestToken || this.chatId !== targetChatId) {
					return;
				}

				this.chatData = response.data.data;
				this.chatDataChatId = targetChatId;

				this.chatId = targetChatId;
				this.persistChatMessagesCache(targetChatId);
			}).catch((error) => {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});

			return this.chatData;
		},
		fetchChatParticipants: async function() {
			const targetChatId = this.chatId;
			const requestToken = ++this.chatParticipantsRequestToken;

			if(! targetChatId) {
				return this.chatParticipants;
			}

			await colibriAPI().messenger().getFrom(`chat/${targetChatId}/participants`).then((response) => {
				if(requestToken !== this.chatParticipantsRequestToken || this.chatId !== targetChatId) {
					return;
				}

				this.chatParticipants = response.data.data;
			}).catch((error) => {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});

			return this.chatParticipants;
		},
		fetchChatMessages: async function(options = {}) {
			let {
				force = false,
				preferCache = true,
				beforeId = null,
				limit = MESSAGE_PAGE_SIZE
			} = options;
			const targetChatId = this.chatId;
			const isLoadingOlder = Boolean(beforeId);
			const requestToken = ++this.chatMessagesRequestToken;
			let hasCache = false;

			if(! targetChatId) {
				return this.chatMessages;
			}

			if(preferCache && ! force && ! isLoadingOlder) {
				if(this.chatMessagesLoaded && this.chatMessagesChatId === targetChatId && isFresh(this.chatMessagesFetchedAt) && this.chatMessages.length) {
					hasCache = true;
				}
				else {
					hasCache = this.hydrateChatMessagesCache(targetChatId);
				}
			}

			if(isLoadingOlder) {
				this.chatMessagesPagination.isLoadingOlder = true;
			}

			return await colibriAPI().messenger().params({
				limit: limit,
				...(beforeId ? { before_id: beforeId } : {})
			}).getFrom(`chat/${targetChatId}/messages`).then((response) => {
				if(requestToken !== this.chatMessagesRequestToken || this.chatId !== targetChatId) {
					return this.chatMessages;
				}

				const responseMessages = response.data.data || [];
				const responsePagination = response.data.meta?.pagination || {};

				this.chatMessages = mergeMessageLists(isLoadingOlder ? responseMessages : this.chatMessages, isLoadingOlder ? this.chatMessages : responseMessages);
				this.chatMessagesChatId = targetChatId;
				this.chatMessagesLoaded = true;
				this.chatMessagesFetchedAt = Date.now();
				this.chatMessagesPagination = {
					...this.chatMessagesPagination,
					hasMore: Boolean(responsePagination.has_more),
					nextBeforeId: responsePagination.next_before_id || this.chatMessages[0]?.id || null,
					isLoadingOlder: false
				};
				this.persistChatMessagesCache();

				return this.chatMessages;
			}).catch((error) => {
				if(isLoadingOlder) {
					this.chatMessagesPagination.isLoadingOlder = false;
				}

				if(! hasCache && ! this.chatMessagesLoaded) {
					this.chatMessages = [];
				}

				if(error.response && ! hasCache) {
					throw new Error(error.response.data.message);
				}

				return this.chatMessages;
			});
		},
		fetchOlderMessages: async function(options = {}) {
			const beforeId = this.chatMessagesPagination.nextBeforeId || this.chatMessages[0]?.id || null;

			if(! beforeId || this.chatMessagesPagination.isLoadingOlder || ! this.chatMessagesPagination.hasMore) {
				return this.chatMessages;
			}

			return await this.fetchChatMessages({
				...options,
				preferCache: false,
				beforeId: beforeId
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
			const targetChatId = this.chatId;
			const clientUid = messageData.client_uid || `msg-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
			const optimisticMessage = createOptimisticOutgoingMessage({
				chatId: targetChatId,
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
					chat_id: targetChatId,
					...messageData,
					client_uid: clientUid,
				}).sendTo('send');

				if(response.data.data) {
					if(this.chatId === targetChatId) {
						this.upsertMessage(response.data.data);
					}
					else {
						this.inboxStore.updateChatFromMessage(response.data.data, useAuthStore().userData.id, this.chatId);
					}
				}
			}
			catch(error) {
				if(this.chatId === targetChatId) {
					this.removeMessage(optimisticMessage.id);
				}

				if(error.response) {
					throw new Error(error.response.data.message);
				}

				throw error;
			}
		},
        sendMediaMessage: async function(mediaData) {
            const formData = new FormData();
            const dateTime = new Date().toISOString();
            const targetChatId = this.chatId;

            formData.append('chat_id', targetChatId);
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
                    if(this.chatId === targetChatId) {
                        this.upsertMessage(response.data.data);
                    }
                    else {
                        this.inboxStore.updateChatFromMessage(response.data.data, useAuthStore().userData.id, this.chatId);
                    }
                }
            }).catch(function(error) {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
        sendAudioMessage: async function(audioData = {}) {
            const authStore = useAuthStore();
            const targetChatId = this.chatId;
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
                chatId: targetChatId,
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
                    chat_id: targetChatId,
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
                    if(this.chatId === targetChatId) {
                        this.upsertMessage(uploadResponse.data.data);
                    }
                    else {
                        this.inboxStore.updateChatFromMessage(uploadResponse.data.data, authStore.userData.id, this.chatId);
                    }
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
                this.persistChatMessagesCache();

                if(error.response) {
                    throw new Error(error.response.data.message);
                }

                throw error;
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

				this.persistChatMessagesCache();
			}
		},
        removeMessage: function(messageId) {
            const messageIndex = this.chatMessages.findIndex((item) => {
                return item.id == messageId;
            });

            if(messageIndex !== -1) {
                this.chatMessages.splice(messageIndex, 1);
                this.persistChatMessagesCache();
            }
        },
        replaceTemporaryMessage: function(tempMessageId, messageData = {}) {
            if(messageData?.chat_uuid && this.chatId && messageData.chat_uuid !== this.chatId) {
                this.inboxStore.updateChatFromMessage(messageData, useAuthStore().userData.id, this.chatId);

                return false;
            }

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
            this.persistChatMessagesCache();
        },
        setLocalAudioState: function(messageId, localAudioState = null) {
            const messageIndex = this.chatMessages.findIndex((item) => {
                return item.id == messageId;
            });

            if(messageIndex === -1) {
                return false;
            }

            this.chatMessages.splice(messageIndex, 1, withLocalAudioState(this.chatMessages[messageIndex], localAudioState));
            this.persistChatMessagesCache();

            return true;
        },
		upsertMessage: function(messageData = {}) {
			if(messageData?.chat_uuid && this.chatId && messageData.chat_uuid !== this.chatId) {
				this.inboxStore.updateChatFromMessage(messageData, useAuthStore().userData.id, this.chatId);

				return false;
			}

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
				this.persistChatMessagesCache();
			}
		},
		appendMessage: function(messageData = {}) {
			if(messageData?.chat_uuid && this.chatId && messageData.chat_uuid !== this.chatId) {
				this.inboxStore.updateChatFromMessage(messageData, useAuthStore().userData.id, this.chatId);

				return false;
			}

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
