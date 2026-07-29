<template>
	<ActionSheet>
		<div class="h-full flex flex-col">
			<div class="px-4 pb-4 border-b border-b-bord-pr text-center">
				<SheetTitle v-bind:title="$t('labels.followers_count', profileData.followers_count.raw) + ' ' + profileData.followers_count.formatted"></SheetTitle>
			</div>
			<div class="flex-1 overflow-y-auto">
				<template v-if="state.isLoading">
					<PeopleListItemSkeleton v-for="i in 3" v-bind:key="i"></PeopleListItemSkeleton>
				</template>
				<template v-else-if="people.length === 0">
					<div class="py-42">
						<TimelineEmptyState v-bind:desc="$t('labels.user_no_followers', { name: profileData.name })"></TimelineEmptyState>
					</div>
				</template>
				<template v-else>
					<PeopleListItem v-for="userData in people" v-bind:key="userData.id" v-bind:userData="userData"></PeopleListItem>

					<template v-if="state.isLoadingContent">
						<div class="flex justify-center py-4">
							<div class="colibri-primary-animation"></div>
						</div>
					</template>
					<template v-else>
						<LoadmoreButton v-if="! state.noMoreContent" v-on:click="loadMoreContent"></LoadmoreButton>
					</template>
				</template>
			</div>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, inject, reactive, ref, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
	import PeopleListItem from '@M/components/people/PeopleListItem.vue';
	import PeopleListItemSkeleton from '@M/components/people/PeopleListItemSkeleton.vue';
	import LoadmoreButton from '@M/components/inter-ui/buttons/LoadmoreButton.vue';
	import TimelineEmptyState from '@M/components/timeline/state/TimelineEmptyState.vue';

	export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const people = ref([]);
			const cursor = ref(0);
			const state = reactive({
				isLoading: true,
				isLoadingContent: false,
				noMoreContent: false
			});

			const fetchFollowers = async () => {
				await colibriAPI().userProfile().params({ 
					id: profileData.value.id,
					cursor: cursor.value
				}).getFrom('profile/followers').then(function(response) {
					if(cursor.value) {
						if(response.data.data.length) {
							people.value = people.value.concat(response.data.data);
						} else {
							state.noMoreContent = true;
						}
					} else {
						people.value = response.data.data;
					}
				}).catch((error) => {
					people.value = [];
				});
			}

			onMounted(async () => {
				await fetchFollowers();

				state.isLoading = false;
			});

			return {
				profileData: profileData,
				state: state,
				people: people,
				loadMoreContent: async () => {
					if(state.isLoadingContent || ! people.value.length) {
						return false;
					}

					state.isLoadingContent = true;
					cursor.value = people.value.at(-1).cursor_id;

					await fetchFollowers();
					state.isLoadingContent = false;
				}
			}
		},
		components: {
			ActionSheet: ActionSheet,
			PeopleListItem: PeopleListItem,
			PeopleListItemSkeleton: PeopleListItemSkeleton,
			SheetTitle: SheetTitle,
			LoadmoreButton: LoadmoreButton,
			TimelineEmptyState: TimelineEmptyState
		}
	});
</script>