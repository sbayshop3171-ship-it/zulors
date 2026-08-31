<template>
	<div class="chat-layout fixed inset-0 bg-bg-pr mb-safe-bottom">
		<div class="chat-header-wrapper shrink-0">
			<ChatHeader v-if="hasChatData" v-on:close="$router.push({ name: 'messenger_inbox' })" v-bind:chatData="chatData" v-bind:typingUser="state.typing"></ChatHeader>
			<div v-else class="chat-header mobile-safe-chat-header flex items-center leading-none gap-2.5">
				<button type="button" class="size-10 flex items-center justify-center text-lab-pr" v-on:click="$router.push({ name: 'messenger_inbox' })">
					<SvgIcon type="line" name="chevron-left" classes="size-icon-medium"></SvgIcon>
				</button>
				<span class="text-par-m text-lab-pr">{{ $t('labels.message') }}</span>
				<div class="size-10 shrink-0"></div>
			</div>
            <Soundbar v-if="hasChatData"></Soundbar>
            <Border></Border>
		</div>
		<div class="chat-messages-container" ref="messagesHistoryContainer" v-on:load.capture="scrollHistoryDownSettled" v-on:loadedmetadata.capture="scrollHistoryDownSettled" v-on:canplay.capture="scrollHistoryDownSettled">
			<div v-if="chatMessages.length" class="flex min-h-full flex-col justify-end py-4">
				<div v-for="messageData in chatMessages" class="block">
					<ChatMessage v-on:delete="handleMessageDelete"
						v-on:copy="handleMessageCopy"
					v-bind:messageData="messageData"
					v-bind:key="messageData.id"></ChatMessage>
				</div>
				<ChatMessageTyping v-bind:typingUser="state.typing"></ChatMessageTyping>
			</div>
			<div v-else class="flex h-full items-center justify-center py-4">
				<p class="text-par-s text-lab-sc">
					{{  $t('chat.no_messages_found') }}
				</p>
			</div>
		</div>
		<div class="chat-input-footer shrink-0" v-bind:class="{ 'pb-safe-bottom': $isStandalone() }">
            <ChatEditorLock v-if="isEditorBlocked"></ChatEditorLock>
			<ChatEditor v-else-if="canRenderEditor" v-on:typing="handleMessageTyping"></ChatEditor>
			<div v-else class="relative z-20 pb-3 px-4 pt-3"></div>
		</div>
	</div>
</template>

