<template>
    <div v-if="state.isLoading" class="zulors-boot-shell zulors-boot-shell--desktop zulors-route-loader" role="status" aria-label="Loading Zulors">
        <span class="zulors-boot-corner zulors-boot-corner--left">{{ $t('labels.hi_there') }}</span>
        <span class="zulors-boot-corner zulors-boot-corner--right">{{ $t('labels.one_moment') }}...</span>
        <img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="zulors-boot-logo">
    </div>

    <SidedContentLayout v-else>
        <template v-slot:content>
            <TimelineContainer>
                <HomeHeader></HomeHeader>

                <div class="block">
                    <div class="pb-4 px-4">
                        <StoriesFeed></StoriesFeed>
                    </div>
                    <Border></Border>
                    <div class="block">
                        <PublicationEditorTrigger></PublicationEditorTrigger>
                    </div>
                    <Border></Border>
                    <template v-if="globalPinnedPosts.length">
                        <TimelinePublication
                            v-for="pinnedPostData in globalPinnedPosts"
                            v-bind:postData="pinnedPostData"
                            v-bind:isPinned="true"
                            v-on:delete="handlePostDelete(pinnedPostData)"
                        v-bind:key="pinnedPostData.hash_id"></TimelinePublication>
                    </template>
                    <FeedUpdate v-if="timelineNewPosts.length" v-bind:posts="timelineNewPosts" v-on:click="applyTimelineUpdate"></FeedUpdate>
                    <div v-if="timelinePosts.length">
                        <TimelinePublication
                            v-for="(postData, index) in timelinePosts"
                            v-bind:postData="postData"
                            v-bind:feedSessionId="timelineFeedSessionId"
                            v-bind:feedType="timelineFeedType"
                            v-bind:position="index + 1"
                            v-bind:refreshReason="timelineRefreshReason"
                            source="home"
                            v-on:delete="handlePostDelete(postData)"
                        v-bind:key="postData.hash_id"></TimelinePublication>

                        <div v-if="state.isLoadingContent">
                            <div class="flex justify-center my-4">
                                <div class="colibri-primary-animation"></div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="state.isLoadingContent">
                        <div class="flex justify-center py-24">
                            <div class="colibri-primary-animation"></div>
                        </div>
                    </div>
                    <div v-else>
                        <div class="block py-72">
                            <p class="text-lab-sc text-par-s text-center">
                                {{ $t('empty_state.home.posts') }}
                            </p>
                        </div>
                    </div>
                </div>
            </TimelineContainer>
        </template>

        <template v-slot:sidebar>
            <FollowRecommendationList></FollowRecommendationList>

            <AdGridItem></AdGridItem>
        </template>
    </SidedContentLayout>
    <ScrollTopButton></ScrollTopButton>
</template>

<script>
    import { defineComponent, reactive, onMounted, computed, onUnmounted } from 'vue';
    import { useTimelineStore } from '@D/store/timeline/timeline.store.js';
    import { usePinsStore } from '@D/store/timeline/pins.store.js';
    import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';
    import BRD from '@/kernel/websockets/brd/index.js';

    import StoriesFeed from '@D/components/stories/feed/StoriesFeed.vue';
    import TimelinePublication from '@D/components/timeline/feed/TimelinePublication.vue';
    import PublicationEditorTrigger from '@D/features/home/parts/PublicationEditorTrigger.vue';

    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
    import ScrollTopButton from '@D/components/inter-ui/buttons/ScrollTopButton.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';
    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import HomeHeader from '@D/views/home/parts/HomeHeader.vue';
    import FeedUpdate from '@D/components/timeline/update/FeedUpdate.vue';

    const maxRouteLoaderMs = 320;

    export default defineComponent({
        setup: function() {
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
            const pinsStore = usePinsStore();

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

            const globalPinnedPosts = computed(() => {
                return pinsStore.posts;
            });

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
                const hasInstantPosts = timelinePosts.value.length > 0;

                state.isLoading = ! hasInstantPosts;

                if(state.isLoading) {
                    routeLoaderTimer = window.setTimeout(() => {
                        state.isLoading = false;
                        state.isLoadingContent = true;
                    }, maxRouteLoaderMs);
                }

                pinsStore.fetchGlobalPins();

                try {
                    if(hasInstantPosts) {
                        timelineStore.refreshFirstPage({
                            refreshReason: 'resume'
                        });
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

            const loadMorePosts = async () => {
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

						state.isLoadingContent = false;
					}
				} catch (error) {
					console.log(error);
				}
			}

            useInfiniteScroll({
                callback: loadMorePosts
            });

            return {
                timelinePosts: timelinePosts,
                timelineFeedSessionId: timelineFeedSessionId,
                timelineFeedType: timelineFeedType,
                timelineRefreshReason: timelineRefreshReason,
                state: state,
                timelineNewPosts: timelineNewPosts,
                globalPinnedPosts: globalPinnedPosts,
                handlePostDelete: (postData) => {
                    postDeleter(postData);
                },
                applyTimelineUpdate: () => {
                    timelineStore.applyUpdate();
                }
            };
        },
        components: {
            StoriesFeed: StoriesFeed,
            TimelinePublication: TimelinePublication,
            PublicationEditorTrigger: PublicationEditorTrigger,
            TimelineContainer: TimelineContainer,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem,
            ScrollTopButton: ScrollTopButton,
            HomeHeader: HomeHeader,
            SidedContentLayout: SidedContentLayout,
            FeedUpdate: FeedUpdate
        }
    });
</script>
