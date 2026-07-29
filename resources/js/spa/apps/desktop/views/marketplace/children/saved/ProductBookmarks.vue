<template>
	<SidedContentLayout>
		<template v-slot:content>
            <TimelineContainer>
				<div class="sticky top-0 popup-background-tr z-10">
					<PageHeader v-bind:hasBack="true" v-bind:titleText="$t('market.bookmarks_title')"></PageHeader>
				</div>
				<div class="border-b border-bord-pr">
					<BookmarksHeader></BookmarksHeader>
				</div>

				<div v-if="state.isLoading">
					<div class="flex-center h-96">
						<PrimarySpinAnimation></PrimarySpinAnimation>
					</div>
				</div>
				<div class="block pt-4" v-else>
					<div v-if="bookmarks.length" class="grid grid-cols-3 px-4 gap-4">
						<RouterLink v-for="productData in bookmarks" v-bind:to="{ name: 'marketplace_show', params: { product_id: productData.hash_id } }" class="block">
							<ProductCard v-bind:key="productData.id" v-bind:productData="productData"></ProductCard>
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
	import ProductCard from '@D/views/marketplace/children/catalog/parts/ProductCard.vue';
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
                    type: 'products'
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
			ProductCard: ProductCard
		}
	});
</script>