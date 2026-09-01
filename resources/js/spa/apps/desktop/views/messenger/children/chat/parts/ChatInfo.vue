<template>
    <div class="block">
        <div class="py-4 px-4">
            <div class="mb-6">
                <PageTitle
                    v-bind:hasBack="true"
                    v-bind:navigateBack="false"
                    v-on:back="closeInfo"
                v-bind:titleText="$t('labels.information')"></PageTitle>
            </div>

            <ChatOverview></ChatOverview>
        </div>
        <Border height="h-3" bg="bg-fill-qt" opacity="opacity-70"></Border>
        <div class="py-4">
            <ChatParticipants v-bind:chatData="chatData"></ChatParticipants>
        </div>
        <Border height="h-3" bg="bg-fill-qt" opacity="opacity-70"></Border>

        <ModalRowButton v-if="chatData.meta.is_archived" v-on:click="unarchiveChat" v-bind:buttonText="$t('chat.unarchive_chat')" iconName="archive"></ModalRowButton>
        <ModalRowButton v-else v-on:click="archiveChat" v-bind:buttonText="$t('chat.archive_chat')" iconName="archive"></ModalRowButton>

        <ModalRowButton v-on:click="clearConversation" v-bind:buttonText="$t('chat.clear_conversation')" iconName="brush-03"></ModalRowButton>

        <ModalRowButton v-if="! isBlocked" v-on:click="reportInterlocutor" buttonRole="danger" v-bind:buttonText="$t('labels.report_this_user', { user_name: chatData.chat_info.name })" iconName="annotation-alert"></ModalRowButton>

        <!-- <ModalRowButton v-on:click="$comingSoon" buttonRole="danger" v-bind:buttonText="$t('labels.block_this_user', { user_name: chatData.chat_info.name })" iconName="slash-circle-01"></ModalRowButton> -->

        <ModalRowButton v-on:click="deleteChat" buttonRole="danger" v-bind:buttonText="isGroup ? $t('chat.delete_group') : $t('chat.delete_chat')" iconName="trash-04"></ModalRowButton>
        <Border height="h-3" bg="bg-fill-qt" opacity="opacity-70"></Border>
        <div class="px-4 py-4">
            <span class="text-lab-sc text-cap-l">
                {{ $t('chat.chat_created_date', { date: chatData.date.iso })}}
            </span>
        </div>
    </div>
</template>

<script>
	import { computed, defineComponent, onMounted, onUnmounted } from 'vue';
    import { useChatStore } from '@D/store/chats/chat.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { useRouter } from 'vue-router';
    import hotkeys from 'hotkeys-js';

    import ModalRowButton from '@D/components/inter-ui/buttons/ModalRowButton.vue';
    import ChatOverview from '@D/views/messenger/children/chat/parts/ChatOverview.vue';
    import ChatParticipants from '@D/views/messenger/children/chat/parts/participants/ChatParticipants.vue';
    import PageTitle from '@D/components/layout/PageTitle.vue';

	export default defineComponent({
        emits: ['close'],
		setup: function (props, context) {
            const router = useRouter();
            const chatStore = useChatStore();
            const chatData = computed(() => {
                return chatStore.chatData || {};
            });

            const closeInfo = () => {
                context.emit('close');
            }

            onMounted(() => {
                hotkeys('esc', () => {
                    closeInfo();
                });
            });

            onUnmounted(() => {
                hotkeys.unbind('esc');
            });

			return {
                chatData: chatData,
                closeInfo: closeInfo,
                isGroup: computed(() => {
                    return chatData.value.type == 'group';
                }),
                isBlocked: computed(() => {
                    return Boolean(chatData.value.chat_info?.meta?.relationship?.block?.blocking);
                }),
                archiveChat: async () => {
                    await chatStore.archiveChat(chatData.value.chat_id);

                    chatData.value.meta.is_archived = true;

                    toastSuccess(__t('toast.chat.chat_archived'));
                },
                unarchiveChat: async () => {
                    await chatStore.unarchiveChat(chatData.value.chat_id);

                    chatData.value.meta.is_archived = false;

                    toastSuccess(__t('toast.chat.chat_unarchived'));
                },
                clearConversation: () => {
                    closeInfo();

                    colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.clear_conversation.title'),
                        description: __t('prompt.clear_conversation.desc'),
                        confirmButtonText: __t('prompt.clear_conversation.confirm'),
                        onConfirm: async () => {
                            try {
                                if(chatStore.chatMessages.length) {
                                    await chatStore.clearChatConversation();
                                }

                                toastSuccess(__t('toast.chat.chat_cleared'), 3000);
                            } catch (error) {
                                toastError(error, 3000);
                            }
                        }
                    });
                },
                deleteChat: () => {
                    closeInfo();

                    colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.delete_chat.title'),
                        description: __t('prompt.delete_chat.desc'),
                        onConfirm: async () => {
                            try {
                                await chatStore.deleteChat(chatData.value.chat_id);

                                router.push({
                                    name: 'messenger_inbox'
                                });

                                toastSuccess(__t('toast.chat.chat_deleted'), 3000);
                            } catch (error) {
                                toastError(error, 3000);
                            }
                        }
                    });
                },
                reportInterlocutor: () => {
                    closeInfo();

                    colibriEventBus.emit('report:open', {
                        type: 'user',
                        reportableId: chatData.value.chat_info.id
                    });
                }
			};
		},
		components: {
            ChatOverview: ChatOverview,
            ModalRowButton: ModalRowButton,
            PageTitle: PageTitle,
            ChatParticipants: ChatParticipants
		}
	});
</script>
