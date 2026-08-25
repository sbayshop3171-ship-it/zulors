<template>
	<div ref="swipeSurfaceRef">
		<TimelineContainer>
			<div class="mobile-safe-overlay-top sticky top-0 popup-background-tr z-10">
	            <Soundbar></Soundbar>
				<div class="px-4 pt-4">
					<QuickSearch v-on:cancel="handleSearchCancel" v-model.lazy="postSearchQuery" v-bind:placeholder="$t('labels.search')"></QuickSearch>
				</div>
				<ExploreTabs></ExploreTabs>
				<FeedUpdate v-if="newPosts.length" v-bind:posts="newPosts" v-on:click="applyNewPosts"></FeedUpdate>
				<Border></Border>
			</div>
			<template v-if="state.isLoading">
				<TimelinePublicationSkeleton v-for="i in 15" v-bind:key="i"></TimelinePublicationSkeleton>
			</template>
			<div v-else>
				<div v-if="state.isSearchLoading">
					<TimelinePublicationSkeleton v-for="i in 15" v-bind:key="i"></TimelinePublicationSkeleton>
				</div>
				<div v-else-if="posts.length">
					<template v-for="(postData, index) in posts" v-bind:key="postData.id">
						<TimelinePublication v-bind:postData="postData" v-bind:feedSessionId="feedSessionId" v-bind:feedType="feedType" v-bind:position="index + 1" source="explore_posts" v-bind:refreshReason="refreshReason" v-on:delete="handlePostDelete(postData)"></TimelinePublication>

						<!-- Show follow recommendation every 35 posts -->
						<template v-if="(index + 1) % 35 === 0">
							<FollowRecommendation v-bind:key="index"></FollowRecommendation>
						</template>

						<!-- Show ad card every 10 posts -->
						<template v-if="(index + 1) % 10 === 0">
							<AdCard v-bind:key="index"></AdCard>
							<Border height="h-2" opacity="opacity-30"></Border>
						</template>
					</template>
				</div>
				<div v-else class="py-32">
					<p class="text-lab-sc text-par-s text-center">
						{{ $t('empty_state.empty') }}
					</p>
				</div>

				<div v-if="state.isLoadingContent">
					<Border></Border>
					<div class="flex justify-center my-4">
						<div class="colibri-primary-animation"></div>
					</div>
				</div>
			</div>
		</TimelineContainer>
	</div>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted, onUnmounted, ref, watch } from 'vue';
    import { useExplorePostsStore } from '@M/store/explore/posts.store.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
	import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';
	import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';
	import { mobileExploreSwipeSequence, useSwipeRouteNavigation } from '@/kernel/vue/composables/swipe-route-navigation/index.js';
	import BRD from '@/kernel/websockets/brd/index.js';

    import TimelineContainer from '@M/components/timeline/feed/TimelineContainer.vue';
    import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import ExploreTabs from '@M/views/explore/parts/ExploreTabs.vue';
    import FeedUpdate from '@M/components/timeline/update/FeedUpdate.vue';
	import AdCard from '@M/components/ads/AdCard.vue';
    import FollowRecommendation from '@M/components/recommend/follow/FollowRecommendation.vue';
    import Soundbar from '@M/components/soundbar/Soundbar.vue';
	import QuickSearch from '@M/components/general/search/QuickSearch.vue';

    export default defineComponent({
        setup: function() {
			const postSearchQuery = ref('');
			const swipeSurfaceRef = ref(null);

			const state = reactive({
				isLoading: true,
                isLoadingContent: false,
                noMoreContent: false,
                isUpdating: false,
				isSearchLoading: false
			});

            let updateIntervalId = null;
            let realtimeChannel = null;

			const { postDeleter } = useDeletePost();

            const explorePostsStore = useExplorePostsStore();
            const newPosts = computed(() => {
                return explorePostsStore.update;
            });

            const posts = computed(() => {
				return explorePostsStore.posts;
			});

			const feedSessionId = computed(() => {
				return explorePostsStore.feedSessionId;
			});

			const feedType = computed(() => {
				return explorePostsStore.feedType;
			});

            const refreshReason = computed(() => {
				return explorePostsStore.refreshReason;
			});

			useSwipeRouteNavigation(swipeSurfaceRef, mobileExploreSwipeSequence);

			const isSearchActive = computed(() => {
				return postSearchQuery.value.trim().length > 0;
			});

			const refreshLatestFeed = async () => {
				if(state.isUpdating || isSearchActive.value) {
					return;
				}

				state.isUpdating = true;

				try {
					await explorePostsStore.updateFeed();

					if(newPosts.value.length) {
						explorePostsStore.applyUpdate();
					}
				} catch (error) {
					console.log(error);
				} finally {
					state.isUpdating = false;
				}
			};

			const setupFeedUpdateInterval = () => {
				if(! updateIntervalId) {
					updateIntervalId = setInterval(refreshLatestFeed, (30 * 1000));
				}
			};

			const setupRealtimeFeedUpdates = () => {
				if(window.ColibriBRD && ! realtimeChannel) {
					const channelName = BRD.getChannel('PUBLIC_TIMELINE');

					realtimeChannel = window.ColibriBRD.channel(channelName);
					realtimeChannel.listen(BRD.getEvent('TIMELINE_POST_CREATED'), refreshLatestFeed);
				}
			};

			useInstantRevalidation(refreshLatestFeed, {
				minDelay: 2000
			});

            useInfiniteScroll({
                callback: async () => {
                    if(! state.isLoadingContent && ! state.noMoreContent && posts.value.length) {
                        state.isLoadingContent = true;

						try {
							explorePostsStore.filter.page += 1;

							state.noMoreContent = (! await explorePostsStore.loadMorePosts());
						}
						finally {
							state.isLoadingContent = false;
						}
                    }
                }
            });

			const applyFilters = async () => {
				explorePostsStore.filter.page = 1;
				explorePostsStore.update = [];
				explorePostsStore.startFeedSession(isSearchActive.value ? 'search' : 'refresh');
				state.noMoreContent = false;
				state.isSearchLoading = ! explorePostsStore.hydrateCachedFirstPage(postSearchQuery.value);

				explorePostsStore.fetchPosts().finally(() => {
					state.isSearchLoading = false;
				});
			};

            onMounted(async() => {
				// Reset filter on mount.
				// Because there can be a filter applied from the previous visits.

				explorePostsStore.resetFilter();
				state.isLoading = true;

				try {
					await explorePostsStore.refreshFirstPage({
						refreshReason: 'open'
					});
				}
				finally {
					state.isLoading = false;
				}

				setupFeedUpdateInterval();
				setupRealtimeFeedUpdates();
            });

			watch(postSearchQuery, () => {
				explorePostsStore.filter.query = postSearchQuery.value;

				debounce(async () => {
					await applyFilters();
				}, 500);
			});

            onUnmounted(() => {
                if(updateIntervalId) {
                    clearInterval(updateIntervalId);
                }

					if(realtimeChannel) {
						realtimeChannel.stopListening(BRD.getEvent('TIMELINE_POST_CREATED'));
					}
	            });

            return {
                state: state,
				swipeSurfaceRef: swipeSurfaceRef,
				posts: posts,
				feedSessionId: feedSessionId,
				feedType: feedType,
				refreshReason: refreshReason,
				postSearchQuery: postSearchQuery,
                newPosts: newPosts,
                applyNewPosts: () => {
                    explorePostsStore.applyUpdate();
                },
				handleSearchCancel: () => {
					postSearchQuery.value = '';
				},
				handlePostDelete: (postData) => {
					postDeleter(postData, (postId) => {
                        toastSuccess(__t('toast.media.post_deleted'));
					});
				}
            };
        },
        components: {
            TimelineContainer: TimelineContainer,
            TimelinePublication: TimelinePublication,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            ExploreTabs: ExploreTabs,
            FeedUpdate: FeedUpdate,
			AdCard: AdCard,
			Soundbar: Soundbar,
			QuickSearch: QuickSearch,
			FollowRecommendation: FollowRecommendation
        }
    });
</script>
