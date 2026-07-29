<template>
	<SidedContentLayout>
		<template v-slot:content>
            <TimelineContainer>
				<div class="sticky top-0 popup-background-tr z-10">
					<PageHeader v-bind:hasBack="true" v-bind:titleText="$t('job.bookmarks_title')"></PageHeader>
				</div>
				<div class="border-b border-bord-pr">
					<BookmarksHeader></BookmarksHeader>
				</div>

				<div class="block" v-if="state.isLoading">
					<JobCardSkeleton v-for="i in 3" v-bind:key="i"></JobCardSkeleton>
				</div>
				<div class="block" v-else>
					<div v-if="bookmarks.length">
						<RouterLink v-for="jobItem in bookmarks" v-bind:to="{ name: 'jobs_show', params: { job_id: jobItem.hash_id } }" class="block">
							<JobCard v-bind:key="jobItem.id" v-bind:jobData="jobItem"></JobCard>
						</RouterLink>
					</div>
					<div v-else>
						<FluidEmptyState iconName="bookmark" v-bind:text="$t('empty_state.jobs.bookmarks')"></FluidEmptyState>
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

	import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
	import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
	import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';
	import PageHeader from '@D/components/layout/PageHeader.vue';
	import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';
	import JobCard from '@D/views/jobs/children/board/parts/JobCard.vue';
	import JobCardSkeleton from '@D/views/jobs/children/board/parts/JobCardSkeleton.vue';
	import BookmarksHeader from '@D/components/timeline/header/BookmarksHeader.vue';

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
                    type: 'jobs'
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
			TimelineContainer: TimelineContainer,
			FollowRecommendationList: FollowRecommendationList,
			AdGridItem: AdGridItem,
			PageHeader: PageHeader,
			FluidEmptyState: FluidEmptyState,
			JobCard: JobCard,
			JobCardSkeleton: JobCardSkeleton
		}
	});
</script>