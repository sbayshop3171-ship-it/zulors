<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <ContentTabs v-bind:cols="2">
                    <TabsLink v-bind:link="{ name: 'explore_posts' }">
                        {{ $t('labels.explore') }}
                    </TabsLink>
                    <TabsLink v-bind:link="{ name: 'explore_people' }">
                        {{ $t('labels.people') }}
                    </TabsLink>
                </ContentTabs>
                <Border></Border>

                <div class="p-4">
                    <SearchBar v-model.lazy="postSearchQuery" v-bind:placeholder="$t('labels.search')"></SearchBar>
                </div>
                <Border></Border>

                <div class="block" v-if="state.isLoading">
                    <TimelinePublicationSkeleton v-for="i in 3" v-bind:key="i"></TimelinePublicationSkeleton>
                </div>
                <div class="block" v-else>
                    <FeedUpdate v-if="newPosts.length" v-bind:posts="newPosts" v-on:click="applyNewPosts"></FeedUpdate>
                    <div v-if="state.isSearchLoading">
                        <TimelinePublicationSkeleton v-for="i in 3" v-bind:key="i"></TimelinePublicationSkeleton>
                    </div>
                    <div v-else-if="posts.length">
                        <TimelinePublication v-for="postData in posts" v-bind:key="postData.id" v-bind:postData="postData"></TimelinePublication>
                    </div>
                    <div v-else>
                        <FluidEmptyState v-bind:text="$t('empty_state.empty')"></FluidEmptyState>
                    </div>

                    <div v-if="state.isLoadingContent">
                        <Border></Border>
                        <div class="flex justify-center my-4">
                            <div class="colibri-primary-animation"></div>
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

    <Teleport to="body">
        <ScrollTopButton></ScrollTopButton>
    </Teleport>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted, onUnmounted, ref, watch } from 'vue';
    import { useExplorePostsStore } from '@D/store/explore/posts.store.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';
    import BRD from '@/kernel/websockets/brd/index.js';

    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';
    import TimelinePublication from '@D/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@D/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';
    import ContentTabs from '@D/components/general/tabs/content/ContentTabs.vue';
    import TabsLink from '@D/components/general/tabs/content/parts/TabsLink.vue';
    import ScrollTopButton from '@D/components/inter-ui/buttons/ScrollTopButton.vue';
    import FeedUpdate from '@D/components/timeline/update/FeedUpdate.vue';
    import SearchBar from '@D/components/general/search/SearchBar.vue';

    export default defineComponent({
        setup: function() {
            const postSearchQuery = ref('');

			const state = reactive({
				isLoading: true,
                isLoadingContent: false,
                noMoreContent: false,
                isUpdating: false,
                isSearchLoading: false
			});

            let updateIntervalId = null;
            let realtimeChannel = null;
            const explorePostsStore = useExplorePostsStore();
            const newPosts = computed(() => {
                return explorePostsStore.update;
            });

            const posts = computed(() => {
				return explorePostsStore.posts;
			});

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

                        explorePostsStore.filter.page += 1;

                        state.noMoreContent = (! await explorePostsStore.loadMorePosts());

                        state.isLoadingContent = false;
                    }
                }
            });

            const applyFilters = async () => {
                explorePostsStore.filter.page = 1;
                explorePostsStore.update = [];
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
                state.isLoading = ! posts.value.length;

                if(posts.value.length) {
                    explorePostsStore.refreshFirstPage().finally(() => {
                        state.isLoading = false;
                    });
                }
                else {
                    await explorePostsStore.fetchPosts();
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
				posts: posts,
                postSearchQuery: postSearchQuery,
                newPosts: newPosts,
                applyNewPosts: () => {
                    explorePostsStore.applyUpdate();
                }
            };
        },
        components: {
            SidedContentLayout: SidedContentLayout,
            TimelineContainer: TimelineContainer,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem,
            TimelinePublication: TimelinePublication,
            FluidEmptyState: FluidEmptyState,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            ContentTabs: ContentTabs,
            TabsLink: TabsLink,
            ScrollTopButton: ScrollTopButton,
            FeedUpdate: FeedUpdate,
            SearchBar: SearchBar
        }
    });
</script>
