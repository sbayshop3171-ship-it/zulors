<template>
	<div class="absolute inset-0 bg-black">
		<template v-if="sourceUrl && canRenderVideo">
			<video
				ref="videoPlayerRef"
				class="size-full object-contain bg-black"
				webkit-playsinline
				playsinline
				preload="metadata"
				v-bind:poster="mediaItem.thumbnail_url"
				v-bind:muted="state.isMuted"
				v-on:click.stop="handleSurfaceTap"
				v-on:loadedmetadata="handleVideoReady"
				v-on:canplay="handleVideoReady"
				v-on:timeupdate="syncProgress"
				v-on:progress="syncBuffered"
				loop
			>
				<source v-bind:src="sourceUrl" type="video/mp4">
			</video>
		</template>
		<template v-else-if="mediaItem.thumbnail_url">
			<img v-bind:src="mediaItem.thumbnail_url" alt="" class="size-full object-contain bg-black">
		</template>
		<template v-else>
			<div class="size-full inline-flex-center bg-black text-white/60">
				<div class="colibri-primary-animation"></div>
			</div>
		</template>

		<div v-if="state.manualPaused && active && ! blocked" class="pointer-events-none absolute inset-0 inline-flex-center">
			<div class="size-16 rounded-full bg-black/45 inline-flex-center text-white">
				<SvgIcon name="play" type="solid" classes="size-9"></SvgIcon>
			</div>
		</div>

		<div class="absolute left-0 right-0 bottom-0 h-0.5 bg-white/20">
			<span class="block h-full bg-white" v-bind:style="{ width: `${displayProgress}%` }"></span>
		</div>

		<div class="absolute top-24 right-3">
			<PrimaryIconButton
				v-on:click.stop="toggleMute"
				v-bind:iconName="state.isMuted ? 'volume-x' : 'volume-max'"
				buttonColor="text-white"
				hoverText="hover:text-white"
				hoverBg="hover:bg-white/10"
				iconSize="5"
				iconAreaSize="9"
			></PrimaryIconButton>
		</div>
	</div>
</template>

