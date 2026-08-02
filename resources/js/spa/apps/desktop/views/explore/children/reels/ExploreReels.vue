<template>
	<SidedContentLayout>
		<template v-slot:content>
			<TimelineContainer>
				<div class="relative overflow-hidden bg-black">
					<div class="sticky top-0 z-30">
						<ExploreTabs variant="dark"></ExploreTabs>
					</div>

					<div
						ref="scrollerRef"
						v-on:scroll.passive="handleScroll"
						class="-mt-12 h-[calc(100dvh-1px)] snap-y snap-mandatory overflow-y-auto overscroll-contain bg-black"
					>
						<div v-if="state.isLoading" class="h-[calc(100dvh-1px)] inline-flex-center text-white">
							<div class="colibri-primary-animation"></div>
						</div>

						<template v-else-if="posts.length">
							<ReelItem
								v-for="(postData, index) in posts"
								v-bind:key="postData.id"
								v-bind:postData="postData"
								v-bind:active="index === state.activeIndex"
								v-bind:isNear="Math.abs(index - state.activeIndex) <= 1"
								v-bind:position="index"
								v-bind:feedSessionId="reelsStore.feedSessionId"
							></ReelItem>

							<div v-if="state.isLoadingMore" class="h-24 inline-flex-center bg-black text-white">
								<div class="colibri-primary-animation"></div>
							</div>
						</template>

						<div v-else class="h-[calc(100dvh-1px)] inline-flex-center bg-black px-10 text-center text-white/70">
							<div>
								<SvgIcon name="video-recorder" type="line" classes="size-12 mx-auto mb-4 text-white/45"></SvgIcon>
								<p class="text-par-s">{{ $t('empty_state.empty') }}</p>
							</div>
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
</template>

<script>
	import { computed, defineComponent, nextTick, onMounted, reactive, ref, watch } from 'vue';
	import { useExploreReelsStore } from '@D/store/explore/reels.store.js';

	import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
	import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
	import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
	import AdGridItem from '@D/components/ads/AdGridItem.vue';
	import ReelItem from '@D/components/reels/ReelItem.vue';
	import ExploreTabs from '@D/views/explore/parts/ExploreTabs.vue';
	import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';

	export default defineComponent({
		props: {
			hash_id: {
				type: String,
				default: ''
			}
		},
		setup: function(props) {
			const scrollerRef = ref(null);
			const reelsStore = useExploreReelsStore();

			const state = reactive({
				isLoading: true,
				isLoadingMore: false,
				noMoreContent: false,
				activeIndex: 0
			});

			const posts = computed(() => {
				return reelsStore.posts;
			});

			const updateActiveIndex = () => {
				const scroller = scrollerRef.value;

				if(! scroller?.clientHeight) {
					return;
				}

				state.activeIndex = Math.max(0, Math.min(posts.value.length - 1, Math.round(scroller.scrollTop / scroller.clientHeight)));
			};

			const maybeLoadMore = async () => {
				const scroller = scrollerRef.value;

				if(! scroller || state.isLoadingMore || state.noMoreContent || ! posts.value.length) {
					return;
				}

				const distanceToBottom = scroller.scrollHeight - (scroller.scrollTop + scroller.clientHeight);

				if(distanceToBottom > (scroller.clientHeight * 2)) {
					return;
				}

				state.isLoadingMore = true;

				try {
					const response = await reelsStore.loadNextPage();
					state.noMoreContent = ! reelsStore.appendPosts(response.data.data);
				} catch (error) {
					state.noMoreContent = true;
				} finally {
					state.isLoadingMore = false;
				}
			};

			const handleScroll = () => {
				updateActiveIndex();
				maybeLoadMore();
			};

			const loadInitial = async () => {
				state.isLoading = true;
				state.noMoreContent = false;
				state.activeIndex = 0;

				await reelsStore.initialLoad(props.hash_id);

				state.isLoading = false;

				nextTick(() => {
					if(scrollerRef.value) {
						scrollerRef.value.scrollTop = 0;
					}

					updateActiveIndex();
				});
			};

			onMounted(loadInitial);

			watch(() => props.hash_id, () => {
				loadInitial();
			});

			return {
				state: state,
				posts: posts,
				scrollerRef: scrollerRef,
				reelsStore: reelsStore,
				handleScroll: handleScroll
			};
		},
		components: {
			SidedContentLayout: SidedContentLayout,
			TimelineContainer: TimelineContainer,
			FollowRecommendationList: FollowRecommendationList,
			AdGridItem: AdGridItem,
			ReelItem: ReelItem,
			ExploreTabs: ExploreTabs,
			SvgIcon: SvgIcon
		}
	});
</script>
