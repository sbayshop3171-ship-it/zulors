<template>
	<Teleport to="body">
		<div v-if="open" class="fixed inset-0 z-[70] bg-black/35" v-on:click.self="closePicker">
			<div class="absolute bottom-0 left-0 right-0 mx-auto flex max-h-[78vh] w-full max-w-2xl flex-col rounded-t-[28px] bg-[#202128] text-white shadow-2xl">
				<div class="flex justify-center pt-3">
					<div class="h-1 w-12 rounded-full bg-white/65"></div>
				</div>
				<div class="px-4 pt-3">
					<div class="flex h-11 items-center gap-3 rounded-xl bg-[#2b2f36] px-4">
						<SvgIcon name="search-lg" type="line" classes="size-5 shrink-0 text-white/55"></SvgIcon>
						<input
							v-model="searchQuery"
							type="search"
							placeholder="Search..."
							class="min-w-0 flex-1 bg-transparent text-par-l text-white outline-hidden placeholder:text-white/55"
						>
						<button v-if="searchQuery" v-on:click="searchQuery = ''" type="button" class="inline-flex size-7 items-center justify-center rounded-full text-white/70 hover:bg-white/10 hover:text-white">
							<SvgIcon name="x" type="solid" classes="size-4"></SvgIcon>
						</button>
					</div>
					<div class="mt-4 flex gap-2 overflow-x-auto pb-1">
						<button
							v-for="tabItem in tabs"
							v-bind:key="tabItem.value"
							v-on:click="activeTab = tabItem.value"
							type="button"
							v-bind:class="activeTab === tabItem.value ? 'bg-white text-black' : 'bg-[#262932] text-white'"
							class="h-9 shrink-0 rounded-lg px-4 text-par-s font-semibold transition-colors"
						>
							{{ tabItem.label }}
						</button>
					</div>
				</div>

				<div class="min-h-0 flex-1 overflow-y-auto px-4 pb-5 pt-2">
					<div v-if="isLoading" class="space-y-3 pt-2">
						<div v-for="item in 8" v-bind:key="item" class="flex items-center gap-3">
							<div class="size-12 shrink-0 rounded-md bg-white/10"></div>
							<div class="min-w-0 flex-1">
								<div class="h-4 w-44 max-w-full rounded bg-white/10"></div>
								<div class="mt-2 h-3 w-56 max-w-full rounded bg-white/10"></div>
							</div>
						</div>
					</div>
					<div v-else-if="loadError" class="flex min-h-52 flex-col items-center justify-center px-6 text-center text-white/70">
						<SvgIcon name="alert-circle" type="line" classes="mb-3 size-9"></SvgIcon>
						<p class="text-par-s">Unable to load music right now.</p>
						<button v-on:click="fetchTracks" type="button" class="mt-3 rounded-lg bg-white px-4 py-2 text-par-s font-semibold text-black">Try again</button>
					</div>
					<div v-else-if="tracks.length" class="space-y-1">
						<div
							v-for="trackItem in tracks"
							v-bind:key="trackItem.id"
							class="flex w-full items-center gap-3 rounded-lg py-2 text-left transition-colors hover:bg-white/10"
						>
							<button v-on:click="selectTrack(trackItem)" type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left">
							<div class="size-12 shrink-0 overflow-hidden rounded-md bg-white/10">
								<img v-if="trackItem.cover_url" v-bind:src="trackItem.cover_url" class="size-full object-cover" alt="">
								<div v-else class="flex size-full items-center justify-center bg-white/10 text-white/70">
									<SvgIcon name="music-note-01" type="line" classes="size-6"></SvgIcon>
								</div>
							</div>
							<div class="min-w-0 flex-1">
								<div class="flex min-w-0 items-center gap-1">
									<span class="truncate text-par-m font-semibold leading-5 text-white">{{ trackItem.title }}</span>
									<span v-if="trackItem.license_type === 'ugc-original'" class="rounded bg-white/18 px-1 text-[10px] font-semibold uppercase leading-4 text-white/85">E</span>
								</div>
								<p class="truncate text-par-s leading-5 text-white/58">
									{{ trackItem.artist || 'Original audio' }} <span v-if="trackItem.duration_seconds"> &middot; {{ formatDuration(trackItem.duration_seconds) }}</span>
								</p>
							</div>
							</button>
							<button v-on:click.stop="toggleSaved(trackItem.id)" type="button" class="inline-flex size-10 shrink-0 items-center justify-center rounded-full text-white hover:bg-white/10">
								<SvgIcon v-bind:name="savedTrackIds.includes(trackItem.id) ? 'bookmark-minus' : 'bookmark'" type="line" classes="size-6"></SvgIcon>
							</button>
						</div>
					</div>
					<div v-else class="flex min-h-52 flex-col items-center justify-center text-center text-white/70">
						<SvgIcon name="music-note-01" type="line" classes="mb-3 size-9"></SvgIcon>
						<p class="text-par-s">No music found</p>
					</div>
				</div>
			</div>
		</div>
	</Teleport>
