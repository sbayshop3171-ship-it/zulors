<template>
    <div class="relative">
        <div class="flex h-screen">
            <div class="flex flex-1 min-h-0 h-full flex-col overflow-hidden">
                <div class="border-b border-bord-card shrink-0">
                    <ChatHeaderSkeleton v-if="state.isLoading"></ChatHeaderSkeleton>
                    <ChatHeader v-else v-bind:typingUser="state.typing"></ChatHeader>
                </div>
                <div ref="chatContainerBlock" class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto">
                    <div class="flex min-h-full flex-col py-4">
                        <div class="shrink-0 border-b border-bord-card py-8">
                            <ChatOverviewSkeleton v-if="state.isLoading"></ChatOverviewSkeleton>
                            <ChatOverview v-else></ChatOverview>
                        </div>
                        <div class="mt-auto pb-4 pt-2">
                            <div v-if="state.isLoading" class="flex flex-col gap-4 opacity-70">
                                <ChatMessageSkeleton v-for="i in 3"></ChatMessageSkeleton>
                            </div>
                            <div v-else>
                                <template v-if="chatMessages.length">
                                    <div v-for="(messageData, messageIndex) in chatMessages" class="block">
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
                                        v-bind:messageData="messageData"
                                        v-bind:key="messageData.id"></ChatMessage>
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
                    <ChatFormSkeleton v-if="state.isLoading"></ChatFormSkeleton>
                    <template v-else>
                        <template v-if="! isGroup && chatData.chat_info.meta.relationship.block.blocking">
                            <ChatFormLock></ChatFormLock>
                        </template>
                        <template v-else>
                            <ChatForm v-on:typing="handleMessageTyping"></ChatForm>
                        </template>
                    </template>
                </div>
            </div>

        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, nextTick, onMounted, reactive, computed, onUnmounted } from 'vue';
    import { colibriSounds } from '@/kernel/services/sounds/index.js';
    import { useRoute, useRouter } from 'vue-router';
    import { useChatStore } from '@D/store/chats/chat.store.js';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useCallStore } from '@D/store/calls/call.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';

    import ChatHeader from '@D/views/messenger/children/chat/parts/ChatHeader.vue';
    import ChatHeaderSkeleton from '@D/views/messenger/children/chat/parts/skeletons/ChatHeaderSkeleton.vue';
    import ChatFormSkeleton from '@D/views/messenger/children/chat/parts/skeletons/ChatFormSkeleton.vue';
    import ChatOverviewSkeleton from '@D/views/messenger/children/chat/parts/skeletons/ChatOverviewSkeleton.vue';
    import ChatMessageSkeleton from '@D/views/messenger/children/chat/parts/skeletons/ChatMessageSkeleton.vue';
    import ChatOverview from '@D/views/messenger/children/chat/parts/ChatOverview.vue';
    import ChatMessage from '@D/views/messenger/children/chat/parts/ChatMessage.vue';
    import ChatMessageTyping from '@D/views/messenger/children/chat/parts/ChatMessageTyping.vue';
    import ChatForm from '@D/views/messenger/children/chat/parts/ChatForm.vue';
    import ChatFormLock from '@D/views/messenger/children/chat/parts/ChatFormLock.vue';

    import BRD from '@/kernel/websockets/brd/index.js';

    export default defineComponent({
        setup: function(props, context) {
            const state = reactive({
                isLoading: true,
                typing: {
                    is_typing: false,
                    user: null
                },
                realtimeReady: false
            });

            const authStore = useAuthStore();
            const chatStore = useChatStore();
            const callStore = useCallStore();
            const router = useRouter();
            const route = useRoute();
            const userData = ref(authStore.userData);
            const chatData = computed(() => {
                return chatStore.chatData;
            });

            const isGroup = computed(() => {
                return chatData.value.is_group;
            });

            const chatMessages = computed(() => {
                return chatStore.chatMessages;
            });

            const chatContainerBlock = ref(null);

            const scrollHistoryDown = function() {
                nextTick(() => {
                    if(chatContainerBlock.value) {
                        chatContainerBlock.value.scrollTop = chatContainerBlock.value.scrollHeight;
                    }
                });
            }

            const refreshActiveChat = async () => {
                const chatId = route.params.chat_id;

                if(! chatId || chatStore.chatId !== chatId) {
                    return false;
                }

                await Promise.allSettled([
                    chatStore.fetchChatData(chatId),
                    chatStore.fetchChatMessages()
                ]);

                if(chatMessages.value.length > 0) {
                    chatStore.markMessagesAsRead();
                }

                attachRealtimeListeners();
                scrollHistoryDown();
            }

            useInstantRevalidation(refreshActiveChat, {
                routeKey: () => {
                    return route.params.chat_id;
                },
                interval: 10000,
                minDelay: 1500
            });

            onUnmounted(() => {
                window.removeEventListener('colibri:ws-status', handleWSStatus);
                detachRealtimeListeners();
            });

            onMounted(async function() {
                try {
                    await chatStore.fetchChatData(route.params.chat_id);
                    await chatStore.fetchChatMessages();

                    if (chatMessages.value.length > 0) {
                        chatStore.markMessagesAsRead();

                        debounce(() => {
                            scrollHistoryDown();
                        }, 500);
                    }

                    state.isLoading = false;

                    window.addEventListener('colibri:ws-status', handleWSStatus);
                    attachRealtimeListeners();
                } catch (error) {
                    router.push({
                        name: 'error_404',
                        params: { pathMatch: route.path.substring(1).split('/') },
                        query: route.query,
                        hash: route.hash
                    });
                }
            });

            const getChatChannel = () => {
                if(chatData.value?.chat_id) {
                    return BRD.getChannel('CHAT', [chatData.value.chat_id]);
                }

                return null;
            }

            const stopListenEventInChat = (eventName) => {
                const chatChannel = getChatChannel();

                if(chatChannel && window.ColibriBRD) {
                    ColibriBRD.private(chatChannel).stopListening(eventName);
                }
            }

            const listenEventInChat = (eventName, callback) => {
                const chatChannel = getChatChannel();

                if(chatChannel && window.ColibriBRD) {
                    ColibriBRD.private(chatChannel).listen(eventName, callback);
                }
            }

            const stopListeningForWhisperInChat = (whisperEvent) => {
                const chatChannel = getChatChannel();

                if(chatChannel && window.ColibriBRD) {
                    ColibriBRD.private(chatChannel).stopListeningForWhisper(whisperEvent);
                }
            }

            const listenWhisperInChat = (whisperEvent, callback) => {
                const chatChannel = getChatChannel();

                if(chatChannel && window.ColibriBRD) {
                    ColibriBRD.private(chatChannel).listenForWhisper(whisperEvent, callback);
                }
            }

            const whisperToChat = (whisperEvent, eventData) => {
                const chatChannel = getChatChannel();

                if(chatChannel && window.ColibriBRD) {
                    ColibriBRD.private(chatChannel).whisper(whisperEvent, eventData);
                }
            }

            const detachRealtimeListeners = () => {
                if(state.realtimeReady) {
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_RECEIVED'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_READ'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_DELETED'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_REACTIONS_UPDATED'));
                    stopListeningForWhisperInChat(BRD.getEvent('CHAT_MESSAGE_TYPING'));

                    state.realtimeReady = false;
                }

                if(! callStore.isVisible) {
                    callStore.detachRealtimeChannel();
                }
            }

            const attachRealtimeListeners = () => {
                if(! window.ColibriBRD || ! getChatChannel()) {
                    return false;
                }

                if(! chatData.value?.is_group && chatData.value?.chat_id) {
                    callStore.attachRealtimeChannel(chatData.value.chat_id);
                }

                if(state.realtimeReady) {
                    return true;
                }

                listenEventInChat(BRD.getEvent('CHAT_MESSAGE_RECEIVED'), function (event) {
                    let messageData = event.data;

                    chatStore.upsertMessage(messageData);

                    scrollHistoryDown();

                    let isSender = (userData.value.id == messageData.user_id);

                    if(! isSender) {
                        colibriSounds.activeChatMessageReceived();
                        chatStore.markMessagesAsRead();
                    }
                });

                listenEventInChat(BRD.getEvent('CHAT_MESSAGE_DELETED'), function (event) {
                    chatStore.markMessageAsDeleted(event.data.message_id);
                });

                listenEventInChat(BRD.getEvent('CHAT_MESSAGE_REACTIONS_UPDATED'), function (event) {
                    chatStore.syncMessageReactions(event.data.message_id, event.data.reactions, event.data.actor_user_id);
                });

                listenWhisperInChat(BRD.getEvent('CHAT_MESSAGE_TYPING'), function (event) {
                    state.typing = event.data;
                });

                listenEventInChat(BRD.getEvent('CHAT_MESSAGE_READ'), function (event) {
                    chatStore.updateLastReadMessageForParticipant(event.data);
                });

                state.realtimeReady = true;
            }

            const handleWSStatus = (event) => {
                if(event.detail.connected) {
                    detachRealtimeListeners();
                    attachRealtimeListeners();
                    refreshActiveChat();
                }
            }

            const broadcastTyping = (isTyping) => {
                whisperToChat(BRD.getEvent('CHAT_MESSAGE_TYPING'), {
                    data: {
                        user: {
                            name: userData.value.name,
                            avatar_url: userData.value.avatar_url
                        },
                        is_typing: isTyping
                    }
                });
            }

            return {
                state: state,
                chatData: chatData,
                chatMessages: chatMessages,
                isGroup: isGroup,
                chatContainerBlock: chatContainerBlock,
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
                        broadcastTyping(true);

                        debounce(() => {
                            broadcastTyping(false);
                        }, 1000);
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
            ChatHeaderSkeleton: ChatHeaderSkeleton,
            ChatFormSkeleton: ChatFormSkeleton,
            ChatOverviewSkeleton: ChatOverviewSkeleton,
            ChatMessageSkeleton: ChatMessageSkeleton,
            ChatFormLock: ChatFormLock,
        }
    });
</script>
