<template>
	<div v-if="state.isLoading" class="zulors-boot-shell zulors-boot-shell--mobile zulors-route-loader" role="status" aria-label="Loading Zulors">
		<img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="zulors-boot-logo">
	</div>

	<div v-else ref="swipeSurfaceRef">
		<TimelineContainer>
	        <div class="px-4 pb-3 pt-1">
	            <StoriesFeed></StoriesFeed>
	        </div>
	        <Border height="h-2" opacity="opacity-30"></Border>
			<div class="pb-6">
	            <FeedUpdate v-if="timelineNewPosts.length" v-bind:posts="timelineNewPosts" v-on:click="applyTimelineUpdate"></FeedUpdate>
				<div v-if="timelinePosts.length">
	                <template v-for="(postData, index) in timelinePosts" v-bind:key="postData.hash_id">
	                    <TimelinePublication
	                        v-bind:postData="postData"
	                        v-bind:feedSessionId="timelineFeedSessionId"
	                        v-bind:feedType="timelineFeedType"
	                        v-bind:position="index + 1"
	                        v-bind:refreshReason="timelineRefreshReason"
	                        source="home"
	                    v-on:delete="handlePostDelete(postData)"></TimelinePublication>
	                    
	                    <!-- Show follow recommendation every 10 posts -->
	                    <template v-if="(index + 1) % 35 === 0">
	                        <FollowRecommendation v-bind:key="index"></FollowRecommendation>
	                    </template>

	                    <!-- Show ad card every 10 posts -->
	                    <template v-if="(index + 1) % 10 === 0">
	                        <AdCard v-bind:key="index"></AdCard>
	                        <Border height="h-2" opacity="opacity-30"></Border>
	                    </template>
	                </template>

					<div v-if="state.isLoadingContent">
						<div class="flex justify-center my-4">
							<div class="colibri-primary-animation"></div>
						</div>
					</div>
				</div>
				<div v-else-if="state.isLoadingContent">
					<TimelinePublicationSkeleton v-for="i in 5" v-bind:key="i"></TimelinePublicationSkeleton>
				</div>
				<div v-else>
					<div class="py-32">
						<p class="text-lab-sc text-par-s text-center">
							{{ $t('empty_state.home.posts') }}
						</p>
					</div>
				</div>
			</div>
		</TimelineContainer>
	</div>
</template>

