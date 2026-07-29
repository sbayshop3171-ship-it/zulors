<template>
	<div v-if="followRecommendations.length || state.isLoading" class="mb-4">
		<template v-if="state.isLoading && ! followRecommendations.length">
			<div class="flex justify-center py-8">
				<PrimarySpinAnimation></PrimarySpinAnimation>
			</div>
		</template>
		<template v-else>
			<div class="mb-2">
				<h5 class="text-lab-pr2 font-semibold text-par-l">
					{{ $t('labels.follow_recommendations') }}
				</h5>
			</div>
			<div class="block">
				<FollowListItem v-for="userData in followRecommendations" v-bind:key="userData.id" v-bind:userData="userData"></FollowListItem>
			</div>
			<div>
				<RouterLink v-bind:to="{ name: 'explore_people' }" class="text-par-m hover:underline text-lab-sc cursor-pointer">
					{{ $t('labels.more_suggestions') }}
				</RouterLink>
			</div>
		</template>
	</div>
</template>

<script>
	import { defineComponent, reactive, onMounted, computed, onBeforeUnmount } from 'vue';
	import { useRecommendStore } from '@D/store/recommend/recommend.store.js';

	import FollowListItem from '@D/components/recommend/follow/list/FollowListItem.vue';

	export default defineComponent({
		setup: function(props, context) {
			const recommendStore = useRecommendStore();

			const state = reactive({
				isLoading: false
			});

			const followRecommendations = computed(() => {
				return recommendStore.followRecommendations;
			});

            var updateInterval = null;
            var updateAttempts = 0;

            const updateFollowRecommendations = async function(options = {}) {
                if(! options.silent) {
                    state.isLoading = true;
                }

                await recommendStore.fetchFollowRecommendations();

                updateAttempts++;

                if(updateAttempts > 10) {
                    clearInterval(updateInterval);
                }

                state.isLoading = false;
            }

			onMounted(async () => {
                if(! followRecommendations.value.length) {
                    await updateFollowRecommendations();
                }
                else {
                    updateFollowRecommendations({ silent: true });
                }

                // Update follow recommendations every 20 minutes.
                updateInterval = setInterval(() => {
                    updateFollowRecommendations({ silent: true });
                }, (1000 * 60 * 20));
			});

			onBeforeUnmount(() => {
				if(updateInterval) {
					clearInterval(updateInterval);
				}
			});

			return {
				state: state,
				followRecommendations: followRecommendations,
			};
		},
		components: {
			FollowListItem: FollowListItem
		}
	});
</script>
