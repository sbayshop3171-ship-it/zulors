<template>
	<div class="fixed inset-0 z-[900] overflow-hidden bg-[#05070a] text-white">
		<div class="pointer-events-none absolute inset-0 bg-[linear-gradient(90deg,#05070a_0%,#101418_48%,#05070a_100%)]"></div>

		<div class="fixed left-6 top-5 z-[930] flex items-center gap-3">
			<RouterLink v-bind:to="{ name: 'explore_posts' }" class="size-11 rounded-full bg-white/10 backdrop-blur inline-flex-center text-white transition hover:bg-white/15">
				<SvgIcon name="arrow-left" type="solid" classes="size-6"></SvgIcon>
			</RouterLink>

			<div class="leading-none">
				<p class="text-par-m font-bold text-white">{{ $t('labels.reels') }}</p>
				<p class="mt-1 text-cap-l text-white/50">{{ $t('labels.explore') }}</p>
			</div>
		</div>

		<button
			type="button"
			v-on:click="closeReels"
			class="fixed right-8 top-7 z-[930] size-11 rounded-full text-white/85 inline-flex-center transition hover:bg-white/10 hover:text-white"
		>
			<SvgIcon name="x" type="solid" classes="size-8"></SvgIcon>
		</button>

		<div class="fixed right-7 top-1/2 z-[930] flex -translate-y-1/2 flex-col gap-4">
			<button
				type="button"
				v-bind:disabled="! canGoPrevious"
				v-on:click="goPrevious"
				class="size-14 rounded-full bg-white/10 text-white backdrop-blur inline-flex-center transition hover:bg-white/15 disabled:cursor-default disabled:opacity-30"
			>
				<SvgIcon name="chevron-up" type="solid" classes="size-8"></SvgIcon>
			</button>

			<button
				type="button"
				v-bind:disabled="! canGoNext"
				v-on:click="goNext"
				class="size-14 rounded-full bg-white/10 text-white backdrop-blur inline-flex-center transition hover:bg-white/15 disabled:cursor-default disabled:opacity-30"
			>
				<SvgIcon name="chevron-down" type="solid" classes="size-8"></SvgIcon>
			</button>
		</div>

		<div
			ref="scrollerRef"
			v-on:scroll.passive="handleScroll"
			class="relative z-10 h-[100dvh] snap-y snap-mandatory overflow-y-auto overscroll-contain bg-transparent hidden-scroll"
		>
			<div v-if="state.isLoading" class="h-[100dvh] inline-flex-center text-white">
				<div class="colibri-primary-animation"></div>
			</div>

			<template v-else-if="posts.length">
				<ReelItem
					v-for="(postData, index) in posts"
					v-bind:key="postData.id"
					v-bind:postData="postData"
					v-bind:active="index === state.activeIndex"
					v-bind:isNear="Math.abs(index - state.activeIndex) <= nearRadius"
					v-bind:distanceFromActive="Math.abs(index - state.activeIndex)"
					v-bind:position="index"
					v-bind:feedSessionId="reelsStore.feedSessionId"
				></ReelItem>

				<div v-if="state.isLoadingMore" class="h-28 snap-start inline-flex-center bg-transparent text-white">
					<div class="colibri-primary-animation"></div>
				</div>
			</template>

			<div v-else class="h-[100dvh] inline-flex-center bg-transparent px-10 text-center text-white/70">
				<div>
					<SvgIcon name="video-recorder" type="line" classes="size-12 mx-auto mb-4 text-white/45"></SvgIcon>
					<p class="text-par-s">{{ $t('empty_state.empty') }}</p>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { computed, defineComponent, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
	import { useRouter } from 'vue-router';
	import { getNetworkProfileSnapshot, subscribeNetworkProfile } from '@/kernel/services/network/index.js';
	import { prefetchReelsPlaybackWindow } from '@/kernel/services/media-prefetch/index.js';
	import { useExploreReelsStore } from '@D/store/explore/reels.store.js';

	import ReelItem from '@D/components/reels/ReelItem.vue';
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
			const router = useRouter();
			const reelsStore = useExploreReelsStore();
			let previousBodyOverflow = '';
			let previousHtmlOverflow = '';

			const state = reactive({
				isLoading: true,
				isLoadingMore: false,
				noMoreContent: false,
				activeIndex: 0,
				networkProfile: getNetworkProfileSnapshot()
			});

			let unsubscribeNetworkProfile = null;

			const posts = computed(() => {
				return reelsStore.posts;
			});

			const canGoPrevious = computed(() => {
				return state.activeIndex > 0;
			});

			const canGoNext = computed(() => {
				return posts.value.length > (state.activeIndex + 1) || ! state.noMoreContent;
			});

				const nearRadius = computed(() => {
					return state.networkProfile.reelsNearRadius;
				});

				const warmPlaybackWindow = () => {
					prefetchReelsPlaybackWindow(posts.value, state.activeIndex);
				};

				const updateActiveIndex = () => {
					const scroller = scrollerRef.value;

					if(! scroller?.clientHeight) {
						return;
					}

					const nextIndex = Math.max(0, Math.min(posts.value.length - 1, Math.round(scroller.scrollTop / scroller.clientHeight)));

					if(state.activeIndex === nextIndex) {
						return;
					}

					state.activeIndex = nextIndex;
					warmPlaybackWindow();
				};

			const scrollToIndex = (index) => {
				const scroller = scrollerRef.value;

				if(! scroller?.clientHeight || ! posts.value.length) {
					return false;
				}

				const safeIndex = Math.max(0, Math.min(posts.value.length - 1, index));

				scroller.scrollTo({
					top: safeIndex * scroller.clientHeight,
					behavior: 'smooth'
				});
			};

			const maybeLoadMore = async () => {
				const scroller = scrollerRef.value;

				if(! scroller || state.isLoadingMore || state.noMoreContent || ! posts.value.length) {
					return;
				}

				const distanceToBottom = scroller.scrollHeight - (scroller.scrollTop + scroller.clientHeight);

				if(distanceToBottom > (scroller.clientHeight * 4)) {
					return;
				}

				state.isLoadingMore = true;

					try {
						const response = await reelsStore.loadNextPage();
						state.noMoreContent = ! reelsStore.appendPosts(response.data.data);
						warmPlaybackWindow();
					} catch (error) {
						state.noMoreContent = true;
					} finally {
					state.isLoadingMore = false;
				}
			};

			const ensureNextPage = async () => {
				if(posts.value.length > (state.activeIndex + 1) || state.noMoreContent || state.isLoadingMore) {
					return;
				}

				await maybeLoadMore();
			};

			const goPrevious = () => {
				scrollToIndex(state.activeIndex - 1);
			};

			const goNext = async () => {
				if(posts.value.length <= (state.activeIndex + 1)) {
					await ensureNextPage();
				}

				scrollToIndex(state.activeIndex + 1);
			};

			const handleScroll = () => {
				updateActiveIndex();
				maybeLoadMore();
			};

			const closeReels = () => {
				if(window.history.state?.back) {
					router.back();
				}
				else {
					router.push({
						name: 'explore_posts'
					});
				}
			};

			const handleKeydown = (event) => {
				if(event.key === 'Escape') {
					closeReels();
				}
				else if(event.key === 'ArrowUp') {
					event.preventDefault();
					goPrevious();
				}
				else if(event.key === 'ArrowDown') {
					event.preventDefault();
					goNext();
				}
			};

			const lockPageScroll = () => {
				previousBodyOverflow = document.body.style.overflow;
				previousHtmlOverflow = document.documentElement.style.overflow;

				document.body.style.overflow = 'hidden';
				document.documentElement.style.overflow = 'hidden';
			};

			const unlockPageScroll = () => {
				document.body.style.overflow = previousBodyOverflow;
				document.documentElement.style.overflow = previousHtmlOverflow;
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
						warmPlaybackWindow();
					});
				};

				onMounted(() => {
					unsubscribeNetworkProfile = subscribeNetworkProfile((networkProfile) => {
						state.networkProfile = networkProfile;
						warmPlaybackWindow();
					});

				lockPageScroll();
				window.addEventListener('keydown', handleKeydown);
				loadInitial();
			});

			onUnmounted(() => {
				unsubscribeNetworkProfile?.();
				unlockPageScroll();
				window.removeEventListener('keydown', handleKeydown);
			});

				watch(() => props.hash_id, () => {
					loadInitial();
				});

			watch(() => posts.value.length, () => {
				state.activeIndex = Math.max(0, Math.min(state.activeIndex, Math.max(0, posts.value.length - 1)));
				warmPlaybackWindow();

				nextTick(() => {
					updateActiveIndex();
					maybeLoadMore();
				});
			});

			return {
				state: state,
				posts: posts,
				scrollerRef: scrollerRef,
				reelsStore: reelsStore,
				nearRadius: nearRadius,
				handleScroll: handleScroll,
				canGoPrevious: canGoPrevious,
				canGoNext: canGoNext,
				goPrevious: goPrevious,
				goNext: goNext,
				closeReels: closeReels
			};
		},
		components: {
			ReelItem: ReelItem,
			SvgIcon: SvgIcon
		}
	});
</script>
