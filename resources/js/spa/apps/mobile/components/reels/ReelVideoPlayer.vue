<template>
	<div class="absolute inset-0 bg-black">
		<img
			v-if="mediaItem.thumbnail_url"
			v-bind:src="mediaItem.thumbnail_url"
			alt=""
			class="pointer-events-none absolute inset-0 size-full scale-110 object-cover opacity-35 blur-xl"
		>

		<template v-if="sourceUrl && canRenderVideo">
			<video
				ref="videoPlayerRef"
				class="relative z-10 size-full object-contain bg-transparent transition-opacity duration-150"
				v-bind:class="state.hasVisualFrame ? 'opacity-100' : 'opacity-0'"
				webkit-playsinline
				playsinline
				v-bind:src="nativeVideoUrl || null"
				v-bind:preload="videoPreload"
				v-bind:poster="mediaItem.thumbnail_url"
				v-bind:muted="state.isMuted"
				v-on:click.stop="handleSurfaceTap"
				v-on:loadedmetadata="handleLoadedMetadata"
				v-on:loadeddata="handleLoadedData"
				v-on:canplay="handleCanPlay"
				v-on:canplaythrough="handleCanPlay"
				v-on:playing="handlePlaying"
				v-on:waiting="handleWaiting"
				v-on:stalled="handleWaiting"
				v-on:error="handleNativeError"
				v-on:timeupdate="syncProgress"
				v-on:progress="syncBuffered"
				loop
			></video>
		</template>
		<template v-else-if="mediaItem.thumbnail_url">
			<img v-bind:src="mediaItem.thumbnail_url" alt="" class="relative z-10 size-full object-contain bg-transparent">
		</template>
		<template v-else>
			<div class="relative z-10 size-full inline-flex-center bg-black text-white/60">
				<div class="colibri-primary-animation"></div>
			</div>
		</template>

		<img
			v-if="showPosterCover"
			v-bind:src="mediaItem.thumbnail_url"
			alt=""
			class="pointer-events-none absolute inset-0 z-20 size-full object-contain bg-transparent transition-opacity duration-150"
		>

		<div v-if="state.manualPaused && active && ! blocked" class="pointer-events-none absolute inset-0 z-30 inline-flex-center">
			<div class="size-16 rounded-full bg-black/45 inline-flex-center text-white">
				<SvgIcon name="play" type="solid" classes="size-9"></SvgIcon>
			</div>
		</div>

		<div v-if="showBufferIndicator" class="pointer-events-none absolute inset-0 z-30 inline-flex-center">
			<div class="size-14 rounded-full bg-black/45 inline-flex-center text-white">
				<div class="colibri-primary-animation"></div>
			</div>
		</div>

		<div v-if="state.showRetry && active && ! blocked" class="absolute inset-0 z-40 inline-flex-center">
			<button
				type="button"
				v-on:click.stop="retryPlayback"
				class="inline-flex items-center gap-2 rounded-full bg-black/75 px-5 py-3 font-semibold text-white shadow-lg"
			>
				<SvgIcon name="refresh-cw-04" type="line" classes="size-5"></SvgIcon>
				<span>{{ $t('labels.retry_video') }}</span>
			</button>
		</div>

		<div class="absolute left-0 right-0 bottom-0 z-30 h-0.5 bg-white/20">
			<span class="block h-full bg-white" v-bind:style="{ width: `${displayProgress}%` }"></span>
		</div>

		<div class="absolute top-32 right-3 z-30">
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
	import Hls from 'hls.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { buildAdaptiveVideoSource, canVideoElementPlayNatively, getDirectPlaybackFallback } from '@/kernel/services/media/adaptive-video/index.js';
	import { buildReelInteractionSignal } from '@/kernel/services/feed-session/reels-session-signals.js';
	import { getNetworkProfileSnapshot, isSlowNetworkProfile, subscribeNetworkProfile } from '@/kernel/services/network/index.js';
	import { createReelPlaybackGestureController } from '@/kernel/services/media/reel-playback-gestures.js';
	import { queueMetric } from '@/kernel/services/performance/index.js';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';

	export default defineComponent({
		emits: ['double-tap', 'interaction'],
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
			distanceFromActive: {
				type: Number,
				default: 0
			},
			isNext: {
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
			},
			sponsoredAdId: {
				type: Number,
				default: 0
			}
		},
		setup: function(props, context) {
			const videoPlayerRef = ref(null);
			const state = reactive({
				isMuted: true,
				isLoaded: false,
					isReadyForPlayback: false,
					isPlaying: false,
					isBuffering: false,
					showBufferIndicator: false,
					showRetry: false,
					hasVisualFrame: false,
					manualPaused: false,
					playRequested: false,
				playbackTime: 0,
				durationSeconds: 0,
				bufferedBar: 0,
				watchStartedAt: null,
				watchMsSinceFlush: 0,
				totalWatchMs: 0,
				lastPlaybackTime: 0,
				loopCount: 0,
				firstFrameMs: 0,
				playbackActivatedAt: 0,
				sessionId: `reel-video-${Date.now()}-${Math.random().toString(36).slice(2)}`,
				telemetryTimer: null,
				bufferRecoveryTimer: null,
				bufferingGraceTimer: null,
				networkProfile: getNetworkProfileSnapshot(),
				nativeVideoUrl: '',
				stallCount: 0,
				retryCount: 0,
				resumePosition: 0,
				lastReportedRendition: '',
				forceMp4Fallback: false
			});

			let hlsInstance = null;
			let playbackGestureController = null;
			let unsubscribeNetworkProfile = null;
			let sponsoredVideoStarted = false;
			let sponsoredThreeSecondView = false;
			let sponsoredCompletedView = false;

			const recordSponsoredEvent = (eventType, payload = {}) => {
				if(! props.sponsoredAdId) {
					return;
				}

				colibriAPI().ads().params({ placement: 'reels' }).with({
					event_type: eventType,
					...payload
				}).sendTo(`event/${props.sponsoredAdId}`).catch(() => {});
			};

			const playbackSource = computed(() => {
				return buildAdaptiveVideoSource(props.mediaItem);
			});

			const sourceUrl = computed(() => {
				return playbackSource.value.url || '';
			});

			const canRenderVideo = computed(() => {
				return props.active || props.isNear;
			});

			const shouldWarmPlayback = computed(() => {
				return ! props.blocked && (props.active || (props.isNext && ! state.networkProfile.offline));
			});

			const shouldAttachPlaybackSource = computed(() => {
				return canRenderVideo.value && ! props.blocked && shouldWarmPlayback.value;
			});

			const nativeVideoUrl = computed(() => {
				return state.nativeVideoUrl;
			});

			const videoPreload = computed(() => {
				if(props.active) {
					return state.networkProfile.activeVideoPreload;
				}

					return shouldWarmPlayback.value ? 'metadata' : 'none';
				});

				const showBufferIndicator = computed(() => {
					return props.active && state.showBufferIndicator && ! props.blocked && ! state.manualPaused && ! state.showRetry;
				});

				const showPosterCover = computed(() => {
					return Boolean(props.mediaItem?.thumbnail_url)
						&& canRenderVideo.value
						&& ! props.blocked
						&& ! state.hasVisualFrame;
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

			const bufferedAheadSeconds = () => {
				const videoElement = videoPlayerRef.value;

				if(! videoElement?.buffered?.length) {
					return 0;
				}

				try {
					const currentTime = Number(videoElement.currentTime || 0);

					for(let index = 0; index < videoElement.buffered.length; index++) {
						const start = videoElement.buffered.start(index);
						const end = videoElement.buffered.end(index);

						if(currentTime >= start && currentTime <= end) {
							return Math.max(0, end - currentTime);
						}
					}

					return Math.max(0, videoElement.buffered.end(videoElement.buffered.length - 1) - currentTime);
				}
				catch(error) {
					return 0;
				}
			};

			const hlsStartPosition = () => {
				const currentTime = Number(videoPlayerRef.value?.currentTime || 0);

				return Number.isFinite(currentTime) && currentTime > 0
					? currentTime
					: -1;
			};

			const clearBufferRecoveryTimer = () => {
				if(state.bufferRecoveryTimer) {
					clearTimeout(state.bufferRecoveryTimer);
					state.bufferRecoveryTimer = null;
				}
			};

			const stopTelemetryTimer = () => {
				if(state.telemetryTimer) {
					clearInterval(state.telemetryTimer);
					state.telemetryTimer = null;
				}
			};

			const clearBufferingGraceTimer = () => {
				if(state.bufferingGraceTimer) {
					clearTimeout(state.bufferingGraceTimer);
					state.bufferingGraceTimer = null;
				}
			};

			const showBufferingAfterGrace = () => {
				clearBufferingGraceTimer();
				state.bufferingGraceTimer = setTimeout(() => {
					state.bufferingGraceTimer = null;
					state.showBufferIndicator = Boolean(state.isBuffering && props.active && ! state.showRetry);
				}, 450);
			};

			const recordPlaybackMetric = (metric, value = 1, details = {}) => {
				queueMetric(metric, value, {
					category: 'explore',
					...details
				});
			};

			const markPlaybackTerminalError = () => {
				clearBufferRecoveryTimer();
				clearBufferingGraceTimer();
				state.isBuffering = false;
				state.showBufferIndicator = false;
				state.showRetry = true;
				state.playRequested = false;
				state.isPlaying = false;
				stopTelemetryTimer();
				videoPlayerRef.value?.pause();
				recordPlaybackMetric('media_terminal_error', 1, {
					rendition: hlsInstance ? 'hls' : 'mp4'
				});
			};

			const updatePlaybackReadiness = (forceReady = false) => {
				const readyState = Number(videoPlayerRef.value?.readyState || 0);
				const hasBufferedAhead = bufferedAheadSeconds() >= state.networkProfile.reelsMinBufferSeconds;
				const isReady = forceReady
					|| ! isSlowNetworkProfile(state.networkProfile)
					|| hasBufferedAhead
					|| readyState >= 3;

				state.isReadyForPlayback = isReady;

				if(isReady) {
					state.isBuffering = false;
				}

				return isReady;
			};

			const syncBuffered = () => {
				const videoElement = videoPlayerRef.value;
				const durationSeconds = videoDurationSeconds();

				if(! videoElement || ! durationSeconds || ! videoElement.buffered?.length) {
					state.bufferedBar = 0;
					return;
				}

				state.bufferedBar = Math.min(100, Math.max(0, (videoElement.buffered.end(videoElement.buffered.length - 1) / durationSeconds) * 100));
				updatePlaybackReadiness(false);
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
				if(props.sponsoredAdId && props.active && currentTime >= 3 && ! sponsoredThreeSecondView) {
					sponsoredThreeSecondView = true;
					recordSponsoredEvent('three_second_view', { watch_time_seconds: currentTime });
				}
				if(props.sponsoredAdId && props.active && state.durationSeconds > 0 && currentTime / state.durationSeconds >= 0.95 && ! sponsoredCompletedView) {
					sponsoredCompletedView = true;
					recordSponsoredEvent('completed_view', { watch_time_seconds: currentTime, completion_rate: currentTime / state.durationSeconds });
				}

				if(state.durationSeconds && state.lastPlaybackTime > 1 && currentTime < (state.lastPlaybackTime - 0.75)) {
					state.loopCount += 1;
					flushVideoTelemetry('video_loop', {
						emitInteraction: true
					});
				}

				state.lastPlaybackTime = currentTime;
					updatePlaybackReadiness(false);
				};

				const markVisualFrameReady = () => {
					if(Number(videoPlayerRef.value?.readyState || 0) >= 2) {
						state.hasVisualFrame = true;
					}
				};

				const handleLoadedMetadata = () => {
					state.isLoaded = true;
					state.durationSeconds = videoDurationSeconds();
					if(state.resumePosition > 0 && videoPlayerRef.value) {
						try {
							videoPlayerRef.value.currentTime = Math.min(state.resumePosition, Math.max(0, state.durationSeconds - 0.1));
						}
						catch(error) {}
						state.resumePosition = 0;
					}
					syncProgress();
					updatePlaybackReadiness(false);
					markVisualFrameReady();
				};

				const handleLoadedData = () => {
					handleLoadedMetadata();
					state.hasVisualFrame = true;

					if(! state.firstFrameMs && state.playbackActivatedAt) {
						state.firstFrameMs = Math.max(0, Date.now() - state.playbackActivatedAt);
					}

					updatePlaybackReadiness(true);

					if(props.active && ! props.blocked && ! state.manualPaused && ! state.showRetry && ! state.isPlaying) {
						nextTick(() => {
							playVideo();
						});
					}
				};

				const handleCanPlay = () => {
					handleLoadedData();
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

			const flushVideoTelemetry = (eventType = 'video_watch', options = {}) => {
				collectWatchTime();

				if(! props.postData?.id || ! props.mediaItem?.id || ! videoPlayerRef.value) {
					return false;
				}

				const watchSeconds = Math.round(state.watchMsSinceFlush / 100) / 10;
				const durationSeconds = videoDurationSeconds();
				const completionRate = durationSeconds ? Math.round((state.totalWatchMs / 1000 / durationSeconds) * 10000) / 10000 : 0;
				const interactionSignal = options.emitInteraction
					? buildReelInteractionSignal({
						postId: props.postData.id,
						eventType: eventType,
						watchMs: state.watchMsSinceFlush,
						totalWatchMs: state.totalWatchMs,
						durationSeconds: durationSeconds,
						completionRate: completionRate,
						loopCount: state.loopCount
					})
					: null;

				if(interactionSignal) {
					context.emit('interaction', interactionSignal);
				}

				if(watchSeconds < 1 && eventType !== 'video_loop') {
					if(state.isPlaying) {
						startWatchSession();
					}

					return false;
				}
				if(props.sponsoredAdId && eventType === 'video_watch') {
					recordSponsoredEvent('video_watch', {
						watch_time_seconds: watchSeconds,
						completion_rate: completionRate
					});
				}

				colibriAPI().userTimeline().with({
					events: [{
						event_type: eventType,
						post_id: props.postData.id,
						media_id: props.mediaItem.id,
						watch_time_seconds: watchSeconds,
						duration_seconds: durationSeconds,
						current_time_seconds: Math.round((videoPlayerRef.value.currentTime || 0) * 10) / 10,
						completion_rate: completionRate,
						loop_count: state.loopCount,
						session_id: props.feedSessionId || state.sessionId,
						playback_session_id: state.sessionId,
						feed_type: 'reels',
						source: 'reels',
						position: props.position,
						is_muted: state.isMuted,
						first_frame_ms: state.firstFrameMs,
						stall_count: state.stallCount,
						swipe_away_ms: Math.round(state.totalWatchMs),
						'3s_view': state.totalWatchMs >= 3000,
						'50p_complete': completionRate >= 0.5,
						'95p_complete': completionRate >= 0.95,
						mute_state: state.isMuted ? 'muted' : 'unmuted',
						visible_window_index: props.position
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

			const destroyHlsInstance = () => {
				if(hlsInstance) {
					hlsInstance.destroy();
					hlsInstance = null;
				}
			};

			const resetPlaybackState = (resetSession = false) => {
				clearBufferRecoveryTimer();
				clearBufferingGraceTimer();
				state.isLoaded = false;
					state.isReadyForPlayback = false;
					state.isPlaying = false;
					state.isBuffering = false;
					state.showBufferIndicator = false;
					state.showRetry = false;
					state.hasVisualFrame = false;
					state.playbackTime = 0;
				state.durationSeconds = 0;
				state.totalWatchMs = 0;
				state.watchMsSinceFlush = 0;
				state.loopCount = 0;
				state.firstFrameMs = 0;
				state.playbackActivatedAt = Date.now();
				state.lastPlaybackTime = 0;
				state.bufferedBar = 0;
				state.stallCount = 0;
				state.retryCount = 0;
				state.lastReportedRendition = '';

				if(resetSession) {
					state.sessionId = `reel-video-${Date.now()}-${Math.random().toString(36).slice(2)}`;
				}
			};

			const applyFallbackSource = () => {
				const fallbackSource = getDirectPlaybackFallback(props.mediaItem);
				const previousTransport = state.forceMp4Fallback ? 'mp4' : playbackSource.value.transport;

				if(! fallbackSource?.url || fallbackSource.url === state.nativeVideoUrl) {
					return false;
				}

				const resumePosition = Number(videoPlayerRef.value?.currentTime || state.playbackTime || 0);
				destroyHlsInstance();
				state.nativeVideoUrl = fallbackSource.url;
				state.forceMp4Fallback = true;
				if(previousTransport === 'hls') {
					recordPlaybackMetric('media_fallback', 1, { rendition: 'mp4' });
				}
				resetPlaybackState(false);
				state.resumePosition = resumePosition;
				state.playbackActivatedAt = Date.now();

				nextTick(() => {
					const videoElement = videoPlayerRef.value;

					if(videoElement) {
						videoElement.load();

							if(videoElement.readyState >= 1) {
								handleLoadedMetadata();
							}

							if(videoElement.readyState >= 2) {
								handleLoadedData();
							}
						}
					});

				return true;
			};

				const recoverPlayback = () => {
				if(state.bufferRecoveryTimer) {
					return;
				}

				if(! props.active || props.blocked || state.manualPaused) {
					return;
				}

				if(state.retryCount >= 3) {
					if(hlsInstance && applyFallbackSource()) {
						return;
					}

					markPlaybackTerminalError('retry_limit');
					return;
				}

				state.retryCount += 1;

				state.bufferRecoveryTimer = setTimeout(() => {
					state.bufferRecoveryTimer = null;
					if(! props.active || props.blocked || state.manualPaused || ! state.isBuffering) {
						return;
					}

					if(hlsInstance) {
						if(isSlowNetworkProfile(state.networkProfile) && Number.isInteger(hlsInstance.currentLevel) && hlsInstance.currentLevel > 0) {
							hlsInstance.nextLevel = Math.max(0, hlsInstance.currentLevel - 1);
						}

						try {
							hlsInstance.startLoad(hlsStartPosition());
						}
						catch(error) {
							if(! applyFallbackSource()) {
								markPlaybackTerminalError(error?.name || 'hls_recovery');
							}
						}
					}
					else if(videoPlayerRef.value) {
						state.resumePosition = Number(videoPlayerRef.value.currentTime || state.playbackTime || 0);
						try {
							videoPlayerRef.value.load();
						}
						catch(error) {}
					}

					playVideo();
				}, state.networkProfile.stallRecoveryDelayMs);
				};

				const warmNativePlayback = () => {
					const videoElement = videoPlayerRef.value;

					if(! videoElement || ! state.nativeVideoUrl || ! shouldWarmPlayback.value) {
						return;
					}

					videoElement.preload = videoPreload.value;

					if(videoElement.networkState === 0 || (props.active && videoElement.readyState < 2)) {
						try {
							videoElement.load();
						}
						catch(error) {}
					}
				};

				const playVideo = () => {
					if(props.blocked || ! props.active || state.showRetry) {
						return false;
					}

					state.playRequested = true;

					if(! state.isLoaded || ! state.isReadyForPlayback || ! videoPlayerRef.value) {
						if(props.active && ! state.manualPaused) {
							state.isBuffering = true;
							showBufferingAfterGrace();
							syncPlaybackWindow();
						}

						return false;
					}

					setMuted();

					videoPlayerRef.value.play().then(() => {
						state.playRequested = true;
						if(! state.isPlaying) {
							state.isPlaying = true;
							startWatchSession();
							startTelemetryTimer();
						}

						state.isBuffering = false;
						state.showRetry = false;
					}).catch((error) => {
						if(error?.name === 'AbortError') {
							return;
						}

						state.isPlaying = false;
						state.isBuffering = false;
						state.showBufferIndicator = false;
						stopTelemetryTimer();
						markPlaybackTerminalError(error?.name || 'play_rejected');
					});
				};

			const pauseVideo = (options = {}) => {
				if(videoPlayerRef.value) {
					videoPlayerRef.value.pause();
				}

				state.isPlaying = false;
				state.isBuffering = false;
				flushVideoTelemetry('video_watch', options);
				stopTelemetryTimer();
				clearBufferRecoveryTimer();
			};

			const togglePlay = () => {
				if(props.blocked || ! props.active) {
					return false;
				}

				const currentlyPlaying = state.isPlaying || state.playRequested || (! state.manualPaused && props.active);

				if(currentlyPlaying) {
					state.manualPaused = true;
					state.playRequested = false;
					pauseVideo();
				}
				else {
					state.manualPaused = false;
					state.playRequested = true;
					state.showRetry = false;
					recordPlaybackMetric('media_play_request', 0, {
						rendition: playbackSource.value.transport
					});
					playVideo();
				}
			};

			const restorePlaybackSnapshot = (snapshot = {}) => {
				state.manualPaused = Boolean(snapshot.manualPaused);
				state.playRequested = Boolean(snapshot.playRequested);

				if(snapshot.shouldPlay) {
					state.showRetry = false;
					playVideo();
				}
				else if(state.manualPaused) {
					state.playRequested = false;
					pauseVideo();
				}
			};

			playbackGestureController = createReelPlaybackGestureController({
				getPlaybackSnapshot: () => ({
					manualPaused: state.manualPaused,
					playRequested: state.playRequested,
					shouldPlay: state.isPlaying || state.playRequested || (! state.manualPaused && props.active)
				}),
				onSingleTap: togglePlay,
				onDoubleTap: () => context.emit('double-tap'),
				restorePlayback: restorePlaybackSnapshot
			});

			const handleSurfaceTap = () => {
				playbackGestureController?.handleTap();
			};

			const retryPlayback = () => {
				state.showRetry = false;
				state.retryCount = 0;
				state.manualPaused = false;
				state.playRequested = true;
				state.isBuffering = true;
				showBufferingAfterGrace();

				if(hlsInstance) {
					try {
						hlsInstance.startLoad(hlsStartPosition());
					}
					catch(error) {
						if(! applyFallbackSource()) {
							markPlaybackTerminalError(error?.name || 'hls_retry');
						}
					}
				}
				else if(videoPlayerRef.value) {
					state.resumePosition = Number(videoPlayerRef.value.currentTime || state.playbackTime || 0);
					videoPlayerRef.value.load();
				}

				playVideo();
			};

			const toggleMute = () => {
				state.isMuted = ! state.isMuted;

				if(videoPlayerRef.value) {
					videoPlayerRef.value.muted = state.isMuted;
				}

				localStorage.setItem('videoPlayerMuted', state.isMuted ? '1' : '0');
			};

				const handlePlaying = () => {
					const recoveredFromStall = state.isBuffering && state.retryCount > 0;
					if(recoveredFromStall) {
						recordPlaybackMetric('media_recovery', state.retryCount, {
							rendition: hlsInstance ? 'hls' : 'mp4'
						});
					}

					state.isPlaying = true;
					state.isBuffering = false;
					state.showBufferIndicator = false;
					state.showRetry = false;
					state.retryCount = 0;
					state.playRequested = true;
					clearBufferRecoveryTimer();
					clearBufferingGraceTimer();
					const videoHeight = Number(videoPlayerRef.value?.videoHeight || 0);
					const isHlsSource = ! state.forceMp4Fallback && playbackSource.value.transport === 'hls';
					const renditionHeight = isHlsSource && videoHeight > 0
						? [240, 360, 480, 720].reduce((closest, candidate) => {
							return Math.abs(candidate - videoHeight) < Math.abs(closest - videoHeight) ? candidate : closest;
						}, 240)
						: 0;
					const rendition = isHlsSource ? (renditionHeight ? `${renditionHeight}p` : 'hls') : 'mp4';
					if(rendition !== state.lastReportedRendition) {
						state.lastReportedRendition = rendition;
						recordPlaybackMetric('media_rendition', renditionHeight || videoHeight || 1, { rendition: rendition });
					}
					state.hasVisualFrame = true;
					updatePlaybackReadiness(true);
				startWatchSession();
				startTelemetryTimer();
					if(props.sponsoredAdId && ! sponsoredVideoStarted) {
						sponsoredVideoStarted = true;
						recordSponsoredEvent('video_start');
					}
			};

			const handleWaiting = () => {
				if(! props.active || props.blocked || state.manualPaused) {
					return;
				}

				if(! state.isBuffering) {
					state.stallCount += 1;
					recordPlaybackMetric('media_stall', 1, {
						rendition: hlsInstance ? 'hls' : 'mp4'
					});
				}
				state.isBuffering = true;
				showBufferingAfterGrace();
				updatePlaybackReadiness(false);
				recoverPlayback();
			};

			const handleNativeError = () => {
				if(hlsInstance) {
					return;
				}

				if(! applyFallbackSource()) {
					handleWaiting();
				}
			};

			const handleHlsError = (event, data) => {
				if(data?.type === Hls.ErrorTypes.NETWORK_ERROR) {
					state.isBuffering = true;
					showBufferingAfterGrace();
					recoverPlayback();
				}

				if(! data?.fatal) {
					return;
				}

				switch(data.type) {
					case Hls.ErrorTypes.NETWORK_ERROR:
						if(! applyFallbackSource()) {
							try {
								hlsInstance?.startLoad(hlsStartPosition());
							}
							catch(error) {
								markPlaybackTerminalError(error?.name || 'hls_network');
							}
						}
						break;
					case Hls.ErrorTypes.MEDIA_ERROR:
						state.retryCount += 1;
						if(state.retryCount > 3) {
							if(! applyFallbackSource()) {
								markPlaybackTerminalError('hls_media_retry_limit');
							}
							break;
						}
						try {
							hlsInstance?.recoverMediaError();
						}
						catch(error) {
							if(! applyFallbackSource()) {
								markPlaybackTerminalError(error?.name || 'hls_media');
							}
						}
						break;
					default:
						if(! applyFallbackSource()) {
							markPlaybackTerminalError('hls_fatal');
						}
						break;
				}
			};

				const configurePlaybackSource = () => {
				const videoElement = videoPlayerRef.value;
				const source = state.forceMp4Fallback
					? (getDirectPlaybackFallback(props.mediaItem) || playbackSource.value)
					: playbackSource.value;

				destroyHlsInstance();
				state.nativeVideoUrl = '';
				resetPlaybackState(false);
				state.playbackActivatedAt = Date.now();

				if(! videoElement || ! source.url || ! shouldAttachPlaybackSource.value) {
					return;
				}

				const shouldUseHlsJs = source.transport === 'hls'
					&& Hls.isSupported()
					&& ! canVideoElementPlayNatively(videoElement, source.type);

				if(shouldUseHlsJs) {
					videoElement.removeAttribute('src');
					videoElement.load();

					hlsInstance = new Hls({
						autoStartLoad: false,
						enableWorker: true,
						capLevelToPlayerSize: true,
						lowLatencyMode: false,
						backBufferLength: state.networkProfile.reelsBackBufferLength,
						startLevel: isSlowNetworkProfile(state.networkProfile) ? 0 : -1,
						maxBufferLength: state.networkProfile.reelsMaxBufferLength,
						maxMaxBufferLength: state.networkProfile.reelsMaxMaxBufferLength
					});

					hlsInstance.on(Hls.Events.ERROR, handleHlsError);
					hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
						handleLoadedMetadata();
					});
					hlsInstance.on(Hls.Events.FRAG_BUFFERED, syncBuffered);
					hlsInstance.on(Hls.Events.LEVEL_SWITCHED, (event, data) => {
						syncBuffered();
						const height = hlsInstance?.levels?.[data?.level]?.height || 0;
						if(height > 0) {
							recordPlaybackMetric('media_rendition', height, {
								rendition: `${height}p`
							});
						}
					});
					hlsInstance.attachMedia(videoElement);
					hlsInstance.on(Hls.Events.MEDIA_ATTACHED, () => {
						hlsInstance.loadSource(source.url);

						if(shouldWarmPlayback.value) {
							hlsInstance.startLoad(hlsStartPosition());
						}
						else {
							hlsInstance.stopLoad();
						}
					});

					return;
				}

				state.nativeVideoUrl = source.url;

				nextTick(() => {
					const nextVideoElement = videoPlayerRef.value;

					if(! nextVideoElement) {
						return;
					}

					nextVideoElement.load();

						if(nextVideoElement.readyState >= 1) {
							handleLoadedMetadata();
						}

						if(nextVideoElement.readyState >= 2) {
							handleLoadedData();
						}
					});
				};

			const syncPlaybackWindow = () => {
				if(hlsInstance) {
					try {
						if(shouldWarmPlayback.value) {
							hlsInstance.startLoad(hlsStartPosition());
						}
						else {
							hlsInstance.stopLoad();
						}
					}
					catch(error) {}
					}

					warmNativePlayback();
				};

				const releasePlaybackSource = () => {
					pauseVideo();
					destroyHlsInstance();
					state.nativeVideoUrl = '';
					resetPlaybackState(false);
				};

				watch(shouldAttachPlaybackSource, (shouldAttach) => {
					if(shouldAttach) {
						nextTick(() => {
							configurePlaybackSource();
							syncPlaybackWindow();

							if(props.active) {
								playVideo();
							}
						});
					}
					else {
						releasePlaybackSource();
					}
				});

				watch(() => props.active, (isActive) => {
				if(isActive) {
					state.manualPaused = false;
					state.showRetry = false;
					state.playRequested = true;
					syncPlaybackWindow();

					nextTick(() => {
						playVideo();
					});
				}
				else {
					pauseVideo({
						emitInteraction: true
					});
					syncPlaybackWindow();
				}
			});

			watch(() => props.distanceFromActive, () => {
				syncPlaybackWindow();
			});

			watch(() => props.blocked, (isBlocked) => {
				if(isBlocked) {
					pauseVideo({
						emitInteraction: props.active
					});
				}
				else if(props.active) {
					playVideo();
				}
			});

			watch(() => props.mediaItem?.id, () => {
				pauseVideo({
					emitInteraction: props.active
				});
					state.forceMp4Fallback = false;
				state.sessionId = `reel-video-${Date.now()}-${Math.random().toString(36).slice(2)}`;
				configurePlaybackSource();
			});

			watch(() => state.networkProfile.profile, () => {
				if(shouldAttachPlaybackSource.value) {
					configurePlaybackSource();
					syncPlaybackWindow();
				}
				else {
					releasePlaybackSource();
				}
			});

			onMounted(() => {
				setMuted();
				unsubscribeNetworkProfile = subscribeNetworkProfile((networkProfile) => {
					state.networkProfile = networkProfile;
				});

				if(shouldAttachPlaybackSource.value) {
					configurePlaybackSource();
					syncPlaybackWindow();
				}
			});

			onUnmounted(() => {
				playbackGestureController?.reset();

				unsubscribeNetworkProfile?.();
				pauseVideo({
					emitInteraction: props.active
				});
				destroyHlsInstance();
				clearBufferRecoveryTimer();
				clearBufferingGraceTimer();
			});

			return {
				videoPlayerRef: videoPlayerRef,
				state: state,
				sourceUrl: sourceUrl,
					nativeVideoUrl: nativeVideoUrl,
					videoPreload: videoPreload,
					showBufferIndicator: showBufferIndicator,
					showPosterCover: showPosterCover,
					canRenderVideo: canRenderVideo,
					displayProgress: displayProgress,
					handleSurfaceTap: handleSurfaceTap,
					retryPlayback: retryPlayback,
					handleLoadedMetadata: handleLoadedMetadata,
					handleLoadedData: handleLoadedData,
					handleCanPlay: handleCanPlay,
				handlePlaying: handlePlaying,
				handleWaiting: handleWaiting,
				handleNativeError: handleNativeError,
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
