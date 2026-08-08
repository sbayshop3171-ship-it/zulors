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

		<div class="absolute left-0 right-0 bottom-0 z-30 h-0.5 bg-white/20">
			<span class="block h-full bg-white" v-bind:style="{ width: `${displayProgress}%` }"></span>
		</div>

		<div class="absolute top-24 right-3 z-30">
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
	import { getNetworkProfileSnapshot, isSlowNetworkProfile, subscribeNetworkProfile } from '@/kernel/services/network/index.js';

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
			distanceFromActive: {
				type: Number,
				default: 0
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
					isReadyForPlayback: false,
					isPlaying: false,
					isBuffering: false,
					hasVisualFrame: false,
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
				telemetryTimer: null,
				bufferRecoveryTimer: null,
				networkProfile: getNetworkProfileSnapshot(),
				nativeVideoUrl: '',
				stallCount: 0
			});

			let tapTimer = null;
			let hlsInstance = null;
			let unsubscribeNetworkProfile = null;

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
				if(props.active || props.blocked) {
					return props.active && ! props.blocked;
				}

				return canRenderVideo.value && Number(props.distanceFromActive || 0) <= Number(state.networkProfile.reelsWarmRadius || 0);
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

					return shouldWarmPlayback.value ? (state.networkProfile.reelsAdjacentVideoPreload || 'metadata') : 'none';
				});

				const showBufferIndicator = computed(() => {
					return props.active && state.isBuffering && ! props.blocked && ! state.manualPaused;
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

				if(state.durationSeconds && state.lastPlaybackTime > 1 && currentTime < (state.lastPlaybackTime - 0.75)) {
					state.loopCount += 1;
					flushVideoTelemetry('video_loop');
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
					syncProgress();
					updatePlaybackReadiness(false);
					markVisualFrameReady();
				};

				const handleLoadedData = () => {
					handleLoadedMetadata();
					state.hasVisualFrame = true;
					updatePlaybackReadiness(true);

					if(props.active && ! props.blocked && ! state.manualPaused && ! state.isPlaying) {
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

			const destroyHlsInstance = () => {
				if(hlsInstance) {
					hlsInstance.destroy();
					hlsInstance = null;
				}
			};

			const resetPlaybackState = (resetSession = false) => {
				clearBufferRecoveryTimer();
				state.isLoaded = false;
					state.isReadyForPlayback = false;
					state.isPlaying = false;
					state.isBuffering = false;
					state.hasVisualFrame = false;
					state.playbackTime = 0;
				state.durationSeconds = 0;
				state.totalWatchMs = 0;
				state.watchMsSinceFlush = 0;
				state.loopCount = 0;
				state.lastPlaybackTime = 0;
				state.bufferedBar = 0;
				state.stallCount = 0;

				if(resetSession) {
					state.sessionId = `reel-video-${Date.now()}-${Math.random().toString(36).slice(2)}`;
				}
			};

			const applyFallbackSource = () => {
				const fallbackSource = getDirectPlaybackFallback(props.mediaItem);

				if(! fallbackSource?.url || fallbackSource.url === state.nativeVideoUrl) {
					return false;
				}

				destroyHlsInstance();
				state.nativeVideoUrl = fallbackSource.url;
				resetPlaybackState(false);

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
					clearBufferRecoveryTimer();

				if(! props.active || props.blocked || state.manualPaused) {
					return;
				}

				state.bufferRecoveryTimer = setTimeout(() => {
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
							applyFallbackSource();
						}
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
					if(! state.isLoaded || ! state.isReadyForPlayback || ! videoPlayerRef.value || props.blocked || ! props.active) {
						if(videoPlayerRef.value && props.active && ! props.blocked && ! state.manualPaused) {
							state.isBuffering = true;
							syncPlaybackWindow();
						}

						return false;
					}

					setMuted();

					videoPlayerRef.value.play().then(() => {
						if(! state.isPlaying) {
							state.isPlaying = true;
							startWatchSession();
							startTelemetryTimer();
						}

						state.isBuffering = false;
					}).catch((error) => {
						state.isPlaying = false;
						state.isBuffering = false;
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
				state.isBuffering = false;
				flushVideoTelemetry('video_watch');
				stopTelemetryTimer();
				clearBufferRecoveryTimer();
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

				const handlePlaying = () => {
					state.isPlaying = true;
					state.isBuffering = false;
					state.hasVisualFrame = true;
					updatePlaybackReadiness(true);
				startWatchSession();
				startTelemetryTimer();
			};

			const handleWaiting = () => {
				if(! props.active || props.blocked || state.manualPaused) {
					return;
				}

				state.isBuffering = true;
				state.stallCount += 1;
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
							catch(error) {}
						}
						break;
					case Hls.ErrorTypes.MEDIA_ERROR:
						try {
							hlsInstance?.recoverMediaError();
						}
						catch(error) {
							applyFallbackSource();
						}
						break;
					default:
						applyFallbackSource();
						break;
				}
			};

				const configurePlaybackSource = () => {
				const videoElement = videoPlayerRef.value;
				const source = playbackSource.value;

				destroyHlsInstance();
				state.nativeVideoUrl = '';
				resetPlaybackState(false);

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
					hlsInstance.on(Hls.Events.LEVEL_SWITCHED, syncBuffered);
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
					syncPlaybackWindow();

					nextTick(() => {
						playVideo();
					});
				}
				else {
					pauseVideo();
					syncPlaybackWindow();
				}
			});

			watch(() => props.distanceFromActive, () => {
				syncPlaybackWindow();
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
				if(tapTimer) {
					clearTimeout(tapTimer);
				}

				unsubscribeNetworkProfile?.();
				pauseVideo();
				destroyHlsInstance();
				clearBufferRecoveryTimer();
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
