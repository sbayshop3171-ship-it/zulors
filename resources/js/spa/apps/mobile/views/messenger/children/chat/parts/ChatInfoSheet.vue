<template>
	<ActionSheet v-on:close="$emit('close')" v-bind:isMuted="true">
		<div class="mb-4">
			<DirectChatOverview v-bind:chatData="chatData"></DirectChatOverview>
		</div>
		<div class="mb-4" v-on:click="$emit('close')">
			<ActionSheetGroup>
				<RouterLink v-bind:to="{ name: 'profile_index', params: { id: chatData.chat_info.username }}">
					<ActionSheetItem
						v-bind:notLast="true"
						v-bind:textLabel="$t('labels.view_profile')"
					iconName="arrow-up-right"></ActionSheetItem>
				</RouterLink>
				<ActionSheetItem
					v-on:click="$emit('open-participants')"
					v-bind:textLabel="$t('chat.participants_count', { count: chatData.chat_info.participants_count.formatted })"
				iconName="users-01"></ActionSheetItem>

				<ActionSheetItem
					v-on:click="clearChat"
					v-bind:textLabel="$t('chat.clear_conversation')"
				iconName="brush-03"></ActionSheetItem>
			</ActionSheetGroup>
		</div>
		<ActionSheetGroup v-on:click="$emit('close')">
			<ActionSheetItem
				v-on:click="reportChat"
				itemColor="text-red-900"
				v-bind:textLabel="$t('labels.report_this_user', { user_name: chatData.chat_info.name })"
			iconName="annotation-alert"></ActionSheetItem>

			<ActionSheetItem
				itemColor="text-red-900"
				v-on:click="deleteChat"
				v-bind:textLabel="$t('chat.delete_chat')"
			iconName="trash-04"></ActionSheetItem>
		</ActionSheetGroup>

		<div class="px-4 mt-4 text-center">
			<span class="text-lab-sc text-cap-l">
				{{ $t('chat.chat_created_date', { date: chatData.date.iso })}}
			</span>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useChatStore } from '@M/store/chats/chat.store.js';
	import { useRouter } from 'vue-router';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import AvatarMedium from '@M/components/general/avatars/AvatarMedium.vue';
	import DirectChatOverview from '@M/views/messenger/children/chat/parts/info/DirectChatOverview.vue';

	export default defineComponent({
		emits: ['close', 'open-participants'],
		props: {
			chatData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			const chatData = ref(props.chatData);
			const chatStore = useChatStore();
			const router = useRouter();
			return {
				clearChat: () => {
					colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.clear_conversation.title'),
                        description: __t('prompt.clear_conversation.desc'),
                        confirmButtonText: __t('prompt.clear_conversation.confirm'),
                        onConfirm: async () => {
                            try {
                                if(chatStore.chatMessages.length) {
                                    await chatStore.clearChat();
                                }

                                toastSuccess(__t('toast.chat.chat_cleared'), 3000);
                            } catch (error) {
                                toastError(error, 3000);
                            }
                        }
                    });
				},
				deleteChat: () => {
					colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.delete_chat.title'),
                        description: __t('prompt.delete_chat.desc'),
                        onConfirm: async () => {
                            try {
                                await chatStore.deleteChat();

                                router.push({
                                    name: 'messenger_index'
                                });

                                toastSuccess(__t('toast.chat.chat_deleted'), 3000);
                            } catch (error) {
                                toastError(error, 3000);
                            }
                        }
                    });
				},
				reportChat: () => {
                    colibriEventBus.emit('report:open', {
                        type: 'user',
                        reportableId: chatData.value.chat_info.id
                    });
				}
			};
		},
		components: {
			ActionSheet: ActionSheet,
			ActionSheetItem: ActionSheetItem,
			ActionSheetGroup: ActionSheetGroup,
			AvatarMedium: AvatarMedium,
			DirectChatOverview: DirectChatOverview
		}
	});
</script>
