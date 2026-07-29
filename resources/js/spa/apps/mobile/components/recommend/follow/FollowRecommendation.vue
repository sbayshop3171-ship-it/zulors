<template>
	<div v-if="followRecommendations.length" class="pt-4 pb-2.5">
		<div class="mb-1 px-4">
			<h5 class="text-lab-pr2 font-semibold text-par-l">
				{{ $t('labels.follow_recommendations') }}
			</h5>
		</div>
		<div class="block">
			<PeopleListItem v-for="userData in followRecommendations" v-bind:key="userData.id" v-bind:userData="userData"></PeopleListItem> 
		</div>

		<div class="px-4">
			<RouterLink v-bind:to="{ name: 'explore_people' }" class="text-par-n flex justify-between text-lab-sc cursor-pointer">
				<span class="shrink-0">
					{{ $t('labels.more_suggestions') }}
				</span>
				<span class="shrink-0 size-icon-x-small">
					<SvgIcon name="chevron-right"></SvgIcon>
				</span>
			</RouterLink>
		</div>
	</div>
	<Border height="h-2" opacity="opacity-30"></Border>
</template>

<script>
	import { defineComponent, reactive, onMounted, computed } from 'vue';
	import { useRecommendStore } from '@M/store/recommend/recommend.store.js';

	import PeopleListItem from '@M/components/people/PeopleListItem.vue';
	
	export default defineComponent({
		setup: function(props, context) {
			const recommendStore = useRecommendStore();

			const state = reactive({
				isLoading: false
			});

			const followRecommendations = computed(() => {
				return recommendStore.followRecommendations;
			});

			onMounted(async () => {
				state.isLoading = ! followRecommendations.value.length;

				await recommendStore.fetchFollowRecommendations();

				state.isLoading = false;
			});

			return {
				state: state,
				followRecommendations: followRecommendations,
			};
		},
		components: {
			PeopleListItem: PeopleListItem
		}
	});
</script>
