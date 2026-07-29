<template>
	<div class="h-screen overflow-hidden flex">
		<div class="flex flex-1 h-full flex-col">
			<div class="h-16 flex items-center px-6 border-b border-bord-pr justify-between">
				<PageTitle v-bind:titleText="$t('chat.group_info')"></PageTitle>

				<div class="inline-flex gap-2.5">
					<PrimaryIconButton v-on:click="state.groupParticipantsMenu.toggle" iconName="layout-right" iconType="line"></PrimaryIconButton>
				</div>
			</div>
	
			<div class="flex-1 overflow-x-hidden overflow-y-auto">
				<div class="flex justify-center py-top-offset">
					<div class="w-content max-w-content">
						<template v-if="state.isLoading">
							<GroupShowSkeleton></GroupShowSkeleton>
						</template>
						<template v-else>
							<div class="flex justify-center mb-2">
								<div class="size-large-avatar relative">
									<AvatarLarge v-bind:hasBorder="false" v-bind:avatarSrc="groupData.avatar_url"></AvatarLarge>
								</div>
							</div>
							<div class="px-4 text-center mb-4">
								<h2 class="text-title-3 font-bold text-lab-pr">
									<span v-html="groupData.name"></span> <VerificationBadge v-if="groupData.verified" size="sm"></VerificationBadge>
								</h2>
								<p class="text-par-m text-lab-sc">
									{{ $t('chat.participants_count', { count: groupData.participants_count.formatted }) }}
								</p>
							</div>
	
							<template v-if="hasDescription">
								<div class="border border-bord-pr rounded-2xl p-4">
									<h5 class="font-medium text-lab-sc mb-2 text-par-m">
										{{ $t('chat.about_group') }}
									</h5>
									<p class="text-par-m text-lab-pr2 markdown-text" v-html="$mdInline(groupData.description, { breaks: false })"></p>
								</div>
							</template>
	
							<div class="border border-bord-pr rounded-2xl mt-4 overflow-hidden">
								<GroupRecentJoins></GroupRecentJoins>
								<Border height="h-3" bg="bg-fill-qt" opacity="opacity-70"></Border>
								<ModalRowButton v-if="groupData.meta.is_archived" v-on:click="unarchiveChat" v-bind:buttonText="$t('chat.unarchive_chat')" iconName="inbox-01"></ModalRowButton>
        						<ModalRowButton v-else v-on:click="archiveChat" v-bind:buttonText="$t('chat.archive_chat')" iconName="archive"></ModalRowButton>
								<Border></Border>
								<ModalRowButton v-bind:disabled="state.groupParticipantsMenu.status" v-on:click="state.groupParticipantsMenu.open()" v-bind:hasArrow="true" v-bind:buttonText="$t('chat.group_participants', { count: groupData.participants_count.formatted })" iconName="users-01"></ModalRowButton>
								<ModalRowButton v-if="canAddParticipants" v-on:click="state.inviteParticipantsMenu.open" v-bind:buttonText="$t('chat.invite_participants')" iconName="user-plus-01"></ModalRowButton>
							</div>
							<template v-if="groupData.meta.is_participant || isOwner">
								<div class="border border-bord-pr rounded-2xl mt-4 overflow-hidden">
									<template v-if="isOwner">
										<ModalRowButton v-bind:disabled="state.isSubmitting" v-on:click="deleteGroup" buttonRole="danger" v-bind:buttonText="$t('chat.delete_group')" v-bind:hasArrow="true" iconName="trash-04"></ModalRowButton>
										<Border></Border>
										<RouterLink :to="{ name: 'messenger_group_edit', params: { chat_id: chat_id } }">
											<ModalRowButton v-bind:buttonText="$t('chat.edit_group')" v-bind:hasArrow="true" iconName="pencil-02"></ModalRowButton>
										</RouterLink>
									</template>
									<template v-else>
										<ModalRowButton v-bind:disabled="state.isSubmitting" v-on:click="reportGroup" buttonRole="danger" v-bind:buttonText="$t('chat.report_group')" iconName="annotation-alert"></ModalRowButton>
										<ModalRowButton v-bind:disabled="state.isSubmitting" v-on:click="leaveGroup" buttonRole="danger" v-bind:buttonText="$t('chat.leave_group')" v-bind:hasArrow="true" iconName="log-out-01" iconType="solid"></ModalRowButton>
									</template>
								</div>
							</template>
							<template v-if="groupData.meta.is_invited">
								<div class="border border-bord-pr rounded-2xl mt-4 overflow-hidden">
									<ModalRowButton v-bind:disabled="state.isSubmitting" v-on:click="acceptInvitation" v-bind:buttonText="$t('dd.chat_participant.accept_invitation')" iconName="users-check"></ModalRowButton>
									<ModalRowButton buttonRole="danger" v-on:click="declineInvitation" v-bind:buttonText="$t('dd.chat_participant.decline_invitation')" iconName="slash-circle-01" iconType="line"></ModalRowButton>
								</div>
							</template>

							<div class="px-4 py-4">
								<span class="text-lab-sc text-cap-l block text-center">
									{{ $t('chat.group_created_date', { date: groupData.date.iso })}}
								</span>
							</div>
						</template>
					</div>
				</div>
			</div>
		</div>
		<template v-if="state.groupParticipantsMenu.status">
			<div class="w-messenger-sidebar h-full border-l border-l-bord-pr">
				<GroupParticipants v-on:close="state.groupParticipantsMenu.close"></GroupParticipants>
			</div>
		</template>
	</div>

	<InviteParticipantsModal v-if="state.inviteParticipantsMenu.status" v-on:close="state.inviteParticipantsMenu.close"></InviteParticipantsModal>
