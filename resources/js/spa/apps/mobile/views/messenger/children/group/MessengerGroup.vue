<template>
	<GroupHeader></GroupHeader>

	<template v-if="state.isLoading">
		<GroupSkeleton></GroupSkeleton>
	</template>

	<template v-else>
		<div class="bg-fill-qt min-h-screen">
			<div class="p-4">
				<div class="mb-4">
					<div class="flex justify-center mb-2">
						<div class="size-large-avatar relative">
							<AvatarLarge v-bind:hasBorder="false" v-bind:avatarSrc="groupData.avatar_url"></AvatarLarge>
						</div>
					</div>
					<div class="px-4 text-center">
						<h2 class="text-par-l font-bold text-lab-pr">
							<span v-html="groupData.name"></span> <VerificationBadge v-if="groupData.verified" size="sm"></VerificationBadge>
						</h2>
						<p class="text-par-m text-lab-sc">
							 {{ $t('labels.group') }} — {{ $t('chat.participants_count', { count: groupData.participants_count.formatted }) }}
						</p>
					</div>
				</div>
				<template v-if="hasDescription">
					<div class="bg-bg-pr rounded-2xl p-4">
						<h5 class="font-medium text-lab-sc mb-2 text-par-m">
							{{ $t('chat.about_group') }}
						</h5>
						<p class="text-par-l text-lab-pr2 markdown-text" v-html="$mdInline(groupData.description, { breaks: false })"></p>
					</div>
				</template>
			</div>

			<ActionSheetGroup>
				<ActionSheetItem v-on:click="state.groupParticipantsMenu.open" v-bind:textLabel="$t('chat.group_participants', { count: groupData.participants_count.formatted })" iconName="users-01"></ActionSheetItem>
				<template v-if="groupData.meta.is_participant || groupData.is_owner">
					<ActionSheetItem v-on:click="unarchiveChat" v-if="groupData.meta.is_archived" v-bind:disabled="state.isSubmitting" v-bind:textLabel="$t('chat.unarchive_chat')" iconName="inbox-01"></ActionSheetItem>
					<ActionSheetItem v-on:click="archiveChat" v-else v-bind:disabled="state.isSubmitting" v-bind:textLabel="$t('chat.archive_chat')" iconName="archive"></ActionSheetItem>
					<template v-if="groupData.is_owner">
						<ActionSheetItem v-on:click="deleteGroup" v-bind:disabled="state.isSubmitting" itemColor="text-red-900" v-bind:textLabel="$t('chat.delete_group')" iconName="trash-04"></ActionSheetItem>
					</template>
					<template v-else>
						<ActionSheetItem v-on:click="reportGroup" v-bind:disabled="state.isSubmitting" itemColor="text-red-900" v-bind:textLabel="$t('chat.report_group')" iconName="annotation-alert"></ActionSheetItem>
						<ActionSheetItem v-on:click="leaveGroup" v-bind:disabled="state.isSubmitting" v-bind:textLabel="$t('chat.leave_group')" itemColor="text-red-900" iconName="log-out-01" iconType="solid"></ActionSheetItem>
					</template>
				</template>
			</ActionSheetGroup>

			<template v-if="groupData.meta.is_invited">
				<div class="mt-4">
					<ActionSheetGroup>
						<ActionSheetItem v-on:click="acceptInvitation" v-bind:disabled="state.isSubmitting" v-bind:textLabel="$t('dd.chat_participant.accept_invitation')" iconName="users-check"></ActionSheetItem>
						<ActionSheetItem v-on:click="declineInvitation" v-bind:disabled="state.isSubmitting" itemColor="text-red-900" v-bind:textLabel="$t('dd.chat_participant.decline_invitation')" iconName="slash-circle-01" iconType="line"></ActionSheetItem>
					</ActionSheetGroup>
				</div>
			</template>

			<div class="px-4 py-4">
				<span class="text-lab-sc text-cap-l block text-center">
					{{ $t('chat.group_created_date', { date: groupData.date.iso })}}
				</span>
			</div>
		</div>
	</template>

	<GroupParticipants v-if="state.groupParticipantsMenu.status" v-bind:groupData="groupData" v-on:close="state.groupParticipantsMenu.close"></GroupParticipants>
</template>

