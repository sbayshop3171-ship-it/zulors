<template>
    <div class="relative">
        <div class="flex h-screen">
            <div class="flex flex-1 min-h-0 h-full flex-col overflow-hidden">
                <div class="border-b border-bord-card shrink-0">
                    <ChatHeader v-if="hasChatData" v-bind:typingUser="state.typing"></ChatHeader>
                    <div v-else class="h-16"></div>
                </div>
                <div
                    ref="chatContainerBlock"
                    class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto"
                    v-on:scroll.passive="handleHistoryScroll"
                    v-on:load.capture="handleMediaSettled"
                    v-on:loadedmetadata.capture="handleMediaSettled"
                    v-on:canplay.capture="handleMediaSettled">
                    <div class="flex min-h-full flex-col py-4">
                        <div v-if="hasChatData" class="shrink-0 border-b border-bord-card py-8">
                            <ChatOverview></ChatOverview>
                        </div>
                        <div class="mt-auto pb-4 pt-2">
                            <div>
                                <template v-if="chatMessages.length">
                                    <div v-for="(messageData, messageIndex) in chatMessages" v-bind:key="messageData.id" class="block">
                                        <div v-if="showDateSeparator(messageIndex)" class="py-4">
                                            <p class="text-par-n font-semibold text-lab-pr3 text-center">
                                                {{ messageData.date.date }}
                                            </p>
                                        </div>

                                        <ChatMessage
                                            v-on:delete="handleMessageDelete"
                                            v-on:reply="handleMessageReply"
                                            v-on:copy="handleMessageCopy"
                                            v-bind:isCompacted="isCompacted(messageIndex)"
                                        v-bind:messageData="messageData"></ChatMessage>
                                    </div>
                                </template>
                                <template v-else-if="state.isLoading">
                                    <div class="py-12 text-center">
                                        <p class="text-par-s text-lab-sc">
                                            {{ $t('labels.loading') }}...
                                        </p>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="py-12 text-center">
                                        <p class="text-par-s text-lab-sc">
                                            {{  $t('chat.no_messages_found') }}
                                        </p>
                                    </div>
                                </template>

                                <ChatMessageTyping v-if="isTyping" v-bind:typingUser="state.typing"></ChatMessageTyping>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="shrink-0">
                    <template v-if="! isGroup && chatData.chat_info?.meta?.relationship?.block?.blocking">
                        <ChatFormLock></ChatFormLock>
                    </template>
                    <template v-else>
                        <ChatForm v-on:typing="handleMessageTyping"></ChatForm>
                    </template>
                </div>
            </div>

        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, nextTick, onMounted, reactive, computed, onUnmounted, watch } from 'vue';
    import { colibriSounds } from '@/kernel/services/sounds/index.js';
    import { useRoute, useRouter } from 'vue-router';
    import { useChatStore } from '@D/store/chats/chat.store.js';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useCallStore } from '@D/store/calls/call.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';

    import ChatHeader from '@D/views/messenger/children/chat/parts/ChatHeader.vue';
    import ChatOverview from '@D/views/messenger/children/chat/parts/ChatOverview.vue';
    import ChatMessage from '@D/views/messenger/children/chat/parts/ChatMessage.vue';
    import ChatMessageTyping from '@D/views/messenger/children/chat/parts/ChatMessageTyping.vue';
    import ChatForm from '@D/views/messenger/children/chat/parts/ChatForm.vue';
    import ChatFormLock from '@D/views/messenger/children/chat/parts/ChatFormLock.vue';

    import BRD from '@/kernel/websockets/brd/index.js';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true,
                typing: BRD.createEmptyTypingState(),
                realtimeReady: false
            });

            const authStore = useAuthStore();
            const chatStore = useChatStore();
            const callStore = useCallStore();
            const router = useRouter();
            const route = useRoute();
            const chatContainerBlock = ref(null);
            const shouldStickToBottom = ref(true);
            let activeLoadToken = 0;
            let activeRealtimeChannel = null;
            let isPreservingScroll = false;

            const userData = computed(() => {
                return authStore.userData || {};
            });

            const chatData = computed(() => {
                return chatStore.chatData || {};
            });

            const hasChatData = computed(() => {
                return Boolean(chatData.value?.chat_info);
            });

            const isGroup = computed(() => {
                return Boolean(chatData.value?.is_group);
            });

            const chatMessages = computed(() => {
                return chatStore.chatMessages;
            });

            const remoteTyping = BRD.createIncomingTypingController((nextState) => {
                state.typing = nextState;
            });

            const getScrollSnapshot = () => {
                if(! chatContainerBlock.value) {
                    return null;
                }

                return {
                    scrollTop: chatContainerBlock.value.scrollTop,
                    scrollHeight: chatContainerBlock.value.scrollHeight
                };
            };

            const restoreScrollSnapshot = (snapshot) => {
                if(! snapshot) {
                    return false;
                }

                return nextTick(() => {
                    if(chatContainerBlock.value) {
                        chatContainerBlock.value.scrollTop = snapshot.scrollTop + (chatContainerBlock.value.scrollHeight - snapshot.scrollHeight);
                    }
                });
            };

            const isNearBottom = (threshold = 120) => {
                if(! chatContainerBlock.value) {
                    return true;
                }

                return (chatContainerBlock.value.scrollHeight - chatContainerBlock.value.scrollTop - chatContainerBlock.value.clientHeight) <= threshold;
            };

            const syncBottomAffinity = () => {
                shouldStickToBottom.value = isNearBottom();
            };

            const scrollHistoryDown = function(behavior = 'auto') {
                nextTick(() => {
                    if(chatContainerBlock.value) {
                        chatContainerBlock.value.scrollTo({
                            top: chatContainerBlock.value.scrollHeight,
                            behavior: behavior
                        });
                    }
                });
            };

            const handleMediaSettled = () => {
                if(shouldStickToBottom.value) {
                    scrollHistoryDown();
                }
            };

            const loadOlderMessages = async () => {
                if(state.isLoading || isPreservingScroll || ! chatStore.chatMessagesPagination.hasMore || chatStore.chatMessagesPagination.isLoadingOlder) {
                    return false;
                }

                const snapshot = getScrollSnapshot();

                isPreservingScroll = true;

                try {
                    await chatStore.fetchOlderMessages();
                    await restoreScrollSnapshot(snapshot);
                }
                finally {
                    isPreservingScroll = false;
                    syncBottomAffinity();
                }
            };

            const handleHistoryScroll = () => {
                syncBottomAffinity();

                if(chatContainerBlock.value && chatContainerBlock.value.scrollTop <= 96) {
                    loadOlderMessages();
                }
            };

            const stopListenEventInChat = (eventName) => {
                if(activeRealtimeChannel && window.ColibriBRD) {
                    ColibriBRD.private(activeRealtimeChannel).stopListening(eventName);
                }
            };

            const listenEventInChat = (eventName, callback) => {
                if(activeRealtimeChannel && window.ColibriBRD) {
                    ColibriBRD.private(activeRealtimeChannel).listen(eventName, callback);
                }
            };

            const stopListeningForWhisperInChat = (whisperEvent) => {
                if(activeRealtimeChannel && window.ColibriBRD) {
                    ColibriBRD.private(activeRealtimeChannel).stopListeningForWhisper(whisperEvent);
                }
            };

            const listenWhisperInChat = (whisperEvent, callback) => {
                if(activeRealtimeChannel && window.ColibriBRD) {
                    ColibriBRD.private(activeRealtimeChannel).listenForWhisper(whisperEvent, callback);
                }
            };

            const whisperToChat = (whisperEvent, eventData) => {
                if(activeRealtimeChannel && window.ColibriBRD) {
                    ColibriBRD.private(activeRealtimeChannel).whisper(whisperEvent, eventData);
                }
            };

            const detachRealtimeListeners = () => {
                if(state.realtimeReady) {
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_RECEIVED'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_READ'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_DELETED'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_REACTIONS_UPDATED'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MEDIA_READY'));
                    stopListeningForWhisperInChat(BRD.getEvent('CHAT_MESSAGE_TYPING'));
                    remoteTyping.stop();

                    state.realtimeReady = false;
                }

                if(! callStore.isVisible) {
                    callStore.detachRealtimeChannel();
                }
            };

            const attachRealtimeListeners = () => {
                if(! window.ColibriBRD || ! activeRealtimeChannel) {
                    return false;
                }

                if(! chatData.value?.is_group && (chatData.value?.chat_id || chatStore.chatId)) {
                    callStore.attachRealtimeChannel(chatData.value?.chat_id || chatStore.chatId);
                }

                if(state.realtimeReady) {
                    return true;
                }

                listenEventInChat(BRD.getEvent('CHAT_MESSAGE_RECEIVED'), function (event) {
                    const messageData = event.data;

                    if(messageData?.chat_uuid && messageData.chat_uuid !== chatStore.chatId) {
                        chatStore.upsertMessage(messageData);

                        return;
                    }

                    const wasNearBottom = isNearBottom();
                    const isSender = (userData.value.id == messageData.user_id);

                    chatStore.upsertMessage(messageData);

                    if(wasNearBottom || isSender) {
                        scrollHistoryDown(isSender ? 'smooth' : 'auto');
                    }

                    if(! isSender) {
                        colibriSounds.activeChatMessageReceived();
                        chatStore.markMessagesAsRead();
                    }
                });

                listenEventInChat(BRD.getEvent('CHAT_MESSAGE_DELETED'), function (event) {
                    const wasNearBottom = isNearBottom();
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

                    if(wasNearBottom) {
                        scrollHistoryDown();
                    }
                });

                listenEventInChat(BRD.getEvent('CHAT_MESSAGE_REACTIONS_UPDATED'), function (event) {
                    chatStore.syncMessageReactions(event.data.message_id, event.data.reactions, event.data.actor_user_id);
                });

                listenEventInChat(BRD.getEvent('CHAT_MEDIA_READY'), function (event) {
                    const wasNearBottom = isNearBottom();

                    chatStore.upsertMessage(event.data);

                    if(wasNearBottom || userData.value.id == event.data.user_id) {
                        scrollHistoryDown();
                    }
                });

                listenWhisperInChat(BRD.getEvent('CHAT_MESSAGE_TYPING'), remoteTyping.receive);

                listenEventInChat(BRD.getEvent('CHAT_MESSAGE_READ'), function (event) {
                    chatStore.updateLastReadMessageForParticipant(event.data);
                });

                state.realtimeReady = true;
            };

            const bindRealtimeChannel = (chatId) => {
                if(! chatId) {
                    return false;
                }

                const nextChannel = BRD.getChannel('CHAT', [chatId]);

                if(activeRealtimeChannel === nextChannel && state.realtimeReady) {
                    return true;
                }

                detachRealtimeListeners();
                activeRealtimeChannel = nextChannel;
                attachRealtimeListeners();

                return true;
            };

            const refreshActiveChat = async () => {
                const chatId = route.params.chat_id;

                if(! chatId || chatStore.chatId !== chatId) {
                    return false;
                }

                const loadToken = activeLoadToken;
                const wasNearBottom = isNearBottom();
                const snapshot = getScrollSnapshot();

                await Promise.allSettled([
                    chatStore.fetchChatData(chatId, { preferCache: false }),
                    chatStore.fetchChatMessages({ force: true, preferCache: false })
                ]);

                if(loadToken !== activeLoadToken || route.params.chat_id !== chatId) {
                    return false;
                }

                if(chatMessages.value.length > 0) {
                    chatStore.markMessagesAsRead();
                }

                state.isLoading = false;
                bindRealtimeChannel(chatData.value?.chat_id || chatId);

                if(wasNearBottom) {
                    scrollHistoryDown();
                }
                else {
                    restoreScrollSnapshot(snapshot);
                }
            };

            const handleWSStatus = (event) => {
                if(event?.detail?.connected) {
                    bindRealtimeChannel(chatData.value?.chat_id || chatStore.chatId || route.params.chat_id);
                    refreshActiveChat();
                }
            };

            const loadChat = async (chatId) => {
                if(! chatId) {
                    return false;
                }

                const loadToken = ++activeLoadToken;
                const inboxChatData = chatStore.inboxStore.findChatById(chatId);

                state.typing = BRD.createEmptyTypingState();
                remoteTyping.stop();
                shouldStickToBottom.value = true;

                chatStore.prepareChatForRoute(chatId, {
                    preferCache: true,
                    primeChatData: inboxChatData
                });

                state.isLoading = ! (chatStore.chatMessagesLoaded && chatStore.chatMessagesChatId === chatId);
                bindRealtimeChannel(chatId);

                await nextTick();

                if(chatMessages.value.length) {
                    scrollHistoryDown();
                }

                const [chatDataResult] = await Promise.allSettled([
                    chatStore.fetchChatData(chatId, { preferCache: true }),
                    chatStore.fetchChatMessages({ preferCache: true })
                ]);

                if(loadToken !== activeLoadToken || route.params.chat_id !== chatId) {
                    return false;
                }

                if(chatDataResult.status === 'rejected' && ! hasChatData.value) {
                    router.push({
                        name: 'error_404',
                        params: { pathMatch: route.path.substring(1).split('/') },
                        query: route.query,
                        hash: route.hash
                    });

                    return false;
                }

                if(chatMessages.value.length > 0) {
                    chatStore.markMessagesAsRead();
                }

                state.isLoading = false;
                bindRealtimeChannel(chatData.value?.chat_id || chatId);
                scrollHistoryDown();
            };

            useInstantRevalidation(refreshActiveChat, {
                routeKey: () => {
                    return route.params.chat_id;
                },
                interval: 10000,
                minDelay: 1500
            });

            const outgoingTyping = BRD.createOutgoingTypingController((payload) => {
                whisperToChat(BRD.getEvent('CHAT_MESSAGE_TYPING'), payload);
            });

            onMounted(async function() {
                window.addEventListener('colibri:ws-status', handleWSStatus);
                await loadChat(route.params.chat_id);
            });

            onUnmounted(() => {
                window.removeEventListener('colibri:ws-status', handleWSStatus);
                outgoingTyping.stop(null, { silent: true });
                remoteTyping.stop();
                detachRealtimeListeners();
            });

            watch(() => {
                return route.params.chat_id;
            }, (chatId, previousChatId) => {
                if(chatId && chatId !== previousChatId) {
                    loadChat(chatId);
                }
            });

            watch(() => {
                return chatMessages.value.length;
            }, (messagesCount, oldMessagesCount) => {
                if(! messagesCount || messagesCount === oldMessagesCount || isPreservingScroll) {
                    return;
                }

                const latestMessage = chatMessages.value[messagesCount - 1];
                const isSender = latestMessage && userData.value.id == latestMessage.user_id;

                if(shouldStickToBottom.value || isSender) {
                    scrollHistoryDown(isSender ? 'smooth' : 'auto');
                }
            });

            watch(() => {
                return state.typing.is_typing;
            }, () => {
                if(shouldStickToBottom.value) {
                    scrollHistoryDown();
                }
            });

            return {
                state: state,
                chatData: chatData,
                hasChatData: hasChatData,
                chatMessages: chatMessages,
                isGroup: isGroup,
                chatContainerBlock: chatContainerBlock,
                handleHistoryScroll: handleHistoryScroll,
                handleMediaSettled: handleMediaSettled,
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

                            toastSuccess(__t('toast.chat.message_deleted'), 1000);
                        },
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
                handleMessageReply: (messageData) => {
                    colibriEventBus.emit('messenger-message:reply', {
                        messageData: messageData
                    });
                },
                handleMessageCopy: (messageData) => {
                    navigator.clipboard.writeText(messageData.content).then(() => {
                        toastSuccess(__t('toast.chat.message_text_copied'), 1000);
                    });
                },
                handleMessageTyping: () => {
                    if(window.ColibriBRConnected) {
                        outgoingTyping.bump({
                            name: userData.value.name,
                            avatar_url: userData.value.avatar_url
                        });
                    }
                },
                showDateSeparator: (messageIndex) => {
                    if(messageIndex > 0 && chatMessages.value[messageIndex - 1].date.generic !== chatMessages.value[messageIndex].date.generic) {
                        return true;
                    }

                    return false;
                },
                isCompacted: (messageIndex) => {
                    if(messageIndex > 0 && chatMessages.value[messageIndex - 1].user_id === chatMessages.value[messageIndex].user_id) {
                        return true;
                    }

                    return false;
                }
            }
        },
        components: {
            ChatHeader: ChatHeader,
            ChatOverview: ChatOverview,
            ChatMessage: ChatMessage,
            ChatMessageTyping: ChatMessageTyping,
            ChatForm: ChatForm,
            ChatFormLock: ChatFormLock,
        }
    });
</script>