<style scoped>
	.chat-layout {
		display: flex;
		flex-direction: column;
		height: var(--app-viewport-height, 100dvh);
		min-height: var(--app-viewport-height, 100dvh);
		overflow: hidden;
		position: fixed;
		inset: 0;
		background: var(--bg-pr, #f8f8f8);
		transform: translateZ(0);
		-webkit-transform: translateZ(0);
		padding-top: max(0px, var(--mobile-safe-top, env(safe-area-inset-top, 0px)));
		padding-bottom: max(0px, env(safe-area-inset-bottom, 0px));
	}

	.chat-header-wrapper,
	.chat-input-footer {
		flex-shrink: 0;
		margin-bottom: 0;
		padding-bottom: max(0px, env(safe-area-inset-bottom, 0px));
		background: var(--bg-pr, #ffffff);
	}

	.chat-header-wrapper {
		position: sticky;
		top: max(0px, var(--mobile-safe-top, env(safe-area-inset-top, 0px)));
		z-index: 25;
	}

	.chat-messages-container {
		flex: 1 1 auto;
		min-height: 0;
		overflow-y: auto;
		overflow-x: hidden;
		-webkit-overflow-scrolling: touch;
		overflow-scrolling: touch;
		overscroll-behavior: contain;
	}

	.chat-header {
		position: sticky;
		top: 0;
		z-index: 20;
		flex-shrink: 0;
	}

	@supports not (height: 100dvh) {
		.chat-layout {
			height: var(--app-viewport-height, 100vh);
			min-height: var(--app-viewport-height, 100vh);
		}
	}
</style>

<script>
	import { defineComponent, reactive, ref, computed, onMounted, nextTick, onUnmounted, watch } from 'vue';

	import { useRoute, useRouter } from 'vue-router';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { useChatStore } from '@M/store/chats/chat.store.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useCallStore } from '@M/store/calls/call.store.js';
	import BRD from '@/kernel/websockets/brd/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';

	import ChatMessage from '@M/views/messenger/children/chat/parts/ChatMessage.vue';
	import ChatHeader from '@M/views/messenger/children/chat/parts/ChatHeader.vue';
	import ChatEditor from '@M/views/messenger/children/chat/parts/ChatEditor.vue';
	import ChatMessageTyping from '@M/views/messenger/children/chat/parts/ChatMessageTyping.vue';
	import Soundbar from '@M/components/soundbar/Soundbar.vue';
	import ChatEditorLock from '@M/views/messenger/children/chat/parts/ChatEditorLock.vue';

	export default defineComponent({
		setup: function() {
			const route = useRoute();
			const router = useRouter();
			const messagesHistoryContainer = ref(null);
			const authStore = useAuthStore();
			const callStore = useCallStore();

			const chatStore = useChatStore();

			const chatChannel = ref(null);
			const scrollTimers = [];
			let resizeObserver = null;
			let activeLoadToken = 0;

			const userData = computed(() => {
                return authStore.userData;
            });

			const chatMessages = computed(() => {
                return chatStore.chatMessages;
            });

			const chatData = computed(() => {
                return chatStore.chatData;
            });

			const state = reactive({
                isLoading: true,
                typing: BRD.createEmptyTypingState(),
                realtimeReady: false
            });
			const remoteTyping = BRD.createIncomingTypingController((nextState) => {
				state.typing = nextState;
			});

			const initializeRouteChat = () => {
				let chatId = route.params.chat_id;

				if(chatId && chatStore.chatId !== chatId) {
					chatStore.chatData = {};
					chatStore.chatMessages = [];
					chatStore.chatMessagesLoaded = false;
				}

				chatStore.chatId = chatId;
				chatStore.hydrateChatMessagesCache(chatId);
			}

			initializeRouteChat();

			const hasChatData = computed(() => {
				return Boolean(chatData.value?.chat_info);
			});

			const isEditorBlocked = computed(() => {
				return Boolean(chatData.value?.chat_info?.meta?.relationship?.block?.blocking);
			});

			const canRenderEditor = computed(() => {
				return Boolean(chatStore.chatId);
			});

			const clearScrollTimers = () => {
				while(scrollTimers.length) {
					window.clearTimeout(scrollTimers.pop());
				}
			}

			const observeHistorySize = () => {
				if(! window.ResizeObserver || ! messagesHistoryContainer.value) {
					return false;
				}

				if(resizeObserver) {
					resizeObserver.disconnect();
				}

				resizeObserver = new ResizeObserver(() => {
					scrollHistoryDown();
				});

				resizeObserver.observe(messagesHistoryContainer.value);

				if(messagesHistoryContainer.value.firstElementChild) {
					resizeObserver.observe(messagesHistoryContainer.value.firstElementChild);
				}
			}

			const runScrollToBottom = (behavior = 'auto') => {
				if(messagesHistoryContainer.value) {
					messagesHistoryContainer.value.scrollTop = messagesHistoryContainer.value.scrollHeight;

					messagesHistoryContainer.value.scrollTo({
						top: messagesHistoryContainer.value.scrollHeight,
						behavior: behavior
					});
				}
			}

			const scrollHistoryDown = function(behavior = 'auto') {
                nextTick(() => {
					runScrollToBottom(behavior);

					window.requestAnimationFrame(() => {
						runScrollToBottom(behavior);
					});
                });
            }

			const scrollHistoryDownSettled = function() {
				clearScrollTimers();
				scrollHistoryDown();

				[60, 180, 420, 900, 1500, 2500].forEach((delay) => {
					scrollTimers.push(window.setTimeout(() => {
						scrollHistoryDown();
					}, delay));
				});
			}

			const stopListenEventInChat = (eventName) => {
                if(chatChannel.value && window.ColibriBRD) {
                    ColibriBRD.private(chatChannel.value).stopListening(eventName);
                }
            }

            const listenEventInChat = (eventName, callback) => {
                if(chatChannel.value && window.ColibriBRD) {
                    ColibriBRD.private(chatChannel.value).listen(eventName, callback);
                }
            }

			const whisperToChat = (payload) => {
				if(chatChannel.value && window.ColibriBRD) {
					ColibriBRD.private(chatChannel.value).whisper(BRD.getEvent('CHAT_MESSAGE_TYPING'), payload);
				}
            }

			const outgoingTyping = BRD.createOutgoingTypingController((payload) => {
				whisperToChat(payload);
			});

			const detachRealtimeListeners = () => {
				if(state.realtimeReady) {
					stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_RECEIVED'));
					stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_READ'));
					stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_DELETED'));
					stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_REACTIONS_UPDATED'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MEDIA_READY'));

					if(chatChannel.value && window.ColibriBRD) {
						ColibriBRD.private(chatChannel.value).stopListeningForWhisper(BRD.getEvent('CHAT_MESSAGE_TYPING'));
					}

					remoteTyping.stop();

					state.realtimeReady = false;
				}

				if(! callStore.isVisible) {
					callStore.detachRealtimeChannel();
				}
			}

			const attachRealtimeListeners = () => {
				if(! window.ColibriBRD || ! chatChannel.value) {
					return false;
				}

				if(! chatData.value?.is_group && (chatData.value?.chat_id || chatStore.chatId)) {
					callStore.attachRealtimeChannel(chatData.value?.chat_id || chatStore.chatId);
				}

				if(state.realtimeReady) {
					return true;
				}

				listenEventInChat(BRD.getEvent('CHAT_MESSAGE_RECEIVED'), function (event) {
					let messageData = event.data;

					chatStore.upsertMessage(messageData);

					scrollHistoryDownSettled();

					if(userData.value.id != messageData.user_id) {
						colibriSounds.activeChatMessageReceived();
						chatStore.markMessagesAsRead();
					}
				});

				listenEventInChat(BRD.getEvent('CHAT_MESSAGE_DELETED'), function (event) {
                    const deletedMessage = chatStore.chatMessages.find((item) => {
                        return item.id == event.data.message_id;
                    });
                    const shouldRevalidateInbox = deletedMessage?.type === 'audio'
                        && deletedMessage?.relations?.media
                        && ! Array.isArray(deletedMessage.relations.media)
                        && deletedMessage.relations.media.status === 'processing';

					chatStore.markMessageAsDeleted(event.data.message_id);

                    if(shouldRevalidateInbox) {
                        chatStore.inboxStore.scheduleUnreadStateSync(0);
                    }

					scrollHistoryDownSettled();
				});

				listenEventInChat(BRD.getEvent('CHAT_MESSAGE_REACTIONS_UPDATED'), function (event) {
					chatStore.syncMessageReactions(event.data.message_id, event.data.reactions, event.data.actor_user_id);
					chatStore.persistChatMessagesCache();
					scrollHistoryDownSettled();
				});

                listenEventInChat(BRD.getEvent('CHAT_MEDIA_READY'), function (event) {
                    chatStore.upsertMessage(event.data);
                    scrollHistoryDownSettled();
                });

				ColibriBRD.private(chatChannel.value).listenForWhisper(BRD.getEvent('CHAT_MESSAGE_TYPING'), remoteTyping.receive);

				listenEventInChat(BRD.getEvent('CHAT_MESSAGE_READ'), function (event) {
					chatStore.updateLastReadMessageForParticipant(event.data);
				});

				state.realtimeReady = true;
			}

			const bindRealtimeChannel = (channelId) => {
				if(! channelId) {
					return false;
				}

				const nextChannel = BRD.getChannel('CHAT', [channelId]);

				if(chatChannel.value === nextChannel && state.realtimeReady) {
					return true;
				}

				detachRealtimeListeners();
				chatChannel.value = nextChannel;
				attachRealtimeListeners();

				return true;
			}

			const handleWSStatus = (event) => {
				if(event.detail.connected) {
					detachRealtimeListeners();
					bindRealtimeChannel(chatData.value?.chat_id || chatStore.chatId || route.params.chat_id);
					refreshActiveChat();
				}
			}

			const prepareChatForRoute = (chatId) => {
				state.typing = BRD.createEmptyTypingState();
				remoteTyping.stop();

				if(chatStore.chatId !== chatId) {
					chatStore.chatData = {};
					chatStore.chatMessages = [];
					chatStore.chatMessagesLoaded = false;
				}

				chatStore.chatId = chatId;
				const hasCachedMessages = chatStore.hydrateChatMessagesCache(chatId);

				state.isLoading = ! hasChatData.value && ! hasCachedMessages;
				nextTick(() => {
					observeHistorySize();
				});
				scrollHistoryDownSettled();
			}

			const loadChat = async (chatId) => {
				const loadToken = ++activeLoadToken;

				prepareChatForRoute(chatId);
				bindRealtimeChannel(chatId);

				await nextTick();
				observeHistorySize();
				scrollHistoryDownSettled();

				const [chatDataResult] = await Promise.allSettled([
						chatStore.fetchChatData(chatId),
						chatStore.fetchChatMessages({ preferCache: true })
				]);

				if(loadToken !== activeLoadToken) {
					return false;
				}

				if(chatDataResult.status === 'rejected' && ! hasChatData.value) {
					router.push({ name: 'messenger_inbox' });

					return false;
				}

				if (chatMessages.value.length > 0) {
					chatStore.markMessagesAsRead();
				}

				state.isLoading = false;

				bindRealtimeChannel(chatData.value?.chat_id || chatId);
				observeHistorySize();
				scrollHistoryDownSettled();
			}

			const refreshActiveChat = async () => {
				const chatId = route.params.chat_id;

				if(! chatId || chatStore.chatId !== chatId) {
					return false;
				}

				await Promise.allSettled([
					chatStore.fetchChatData(chatId),
					chatStore.fetchChatMessages({ force: true })
				]);

				if(chatMessages.value.length > 0) {
					chatStore.markMessagesAsRead();
				}

				bindRealtimeChannel(chatData.value?.chat_id || chatId);
				observeHistorySize();
				scrollHistoryDownSettled();
			}

			useInstantRevalidation(refreshActiveChat, {
				routeKey: () => {
					return route.params.chat_id;
				},
				interval: 10000,
				minDelay: 1500
			});

			const handleVisibilityRefresh = () => {
				if(document.visibilityState === 'visible') {
					refreshActiveChat();
				}
			}

			onMounted(async function() {
				window.addEventListener('colibri:ws-status', handleWSStatus);
				document.addEventListener('visibilitychange', handleVisibilityRefresh);
				window.addEventListener('focus', refreshActiveChat);
				await loadChat(route.params.chat_id);
			});

			onUnmounted(() => {
				window.removeEventListener('colibri:ws-status', handleWSStatus);
				document.removeEventListener('visibilitychange', handleVisibilityRefresh);
				window.removeEventListener('focus', refreshActiveChat);
				outgoingTyping.stop(null, { silent: true });
				remoteTyping.stop();
                detachRealtimeListeners();
				clearScrollTimers();

				if(resizeObserver) {
					resizeObserver.disconnect();
				}
            });

			watch(() => {
				return route.params.chat_id;
			}, (chatId) => {
				if(chatId) {
					loadChat(chatId);
				}
			});

			watch(() => {
				return chatMessages.value.length;
			}, (messagesCount, oldMessagesCount) => {
				if(messagesCount && messagesCount !== oldMessagesCount) {
					nextTick(() => {
						observeHistorySize();
					});
					scrollHistoryDownSettled();
				}
			});

			watch(() => {
				return state.typing.is_typing;
			}, () => {
				scrollHistoryDownSettled();
			});

			return {
				chatMessages: chatMessages,
				state: state,
				chatData: chatData,
				hasChatData: hasChatData,
				isEditorBlocked: isEditorBlocked,
				canRenderEditor: canRenderEditor,
				messagesHistoryContainer: messagesHistoryContainer,
				scrollHistoryDownSettled: scrollHistoryDownSettled,
				isTyping: computed(() => {
                    return state.typing.is_typing;
                }),
				handleMessageDelete: (messageData) => {
					const modalData = {
						title: __t('prompt.delete_message.title'),
                        description: (messageData.isSender ? __t('prompt.delete_message.description') : __t('prompt.delete_message_for_me.description')),
                        confirmButtonText: (messageData.isSender ? null : __t('prompt.delete_message_for_me.confirm')),
                        onConfirm: async () => {
                            await chatStore.deleteMessage(messageData.messageId);
                        }
					};

					if(messageData.isSender) {
						modalData.confirmSecondary = true;
						modalData.confirmSecondaryText = __t('chat.delete_message_for_all');
						modalData.onConfirmSecondary = async () => {
							await chatStore.deleteMessage(messageData.messageId, true);
						}
					}

                    colibriEventBus.emit('confirmation-modal:open', modalData);
                },
				handleMessageCopy: (messageData) => {
					try {
						navigator.clipboard.writeText(messageData.content).then(() => {
							toastSuccess(__t('toast.chat.message_text_copied'), 1000);
						});
					} catch (error) {
						toastError(error);
					}
				},
				handleMessageTyping: () => {
                    if(window.ColibriBRConnected) {
                        outgoingTyping.bump({
							name: userData.value.name,
							avatar_url: userData.value.avatar_url
						});
                    }
                }
			};
		},
		components: {
			ChatMessage: ChatMessage,
			ChatHeader: ChatHeader,
			ChatMessageTyping: ChatMessageTyping,
			ChatEditor: ChatEditor,
            Soundbar: Soundbar,
            ChatEditorLock: ChatEditorLock,
		}
	});
</script>