</template>

<script>
	import { defineComponent, onMounted, computed, reactive } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useRouter } from 'vue-router';
	import { useGroupStore } from '@D/store/chats/group.store.js';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	import { useInboxStore } from '@D/store/chats/inbox.store.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import PageTitle from '@D/components/layout/PageTitle.vue';
	import AvatarLarge from '@D/components/general/avatars/AvatarLarge.vue';
	import GroupShowSkeleton from '@D/views/messenger/children/group/parts/skeletons/GroupShowSkeleton.vue';
	import ModalRowButton from '@D/components/inter-ui/buttons/ModalRowButton.vue';
	import GroupParticipants from '@D/views/messenger/children/group/parts/GroupParticipants.vue';
	import InviteParticipantsModal from '@D/views/messenger/children/group/parts/modals/InviteParticipantsModal.vue';
	import GroupRecentJoins from '@D/views/messenger/children/group/parts/GroupRecentJoins.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		props: {
			chat_id: {
				type: String,
				required: true
			}
		},
		setup: function(props) {
			const router = useRouter();

			const state = reactive({
				isLoading: true,
				isSubmitting: false,
				groupParticipantsMenu: useMenu(),
				inviteParticipantsMenu: useMenu()
			});

			const inboxStore = useInboxStore();
			const chatStore = useChatStore();
			const groupStore = useGroupStore();

			const groupData = computed(() => {
				return groupStore.groupData;
			});

			onMounted(async () => {
				await groupStore.fetchGroupData(props.chat_id);

				if(! groupData.value) {
					router.push({ name: 'error_404' });
				}

				state.isLoading = false;
			});

			return {
				state: state,
				groupData: groupData,
				hasDescription: computed(() => {
					return groupData.value.description.length > 0;
				}),
				canAddParticipants: computed(() => {
					return groupData.value.meta.permissions.can_add_participant;	
				}),
				isOwner: computed(() => {
					return groupData.value.is_owner;	
				}),
				archiveChat: async () => {
                    await chatStore.archiveChat(props.chat_id);

					groupData.value.meta.is_archived = true;
					
                    toastSuccess(__t('toast.chat.chat_archived'));
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

							inboxStore.removeRequestFromHistory(props.chat_id);

							toastSuccess(__t('toast.chat.declined_invitation', { name: groupData.value.name }));
                        }
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

						toastSuccess(__t('toast.chat.joined_group', { name: groupData.value.name }));

						inboxStore.removeRequestFromHistory(props.chat_id);
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});

					state.isSubmitting = false;
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
			PageTitle: PageTitle,
			ModalRowButton: ModalRowButton,
			AvatarLarge: AvatarLarge,
			PrimaryIconButton: PrimaryIconButton,
			GroupParticipants: GroupParticipants,
			InviteParticipantsModal: InviteParticipantsModal,
			GroupRecentJoins: GroupRecentJoins,
			GroupShowSkeleton: GroupShowSkeleton
		}
	});
</script>