<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <PageHeader v-bind:hasBack="true" v-bind:titleText="$t('labels.posts')"></PageHeader>
                <BookmarksHeader></BookmarksHeader>
                <Border></Border>
                <div class="block" v-if="state.isLoading">
                    <TimelinePublicationSkeleton v-for="i in 3" v-bind:key="i"></TimelinePublicationSkeleton>
                </div>
                <div class="block" v-else>
                    <div v-if="bookmarks.length">
                        <TimelinePublication v-for="postData in bookmarks" v-bind:key="postData.id" v-bind:postData="postData"></TimelinePublication>
                    </div>
                    <div v-else>
                        <FluidEmptyState iconName="bookmark" v-bind:text="$t('empty_state.post_bookmarks')"></FluidEmptyState>
                    </div>
                </div>
            </TimelineContainer>
        </template>
        <template v-slot:sidebar>
            <FollowRecommendationList></FollowRecommendationList>

            <AdGridItem></AdGridItem>
        </template>
    </SidedContentLayout>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted } from 'vue';
    import { useBookmarksStore } from '@D/store/bookmarks/bookmarks.store.js';
    
    import PageHeader from '@D/components/layout/PageHeader.vue';
    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';
    import BookmarksHeader from '@D/components/timeline/header/BookmarksHeader.vue';
    import TimelinePublication from '@D/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@D/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';

    export default defineComponent({
        setup: function() {
            const bookmarksStore = useBookmarksStore();
			const state = reactive({
				isLoading: true
			});

            bookmarksStore.resetBookmarks();

            const bookmarks = computed(() => {
				return bookmarksStore.bookmarks;
			});

            onMounted(async() => {
                state.isLoading = true;

                await bookmarksStore.fetchBookmarks({
                    type: 'posts'
                });

               	state.isLoading = false;
            });

            return {
                state: state,
				bookmarks: bookmarks
            };
        },
        components: {
            BookmarksHeader: BookmarksHeader,
            SidedContentLayout: SidedContentLayout,
            PageHeader: PageHeader,
            TimelineContainer: TimelineContainer,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem,
            TimelinePublication: TimelinePublication,
            FluidEmptyState: FluidEmptyState,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton
        }
    });
</script>