<script>
	import { computed, defineComponent, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';

	export default defineComponent({
		emits: ['double-tap'],
		props: {
			mediaItem: {
				type: Object,
				required: true
			},
			postData: {
				type: Object,
				required: true
			},
			active: {
				type: Boolean,
				default: false
			},
			isNear: {
				type: Boolean,
				default: false
			},
			blocked: {
				type: Boolean,
				default: false
			},
			feedSessionId: {
				type: String,
				default: ''
			},
			position: {
				type: Number,
				default: 0
			}
		},
		setup: function(props, context) {
			const videoPlayerRef = ref(null);
			const state = reactive({
				isMuted: true,
				isLoaded: false,
				isPlaying: false,
				manualPaused: false,
				playbackTime: 0,
				durationSeconds: 0,
				bufferedBar: 0,
				watchStartedAt: null,
				watchMsSinceFlush: 0,
				totalWatchMs: 0,
				lastPlaybackTime: 0,
				loopCount: 0,
				sessionId: `reel-video-${Date.now()}-${Math.random().toString(36).slice(2)}`,
				telemetryTimer: null
			});

			let tapTimer = null;

			const sourceUrl = computed(() => {
				return props.mediaItem.preview_url || props.mediaItem.source_url || '';
			});

			const canRenderVideo = computed(() => {
				return props.active || props.isNear;
			});

			const displayProgress = computed(() => {
				const durationSeconds = videoDurationSeconds();

				return durationSeconds ? Math.min(100, Math.max(0, (state.playbackTime / durationSeconds) * 100)) : 0;
			});

			const setMuted = () => {
				state.isMuted = localStorage.getItem('videoPlayerMuted') !== '0';

				if(videoPlayerRef.value) {
					videoPlayerRef.value.muted = state.isMuted;
				}
			};

			const videoDurationSeconds = () => {
				const videoDuration = Number(videoPlayerRef.value?.duration || 0);

				if(Number.isFinite(videoDuration) && videoDuration > 0) {
					return videoDuration;
				}

				const mediaDuration = Number(props.mediaItem.metadata?.duration?.seconds || props.mediaItem.metadata?.duration_seconds || 0);

				return Number.isFinite(mediaDuration) && mediaDuration > 0 ? mediaDuration : 0;
			};

			const syncBuffered = () => {
				const videoElement = videoPlayerRef.value;
				const durationSeconds = videoDurationSeconds();

				if(! videoElement || ! durationSeconds || ! videoElement.buffered?.length) {
					state.bufferedBar = 0;
					return;
				}

				state.bufferedBar = Math.min(100, Math.max(0, (videoElement.buffered.end(videoElement.buffered.length - 1) / durationSeconds) * 100));
			};

			const syncProgress = () => {
				const videoElement = videoPlayerRef.value;

				if(! videoElement) {
					return;
				}

				const currentTime = videoElement.currentTime || 0;

				state.durationSeconds = videoDurationSeconds();
				state.playbackTime = currentTime;
				syncBuffered();

				if(state.durationSeconds && state.lastPlaybackTime > 1 && currentTime < (state.lastPlaybackTime - 0.75)) {
					state.loopCount += 1;
					flushVideoTelemetry('video_loop');
				}

				state.lastPlaybackTime = currentTime;
			};

			const handleVideoReady = () => {
				state.isLoaded = true;
				state.durationSeconds = videoDurationSeconds();
				syncProgress();

				if(props.active && ! props.blocked && ! state.manualPaused) {
					playVideo();
				}
			};

			const startWatchSession = () => {
				if(! state.watchStartedAt) {
					state.watchStartedAt = Date.now();
				}
			};

			const collectWatchTime = () => {
				if(state.watchStartedAt) {
					const watchedMs = Date.now() - state.watchStartedAt;

					state.watchMsSinceFlush += watchedMs;
					state.totalWatchMs += watchedMs;
					state.watchStartedAt = null;
				}
			};

			const flushVideoTelemetry = (eventType = 'video_watch') => {
				collectWatchTime();

				if(! props.postData?.id || ! props.mediaItem?.id || ! videoPlayerRef.value) {
					return false;
				}

				const watchSeconds = Math.round(state.watchMsSinceFlush / 100) / 10;
				const durationSeconds = videoDurationSeconds();

				if(watchSeconds < 1 && eventType !== 'video_loop') {
					if(state.isPlaying) {
						startWatchSession();
					}

					return false;
				}

				colibriAPI().userTimeline().with({
					events: [{
						event_type: eventType,
						post_id: props.postData.id,
						media_id: props.mediaItem.id,
						watch_time_seconds: watchSeconds,
						duration_seconds: durationSeconds,
						current_time_seconds: Math.round((videoPlayerRef.value.currentTime || 0) * 10) / 10,
						completion_rate: durationSeconds ? Math.round((state.totalWatchMs / 1000 / durationSeconds) * 10000) / 10000 : 0,
						loop_count: state.loopCount,
						session_id: props.feedSessionId || state.sessionId,
						playback_session_id: state.sessionId,
						feed_type: 'reels',
						source: 'reels',
						position: props.position,
						is_muted: state.isMuted
					}]
				}).sendTo('telemetry/events').catch(() => {});

				state.watchMsSinceFlush = 0;
				state.loopCount = 0;

				if(state.isPlaying) {
					startWatchSession();
				}
			};

			const startTelemetryTimer = () => {
				stopTelemetryTimer();

				state.telemetryTimer = setInterval(() => {
					flushVideoTelemetry('video_watch');
				}, 5000);
			};

			const stopTelemetryTimer = () => {
				if(state.telemetryTimer) {
					clearInterval(state.telemetryTimer);
					state.telemetryTimer = null;
				}
			};

			const playVideo = () => {
				if(! state.isLoaded || ! videoPlayerRef.value || props.blocked || ! props.active) {
					return false;
				}

				setMuted();

				videoPlayerRef.value.play().then(() => {
					state.isPlaying = true;
					startWatchSession();
					startTelemetryTimer();
				}).catch((error) => {
					state.isPlaying = false;
					stopTelemetryTimer();

					if(error.name === 'NotAllowedError') {
						console.info('Cannot play reel because user has not interacted with the page yet');
					}
				});
			};

			const pauseVideo = () => {
				if(videoPlayerRef.value) {
					videoPlayerRef.value.pause();
				}

				state.isPlaying = false;
				flushVideoTelemetry('video_watch');
				stopTelemetryTimer();
			};

			const togglePlay = () => {
				if(props.blocked || ! state.isLoaded) {
					return false;
				}

				if(state.isPlaying) {
					state.manualPaused = true;
					pauseVideo();
				}
				else {
					state.manualPaused = false;
					playVideo();
				}
			};

			const handleSurfaceTap = () => {
				if(tapTimer) {
					clearTimeout(tapTimer);
					tapTimer = null;
					context.emit('double-tap');
					return;
				}

				tapTimer = setTimeout(() => {
					tapTimer = null;
					togglePlay();
				}, 220);
			};

			const toggleMute = () => {
				state.isMuted = ! state.isMuted;

				if(videoPlayerRef.value) {
					videoPlayerRef.value.muted = state.isMuted;
				}

				localStorage.setItem('videoPlayerMuted', state.isMuted ? '1' : '0');
			};

			watch(() => props.active, (isActive) => {
				if(isActive) {
					state.manualPaused = false;

					nextTick(() => {
						playVideo();
					});
				}
				else {
					pauseVideo();
				}
			});

			watch(() => props.blocked, (isBlocked) => {
				if(isBlocked) {
					pauseVideo();
				}
				else if(props.active) {
					playVideo();
				}
			});

			watch(() => props.mediaItem?.id, () => {
				pauseVideo();
				state.isLoaded = false;
				state.playbackTime = 0;
				state.durationSeconds = 0;
				state.totalWatchMs = 0;
				state.watchMsSinceFlush = 0;
				state.loopCount = 0;
				state.lastPlaybackTime = 0;
				state.sessionId = `reel-video-${Date.now()}-${Math.random().toString(36).slice(2)}`;
			});

			onMounted(() => {
				setMuted();

				if(videoPlayerRef.value?.readyState >= 1) {
					handleVideoReady();
				}
			});

			onUnmounted(() => {
				if(tapTimer) {
					clearTimeout(tapTimer);
				}

				pauseVideo();
			});

			return {
				videoPlayerRef: videoPlayerRef,
				state: state,
				sourceUrl: sourceUrl,
				canRenderVideo: canRenderVideo,
				displayProgress: displayProgress,
				handleSurfaceTap: handleSurfaceTap,
				handleVideoReady: handleVideoReady,
				syncProgress: syncProgress,
				syncBuffered: syncBuffered,
				toggleMute: toggleMute
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			SvgIcon: SvgIcon
		}
	});
</script>
