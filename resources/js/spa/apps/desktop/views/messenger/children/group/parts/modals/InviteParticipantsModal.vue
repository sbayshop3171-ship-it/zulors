<template>
	<Teleport to="body">
		<ContentModal v-on:close="$emit('close')">
			<div class="shrink-0">
				<div class="px-4 py-4">
					<ModalTitle v-bind:title="$t('chat.invite_participants')"></ModalTitle>
					<p class="text-par-n text-lab-sc">
						{{ $t('chat.participant_add_rights') }}
					</p>
				</div>
				<div class="px-4 py-4 mb-4">
					<SearchBar v-model.lazy="searchQuery" v-bind:placeholder="$t('chat.search_by_name')"></SearchBar>
				</div>
			</div>
			<div class="flex-1 max-h-96 overflow-y-auto">
				<PendingInvitations></PendingInvitations>

				<div v-if="state.isSearching || state.isLoading">
					<ParticipantSkeleton v-for="i in 10" :key="i"></ParticipantSkeleton>
				</div>
				<div v-else>
					<template v-if="invitees.length">
						<ParticipantItem v-bind:disabled="! participantData.meta.can_invite"
							v-for="participantData in invitees"
							v-bind:name="participantData.relations.user.name"
							v-bind:caption="$t('labels.was_online_at', { time: participantData.relations.user.last_active.time_ago })"
							v-bind:verified="participantData.relations.user.verified"
							v-bind:avatarSrc="participantData.relations.user.avatar_url"
						v-bind:username="participantData.relations.user.username">
							<template v-if="participantData.meta.can_invite">
								<PrimaryIconButton
									iconSize="icon-normal"
									v-bind:buttonColor="((isParticipantSelected(participantData.id) ? 'text-green-900' : 'text-lab-sc'))"
									v-bind:iconType="(isParticipantSelected(participantData.id) ? 'solid' : 'line')"
									v-bind:iconName="(isParticipantSelected(participantData.id) ? 'check-circle' : 'square')"
								v-on:click="selectParticipant(participantData)"></PrimaryIconButton>
							</template>

							<template v-if="! participantData.meta.can_invite" v-slot:caption>
								<span class="text-par-n text-lab-sc">
									~ ({{ $t('chat.forbidden_to_invite') }})
								</span>
							</template>
						</ParticipantItem>
					</template>
					<template v-else>
						<FluidEmptyState v-bind:text="$t('empty_state.no_results')"></FluidEmptyState>
					</template>
				</div>
			</div>
			<div class="border-t border-bord-pr">
				<div class="px-4 pt-4 pb-2">
					<p class="text-par-n text-lab-tr mt-1">
						{{ $t('chat.invite_participants_description', { days: $config('chat.group.invite_expire_days') }) }}
					</p>
				</div>
				<div class="px-4 pb-4">
					<PrimaryPillButton
						v-bind:isDisabled="sendButtonStatus"
						v-bind:loading="state.isSubmitting" 
						v-bind:buttonText="`${$t('dd.chat_participant.send_invitation')} (${selectedParticipants.length})`"
						buttonSize="lg"
						buttonType="button"
						v-on:click="inviteParticipants"
					v-bind:buttonFluid="true"></PrimaryPillButton>
				</div>
			</div>
		</ContentModal>
	</Teleport>
</template>

<script>
	import { defineComponent, onMounted, reactive, watch, onUnmounted, computed, ref } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import hotkeys from 'hotkeys-js';

	import { useGroupStore } from '@D/store/chats/group.store.js';

	import ModalTitle from '@D/components/general/modals/parts/ModalTitle.vue';
	import SearchBar from '@D/components/general/search/SearchBar.vue';
	import ParticipantItem from '@D/views/messenger/children/chat/parts/participants/ParticipantItem.vue';
	import ParticipantSkeleton from '@D/views/messenger/children/chat/parts/participants/ParticipantSkeleton.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
	import ContentModal from '@D/components/general/modals/ContentModal.vue';
	import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PendingInvitations from '@D/views/messenger/children/group/parts/modals/parts/PendingInvitations.vue';

	export default defineComponent({
		emits: ['close'],
		setup: function(props, context) {
			const groupStore = useGroupStore();
			const state = reactive({
				isLoading: true,
				isSubmitting: false,
				isSearching: false
			});

			const searchQuery = ref('');
			const groupData = computed(() => {
				return groupStore.groupData;
			});

			const selectedParticipants = ref([]);

			const invitees = ref([]);

			const searchInvitees = async () => {
				await colibriAPI().messenger().with({
					chat_id: groupData.value.chat_id,
					query: searchQuery.value
				}).sendTo('groups/invite/search').then(function(response) {
					invitees.value = response.data.data;
				}).catch(() => {
					invitees.value = [];
				});
			};

			onMounted(async function() {
				await searchInvitees();

				debounce(() => {
					state.isLoading = false;
				}, 300);

				hotkeys('esc', () => {
                    context.emit('close');
                });
			});

            onUnmounted(() => {
                hotkeys.unbind('esc');
            });
			
			const isParticipantSelected = (id) => {
				return selectedParticipants.value.includes(id);
			};

			watch(searchQuery, async (queryValue) => {
                if(! state.isSearching) {
					state.isSearching = true;

					await searchInvitees();

					state.isSearching = false;
				}
            });

			return {
				state: state,
				searchQuery: searchQuery,
				selectedParticipants: selectedParticipants,
				invitees: invitees,
				inviteParticipants: async () => {
					state.isSubmitting = true;

					await colibriAPI().messenger().with({
						chat_id: groupData.value.chat_id,
						ids: selectedParticipants.value
					}).sendTo('groups/invite/send').then(function(response) {
						toastSuccess(__t('toast.chat.invite_participants_sent'));
						context.emit('close');
					}).catch(function(error) {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});

					selectedParticipants.value = [];
					state.isSubmitting = false;
				},
				sendButtonStatus: computed(() => {
					return state.isSubmitting || selectedParticipants.value.length < 1;
				}),
				selectParticipant: (participantData) => {
					if(isParticipantSelected(participantData.id)) {
						selectedParticipants.value = selectedParticipants.value.filter((id) => {
							return id !== participantData.id;
						});
					} else {
						selectedParticipants.value.push(participantData.id);
					}
				},
				isParticipantSelected: isParticipantSelected
			};
		},
		components: {
			ModalTitle: ModalTitle,
			PrimaryIconButton: PrimaryIconButton,
			SearchBar: SearchBar,
			ParticipantItem: ParticipantItem,
			PrimaryTextButton: PrimaryTextButton,
			ContentModal: ContentModal,
			PrimaryPillButton: PrimaryPillButton,
			FluidEmptyState: FluidEmptyState,
			ParticipantSkeleton: ParticipantSkeleton,
			PendingInvitations: PendingInvitations
		}
	});
</script>