<script>
    import { defineComponent, reactive, onMounted, computed, onUnmounted, ref } from 'vue';
    import { useTimelineStore } from '@M/store/timeline/timeline.store.js';
    import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';
    import { mobileHomeSwipeSequence, useSwipeRouteNavigation } from '@/kernel/vue/composables/swipe-route-navigation/index.js';
    import BRD from '@/kernel/websockets/brd/index.js';

    import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import TimelineContainer from '@M/components/timeline/feed/TimelineContainer.vue';
    import StoriesFeed from '@M/components/stories/feed/StoriesFeed.vue';
    import AdCard from '@M/components/ads/AdCard.vue';
    import FollowRecommendation from '@M/components/recommend/follow/FollowRecommendation.vue';
    import FeedUpdate from '@M/components/timeline/update/FeedUpdate.vue';

    const maxRouteLoaderMs = 320;

    export default defineComponent({
        setup: function() {
            const swipeSurfaceRef = ref(null);
            const state = reactive({
                isLoading: true,
                isLoadingContent: false,
                noMoreContent: false,
                isUpdating: false,
                filter: {
                    page: 1
                }
            });

            let updateIntervalId = null;
            let realtimeChannel = null;
            let routeLoaderTimer = null;

            const { postDeleter } = useDeletePost();

            const timelineStore = useTimelineStore();

            const timelineNewPosts = computed(() => {
                return timelineStore.update;
            });

            const timelinePosts = computed(() => {
                return timelineStore.posts;
            });

            const timelineFeedSessionId = computed(() => {
                return timelineStore.feedSessionId;
            });

            const timelineFeedType = computed(() => {
                return timelineStore.feedType;
            });

            const timelineRefreshReason = computed(() => {
                return timelineStore.refreshReason;
            });

            const hydrateInstantTimeline = () => {
                if(timelinePosts.value.length) {
                    return true;
                }

                return timelineStore.hydrateBootFeed(
                    window.__zulorsBoot?.cachedBootstrap?.home_feed
                    ?? (window.__zulorsBoot?.isAuthenticated ? null : window.__zulorsBoot?.sharedFeed)
                    ?? null
                );
            };

            useSwipeRouteNavigation(swipeSurfaceRef, mobileHomeSwipeSequence);

            const refreshLatestFeed = async () => {
                if(state.isUpdating) {
                    return;
                }

                state.isUpdating = true;

                try {
                    await timelineStore.updateFeed();

                    if(timelineNewPosts.value.length) {
                        timelineStore.applyUpdate();
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

            onMounted(async () => {
                const hasInstantPosts = hydrateInstantTimeline() || timelinePosts.value.length > 0;

                state.isLoading = ! hasInstantPosts;

                if(state.isLoading) {
                    routeLoaderTimer = window.setTimeout(() => {
                        state.isLoading = false;
                        state.isLoadingContent = true;
                    }, maxRouteLoaderMs);
                }

                try {
                    if(hasInstantPosts) {
                        timelineStore.refreshOnAppVisible({
                            refreshReason: 'open',
                            minIntervalMs: 0
                        }).catch(() => {});
                    }
                    else {
                        await timelineStore.initialLoad();
                    }
                } catch (error) {
                    console.log(error);
                } finally {
                    if(routeLoaderTimer) {
                        window.clearTimeout(routeLoaderTimer);
                        routeLoaderTimer = null;
                    }

                    state.isLoading = false;
                    state.isLoadingContent = false;

                    setupFeedUpdateInterval();
                    setupRealtimeFeedUpdates();
                }
            });

            onUnmounted(() => {
                if(routeLoaderTimer) {
                    window.clearTimeout(routeLoaderTimer);
                }

                if(updateIntervalId) {
                    clearInterval(updateIntervalId);
                }

                if(realtimeChannel) {
                    realtimeChannel.stopListening(BRD.getEvent('TIMELINE_POST_CREATED'));
                }
			});

            const loadMorePost = async () => {
				try {
					if(! state.isLoadingContent && ! state.noMoreContent && timelinePosts.value.length) {
						state.isLoadingContent = true;

						await timelineStore.loadNextPage().then(function(response) {
							let content = response.data.data;

							if(content.length) {
								state.noMoreContent = ! timelineStore.appendPosts(content);
							}
							else{
								state.noMoreContent = true;
							}
						}).catch((error) => {
							if(error.response) {
								state.noMoreContent = true;
							}
						});

					}
				} catch (error) {
					console.log(error);
				} finally {
					state.isLoadingContent = false;
				}
			}

            useInfiniteScroll({
                callback: loadMorePost
            });

            return {
                swipeSurfaceRef: swipeSurfaceRef,
                timelinePosts: timelinePosts,
                timelineFeedSessionId: timelineFeedSessionId,
                timelineFeedType: timelineFeedType,
                timelineRefreshReason: timelineRefreshReason,
                state: state,
                timelineNewPosts: timelineNewPosts,
                handlePostDelete: (postData) => {
                    postDeleter(postData, (postId) => {
                        toastSuccess(__t('toast.media.post_deleted'));
                    });
                },
                applyTimelineUpdate: () => {
                    timelineStore.applyUpdate();
                }
            };
        },
        components: {
            TimelinePublication: TimelinePublication,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            TimelineContainer: TimelineContainer,
            StoriesFeed: StoriesFeed,
            AdCard: AdCard,
            FollowRecommendation: FollowRecommendation,
            FeedUpdate: FeedUpdate
        }
    });
</script>
