<template>
	<div ref="swipeSurfaceRef" class="mobile-reels-viewport relative overflow-hidden bg-black text-white">
		<div class="mobile-safe-overlay-top pointer-events-none absolute inset-x-0 top-0 z-40 bg-gradient-to-b from-black/90 via-black/55 to-transparent">
			<div class="pointer-events-auto flex h-11 items-center px-2">
				<button type="button" v-on:click="goBack" class="size-10 rounded-full inline-flex-center text-white hover:bg-white/10">
					<SvgIcon name="arrow-left" type="solid" classes="size-6"></SvgIcon>
				</button>
				<div class="flex-1 text-center text-par-l font-bold">
					{{ $t('labels.reels') }}
				</div>
				<div class="size-10"></div>
			</div>
			<div class="pointer-events-auto">
				<ExploreTabs surface="dark"></ExploreTabs>
			</div>
		</div>

		<div v-if="state.isLoading" class="size-full inline-flex-center">
			<div class="colibri-primary-animation"></div>
		</div>

		<div v-else-if="posts.length"
			ref="scrollerRef"
			v-on:scroll.passive="handleScroll"
		class="h-full overflow-y-auto snap-y snap-mandatory overscroll-contain reels-scrollbar">
			<ReelItem
				v-for="(postData, index) in posts"
				v-bind:key="postData.id"
				v-bind:postData="postData"
				v-bind:active="activeIndex === index"
				v-bind:isNear="Math.abs(activeIndex - index) <= nearRadius"
				v-bind:position="index + 1"
				v-bind:feedSessionId="feedSessionId"
			></ReelItem>

			<div v-if="state.isLoadingContent" class="h-24 inline-flex-center text-white/70">
				<div class="colibri-primary-animation"></div>
			</div>
		</div>

		<div v-else class="size-full inline-flex-center px-8 text-center">
			<p class="text-par-s text-white/65">
				{{ $t('empty_state.empty') }}
			</p>
		</div>
	</div>
</template>

<script>
	import { computed, defineComponent, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
	import { useRouter } from 'vue-router';
	import { getNetworkProfileSnapshot, subscribeNetworkProfile } from '@/kernel/services/network/index.js';
	import { useExploreReelsStore } from '@M/store/explore/reels.store.js';
	import { mobileExploreSwipeSequence, useSwipeRouteNavigation } from '@/kernel/vue/composables/swipe-route-navigation/index.js';

	import ReelItem from '@M/components/reels/ReelItem.vue';
	import ExploreTabs from '@M/views/explore/parts/ExploreTabs.vue';
	import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';

	export default defineComponent({
		props: {
			hash_id: {
				type: String,
				default: ''
			}
		},
		setup: function(props) {
			const router = useRouter();
			const reelsStore = useExploreReelsStore();
			const swipeSurfaceRef = ref(null);
			const scrollerRef = ref(null);
			const activeIndex = ref(0);
			const state = reactive({
				isLoading: true,
				isLoadingContent: false,
				noMoreContent: false,
				networkProfile: getNetworkProfileSnapshot()
			});

			let scrollFrame = null;
			let unsubscribeNetworkProfile = null;

			const posts = computed(() => {
				return reelsStore.posts;
			});

			const feedSessionId = computed(() => {
				return reelsStore.feedSessionId;
			});

			const nearRadius = computed(() => {
				return state.networkProfile.reelsNearRadius;
			});

			useSwipeRouteNavigation(swipeSurfaceRef, mobileExploreSwipeSequence);

			const updateActiveIndex = () => {
				const scroller = scrollerRef.value;

				if(! scroller || ! scroller.clientHeight) {
					activeIndex.value = 0;
					return;
				}

				const nextIndex = Math.max(0, Math.min(posts.value.length - 1, Math.round(scroller.scrollTop / scroller.clientHeight)));

				activeIndex.value = nextIndex;
			};

			const maybeLoadMore = async () => {
				const scroller = scrollerRef.value;

				if(! scroller || state.isLoadingContent || state.noMoreContent || ! posts.value.length) {
					return;
				}

				const remaining = scroller.scrollHeight - (scroller.scrollTop + scroller.clientHeight);

				if(remaining > scroller.clientHeight * 2) {
					return;
				}

				state.isLoadingContent = true;

				await reelsStore.loadNextPage().then((response) => {
					const newPosts = response.data.data;

					if(newPosts.length) {
						reelsStore.appendPosts(newPosts);
					}
					else {
						state.noMoreContent = true;
					}
				}).catch(() => {
					state.noMoreContent = true;
				});

				state.isLoadingContent = false;
			};

			const handleScroll = () => {
				if(scrollFrame) {
					cancelAnimationFrame(scrollFrame);
				}

				scrollFrame = requestAnimationFrame(() => {
					scrollFrame = null;
					updateActiveIndex();
					maybeLoadMore();
				});
			};

			const loadReels = async () => {
				state.isLoading = true;
				state.noMoreContent = false;
				activeIndex.value = 0;

				await reelsStore.initialLoad(props.hash_id).finally(() => {
					state.isLoading = false;

					nextTick(() => {
						if(scrollerRef.value) {
							scrollerRef.value.scrollTop = 0;
						}

						updateActiveIndex();
					});
				});
			};

			const handleResize = () => {
				updateActiveIndex();
			};

			onMounted(async () => {
				unsubscribeNetworkProfile = subscribeNetworkProfile((networkProfile) => {
					state.networkProfile = networkProfile;
				});

				await loadReels();
				window.addEventListener('resize', handleResize);
			});

			onUnmounted(() => {
				unsubscribeNetworkProfile?.();
				window.removeEventListener('resize', handleResize);

				if(scrollFrame) {
					cancelAnimationFrame(scrollFrame);
				}
			});

			watch(() => props.hash_id, async () => {
				await loadReels();
			});

			return {
				state: state,
				posts: posts,
				feedSessionId: feedSessionId,
				nearRadius: nearRadius,
				swipeSurfaceRef: swipeSurfaceRef,
				scrollerRef: scrollerRef,
				activeIndex: activeIndex,
				handleScroll: handleScroll,
				goBack: () => {
					if(window.history.length > 1) {
						router.back();
					}
					else {
						router.push({
							name: 'explore_posts'
						});
					}
				}
			};
		},
		components: {
			ReelItem: ReelItem,
			ExploreTabs: ExploreTabs,
			SvgIcon: SvgIcon
		}
	});
</script>

<style scoped>
	.reels-scrollbar {
		scrollbar-width: none;
	}

	.reels-scrollbar::-webkit-scrollbar {
		display: none;
	}
</style>
