<template>
	<div class="flex flex-col h-full">
		<div class="shrink-0 border-b border-bord-pr">
			<div class="py-4 px-6 mb-4 flex">
				<div class="flex-1">
					<PageTitle v-bind:hasBack="false" v-bind:titleText="$t('chat.participants')"></PageTitle>
					<p class="text-par-n text-lab-sc mt-1">
						{{ $t('chat.participant_remove_rights') }}
					</p>
				</div>
				<div v-if="canDeleteParticipants" class="shrink-0">
					<template v-if="state.selectMode">
						<PrimaryTextButton v-bind:buttonText="$t('dd.done')" v-on:click="state.selectMode = false"></PrimaryTextButton>
					</template>
					<template v-else>
						<PrimaryTextButton v-bind:buttonText="$t('dd.edit')" v-on:click="state.selectMode = true"></PrimaryTextButton>
					</template>
				</div>
			</div>
			<div class="px-4 mb-4">
				<QuickSearch v-on:cancel="handleSearchCancel" v-model.lazy="searchQuery" v-bind:placeholder="$t('chat.search')"></QuickSearch>
			</div>
		</div>
		<div class="flex-1 overflow-y-auto">
			<template v-if="isEmptyGroup">
				<div class="py-80 text-center">
					<p class="text-par-s text-lab-sc">
						{{  $t('chat.no_group_participants') }}
					</p>
				</div>
			</template>
			<template v-else>
				<div v-if="state.isLoading">
					<div class="flex-center h-24">
						<PrimarySpinAnimation></PrimarySpinAnimation>
					</div>
				</div>
				<div v-else>
					<ParticipantItem v-bind:disabled="participantData.meta.is_admin"
						v-for="participantData in participantsToShow"
							v-bind:name="participantData.relations.user.name"
							v-bind:caption="$t('labels.was_online_at', { time: participantData.relations.user.last_active.time_ago })"
							v-bind:verified="participantData.relations.user.verified"
							v-bind:avatarSrc="participantData.relations.user.avatar_url"
						v-bind:username="participantData.relations.user.username">
						
						<template v-if="state.selectMode && canDeleteParticipants">
							<template v-if="! participantData.meta.is_admin">
								<PrimaryIconButton
									iconSize="icon-normal"
										v-bind:buttonColor="((isParticipantSelected(participantData.participant_id) ? 'text-green-900' : 'text-lab-sc'))"
										v-bind:iconType="(isParticipantSelected(participantData.participant_id) ? 'solid' : 'line')"
										v-bind:iconName="(isParticipantSelected(participantData.participant_id) ? 'check-circle' : 'square')"
									v-on:click="selectParticipant(participantData)">
								</PrimaryIconButton>
							</template>
						</template>
						<template v-if="participantData.meta.is_admin" v-slot:caption>
							<span class="text-par-n text-green-900">
								~ {{ $t('chat.group_admin') }}
								<template v-if="participantData.relations.user.is_auth_user">
									~ {{ $t('labels.you') }}
								</template>
							</span>
						</template>
						<template v-else-if="participantData.relations.user.is_auth_user" v-slot:caption>
							<span class="text-par-n text-green-900">
								~ {{ $t('labels.you') }}
							</span>
						</template>
					</ParticipantItem>
				</div>
			</template>
		</div>
		<div v-if="canDeleteParticipants && state.selectMode" class="border-t border-bord-pr">
			<ModalRowButton v-bind:loading="state.isSubmitting" v-bind:disabled="deleteButtonStatus" buttonRole="danger" v-bind:buttonText="`${$t('dd.chat_participant.delete_participants')} (${selectedParticipants.length})`" iconName="trash-04" v-on:click="deleteParticipants"></ModalRowButton>
		</div>
	</div>
</template>

<script>
	import { defineComponent, onMounted, reactive, watch, onUnmounted, computed, ref } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import hotkeys from 'hotkeys-js';

	import { useGroupStore } from '@D/store/chats/group.store.js';

	import PageTitle from '@D/components/layout/PageTitle.vue';
	import QuickSearch from '@D/components/general/search/QuickSearch.vue';
	import ParticipantItem from '@D/views/messenger/children/chat/parts/participants/ParticipantItem.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ModalRowButton from '@D/components/inter-ui/buttons/ModalRowButton.vue';
	import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';

	export default defineComponent({
		emits: ['close'],
		setup: function(props, context) {
			const groupStore = useGroupStore();
			const state = reactive({
				isLoading: true,
				selectMode: false,
				isSubmitting: false
			});

			const searchQuery = ref('');
			const groupData = computed(() => {
				return groupStore.groupData;
			});

			const searchResults = ref([]);

			const selectedParticipants = ref([]);

			const groupParticipants = computed(() => {
				return groupStore.groupParticipants;
			});

			onMounted(async function() {
				await groupStore.fetchGroupParticipants();

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

			const isSearching = computed(() => {
                return searchQuery.value.length > 0;
            });

			watch(searchQuery, (queryValue) => {
                searchResults.value = groupParticipants.value.filter((item) => {
                    if(item.relations.user.name.toLowerCase().includes(queryValue.toLowerCase())) {
                        return true;
                    }
                });
            });

			return {
				state: state,
				searchQuery: searchQuery,
				selectedParticipants: selectedParticipants,
				isSearching: isSearching,
				isEmptyGroup: computed(() => {
					return groupParticipants.value.length < 1;
				}),
				participantsToShow: computed(() => {
					if(isSearching.value) {
						return searchResults.value;
					}

					return groupParticipants.value;
				}),
				handleSearchCancel: () => {
					searchQuery.value = '';
				},
				deleteParticipants: async () => {
					state.isSubmitting = true;

					await colibriAPI().messenger().with({
						chat_id: groupData.value.chat_id,
						ids: selectedParticipants.value
					}).delete('groups/participant/delete').then(function(response) {
						
						groupStore.groupParticipants = groupStore.groupParticipants.filter((p) => {
							return ! selectedParticipants.value.includes(p.participant_id);
						});

						toastSuccess("Participants have been deleted from this chat.");
					}).catch(function(error) {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});

					selectedParticipants.value = [];
					state.selectMode = false;
					state.isSubmitting = false;
				},
				deleteButtonStatus: computed(() => {
					return state.isSubmitting || selectedParticipants.value.length < 1;
				}),
				selectParticipant: (participantData) => {
					if(isParticipantSelected(participantData.participant_id)) {
						selectedParticipants.value = selectedParticipants.value.filter((id) => {
							return id !== participantData.participant_id;
						});
					} else {
						selectedParticipants.value.push(participantData.participant_id);
					}
				},
				canDeleteParticipants: computed(() => {
					return groupStore.groupData.meta.permissions.can_delete_participant;
				}),
				isParticipantSelected: isParticipantSelected
			};
		},
		components: {
			PageTitle: PageTitle,
			PrimaryIconButton: PrimaryIconButton,
			QuickSearch: QuickSearch,
			ModalRowButton: ModalRowButton,
			ParticipantItem: ParticipantItem,
			PrimaryTextButton: PrimaryTextButton
		}
	});
</script>