</template>

<script>
	import { defineComponent, onBeforeUnmount, ref, watch } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	export default defineComponent({
		props: {
			open: {
				type: Boolean,
				default: false
			},
			selectedTrack: {
				type: Object,
				default: null
			}
		},
		emits: ['close', 'select'],
		setup: function(props, context) {
			const activeTab = ref('for_you');
			const searchQuery = ref('');
			const tracks = ref([]);
			const isLoading = ref(false);
			const loadError = ref(false);
			const savedTrackIds = ref([]);
			const requestIndex = ref(0);
			let searchTimer = null;

			const tabs = [
				{ value: 'for_you', label: 'For you' },
				{ value: 'trending', label: 'Trending' },
				{ value: 'saved', label: 'Saved' },
				{ value: 'original_audio', label: 'Original audio' }
			];

			const fetchTracks = async () => {
				const currentRequest = requestIndex.value + 1;
				requestIndex.value = currentRequest;
				isLoading.value = true;
				loadError.value = false;

				try {
					const response = await colibriAPI().storyMusic().params({
						tab: activeTab.value,
						search: searchQuery.value,
						sort: activeTab.value === 'trending' ? 'trending' : (activeTab.value === 'original_audio' ? 'newest' : 'default'),
						per_page: 30
					}).getFrom('tracks');

					if(currentRequest !== requestIndex.value) {
						return;
					}

					const payload = response.data.data;
					tracks.value = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
				}
				catch (error) {
					if(currentRequest === requestIndex.value) {
						tracks.value = [];
						loadError.value = true;
						toastError(error.response?.data?.message || error.message || 'Unable to load music.');
					}
				}
				finally {
					if(currentRequest === requestIndex.value) {
						isLoading.value = false;
					}
				}
			};

			const scheduleFetch = () => {
				clearTimeout(searchTimer);
				searchTimer = setTimeout(fetchTracks, 250);
			};

			watch(() => props.open, (isOpen) => {
				if(isOpen) {
					fetchTracks();
				}
			}, { immediate: true });

			watch(activeTab, fetchTracks);
			watch(searchQuery, scheduleFetch);

			onBeforeUnmount(() => {
				clearTimeout(searchTimer);
			});

			return {
				activeTab: activeTab,
				searchQuery: searchQuery,
				tracks: tracks,
				isLoading: isLoading,
				loadError: loadError,
				savedTrackIds: savedTrackIds,
				tabs: tabs,
				closePicker: () => {
					context.emit('close');
				},
				selectTrack: (trackItem) => {
					context.emit('select', trackItem);
				},
				toggleSaved: (trackId) => {
					if(savedTrackIds.value.includes(trackId)) {
						savedTrackIds.value = savedTrackIds.value.filter((id) => id !== trackId);
					}
					else {
						savedTrackIds.value = [...savedTrackIds.value, trackId];
					}
				},
				formatDuration: (seconds) => {
					const totalSeconds = Math.max(0, Number(seconds) || 0);
					const minutes = Math.floor(totalSeconds / 60);
					const leftSeconds = String(totalSeconds % 60).padStart(2, '0');

					return `${minutes}:${leftSeconds}`;
				}
			};
		}
	});
</script>