<script>
	import { defineComponent, onMounted, computed, reactive } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useRouter } from 'vue-router';
	import { useGroupStore } from '@M/store/chats/group.store.js';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';
	import { useChatStore } from '@M/store/chats/chat.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

	import GroupHeader from '@M/views/messenger/children/group/parts/GroupHeader.vue';
	import GroupSkeleton from '@M/views/messenger/children/group/parts/skeletons/GroupSkeleton.vue';
	import AvatarLarge from '@M/components/general/avatars/AvatarLarge.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import GroupParticipants from '@M/views/messenger/children/group/parts/GroupParticipants.vue';

	export default defineComponent({
		props: {
			chat_id: {
				type: String,
				required: true
			}
		},
		setup: function(props) {
			const router = useRouter();
			const inboxStore = useInboxStore();
			const groupStore = useGroupStore();
			const chatStore = useChatStore();

			const state = reactive({
				isLoading: true,
				isSubmitting: false,
				groupParticipantsMenu: useMenu()
			});
			
			const groupData = computed(() => {
				return groupStore.groupData;
			});

			onMounted(async () => {
				await groupStore.fetchGroupData(props.chat_id);

				if(! groupData.value) {
					router.push({ name: 'error_404' });
				}
				else {
					state.isLoading = false;
				}
			});

			return {
				state: state,
				groupData: groupData,
				hasDescription: computed(() => {
					return groupData.value.description.length > 0;
				}),
				declineInvitation: async () => {
					colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.decline_invitation.title'),
                        description: __t('prompt.decline_invitation.description'),
						confirmButtonText: __t('prompt.decline_invitation.confirm'),
                        onConfirm: () => {
							colibriAPI().messenger().with({
								chat_id: props.chat_id
							}).sendTo('groups/invite/decline').then((response) => {
								// Do nothing
							}).catch((error) => {
								if(error.response) {
									toastError(error.response.data.message);
								}
							});

							// Redirect to messenger index without waiting for the response.

							router.push({
								name: 'messenger_index'
							});

							toastSuccess(__t('toast.chat.declined_invitation', { name: groupData.value.name }));
                        }
                    });
				},
				acceptInvitation: async () => {
					state.isSubmitting = true;

					await colibriAPI().messenger().with({
						chat_id: props.chat_id
					}).sendTo('groups/invite/accept').then((response) => {
						router.push({
							name: 'messenger_chat',
							params: {
								chat_id: props.chat_id
							}
						});

						inboxStore.fetchChatsHistory();

						toastSuccess(__t('toast.chat.joined_group', { name: groupData.value.name }));
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});

					state.isSubmitting = false;
				},
				archiveChat: async () => {
                    await chatStore.archiveChat(props.chat_id);

					groupData.value.meta.is_archived = true;
					
                    toastSuccess(__t('toast.chat.chat_archived'));

					router.push({
						name: 'messenger_index'
					});
                },
                unarchiveChat: async () => {
                    await chatStore.unarchiveChat(props.chat_id);

                    groupData.value.meta.is_archived = false;

                    toastSuccess(__t('toast.chat.chat_unarchived'));
                },
				reportGroup: () => {
					colibriEventBus.emit('report:open', {
                        type: 'group',
                        reportableId: groupData.value.id
                    });
				},
				leaveGroup: async () => {
					colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.leave_group.title'),
                        description: __t('prompt.leave_group.description'),
						confirmButtonText: __t('prompt.leave_group.confirm'),
                        onConfirm: () => {
							colibriAPI().messenger().with({
								chat_id: props.chat_id
							}).sendTo('groups/leave').then((response) => {
								// Do nothing
							}).catch((error) => {
								if(error.response) {
									toastError(error.response.data.message);
								}
							});

							// Redirect to messenger index without waiting for the response.

							router.push({
								name: 'messenger_index'
							});

							inboxStore.removeChatFromHistory(props.chat_id);

							toastSuccess(__t('toast.chat.left_group', { name: groupData.value.name }));
                        }
                    });
				},
				deleteGroup: async () => {
					colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.delete_group.title'),
                        description: __t('prompt.delete_group.description'),
						confirmButtonText: __t('prompt.delete_group.confirm'),
                        onConfirm: () => {
							colibriAPI().messenger().with({
								chat_id: props.chat_id
							}).delete('groups/delete').then((response) => {
								// Do nothing
							}).catch((error) => {
								if(error.response) {
									toastError(error.response.data.message);
								}
							});

							// Redirect to messenger index without waiting for the response.

							router.push({
								name: 'messenger_index'
							});

							inboxStore.removeChatFromHistory(props.chat_id);

							toastSuccess(__t('toast.chat.delete_group', { name: groupData.value.name }));
                        }
                    });
				},
			}
		},
		components: {
			GroupHeader: GroupHeader,
			GroupSkeleton: GroupSkeleton,
			AvatarLarge: AvatarLarge,
			ActionSheetGroup: ActionSheetGroup,
			ActionSheetItem: ActionSheetItem,
			GroupParticipants: GroupParticipants
		}
	});
</script>