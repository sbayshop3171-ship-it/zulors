<template>
	<ActionSheet v-bind:isMuted="true">
		<div class="mb-4 px-4 text-center">
			<SheetTitle v-bind:title="$t('chat.participants_count', { count: groupData.participants_count.formatted })"></SheetTitle>

			<p class="text-par-n text-lab-sc mt-1">
				{{ $t('chat.participant_add_rights') }}
			</p>
		</div>
		<div v-if="state.isLoading" class="block">
			<div class="flex-center h-24">
				<div class="colibri-primary-animation"></div>
			</div>
		</div>
		<div v-else>
			<ActionSheetGroup>
				<div class="block max-h-[500px] overflow-y-auto py-1">
					<ParticipantItem v-for="participantData in groupParticipants"
						v-bind:name="participantData.relations.user.name"
						v-bind:caption="$t('labels.was_online_at', { time: participantData.relations.user.last_active.time_ago })"
						v-bind:verified="participantData.relations.user.verified"
						v-bind:avatarSrc="participantData.relations.user.avatar_url"
					v-bind:username="participantData.relations.user.username">
	
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
			</ActionSheetGroup>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, onMounted, computed, reactive } from 'vue';
	import { useGroupStore } from '@M/store/chats/group.store.js';
	
	import ParticipantItem from '@M/views/messenger/children/chat/parts/participants/ParticipantItem.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';

	export default defineComponent({
		props: {
			groupData: {
				type: Object,
				required: true
			}
		},
		setup: function (props, context) {
			const state = reactive({
                isLoading: true
            });

			const groupStore = useGroupStore();
			const groupParticipants = computed(() => {
                return groupStore.groupParticipants;
            });

            onMounted(async () => {
                try {
                    await groupStore.fetchGroupParticipants();

                    state.isLoading = false;
                } catch (error) {
                    alert(error);
                }
            });

			return {
				groupParticipants: groupParticipants,
				state: state
			};
		},
		components: {
			ParticipantItem: ParticipantItem,
			ActionSheet: ActionSheet,
			ActionSheetGroup: ActionSheetGroup,
			SheetTitle: SheetTitle
		}
	});
</script>