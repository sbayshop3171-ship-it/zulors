<template>
	<div
		v-bind:style="playerFrameStyle"
	class="relative flex w-full justify-center cursor-pointer group bg-black overflow-hidden">
		<video
			v-on:click="handleVideoSurfaceClick"
			class="size-full object-cover"
			ref="videoPlayerRef"
			v-bind:poster="thumbnailUrl"
			preload="metadata"
			playsinline
			muted
			loop="loop"
		>
			<source v-bind:src="videoUrl" type="video/mp4">
		</video>
		<div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 from-black/60 to-transparent bg-linear-to-t absolute bottom-0 left-0 right-0 px-2 pb-2 pt-6">
            <div
				role="slider"
				tabindex="0"
				aria-label="Video progress"
				aria-valuemin="0"
				v-bind:aria-valuemax="Math.round(state.durationSeconds)"
				v-bind:aria-valuenow="Math.round(scrubberDisplayTime)"
				v-bind:aria-valuetext="scrubberAriaValue"
				v-on:pointerdown.stop.prevent="startScrubbing"
				v-on:pointermove="previewScrubbing"
				v-on:pointerleave="clearScrubPreview"
				v-on:pointerup.stop.prevent="endScrubbing"
				v-on:pointercancel.stop.prevent="endScrubbing"
				v-on:lostpointercapture="endScrubbing"
				v-on:keydown="handleScrubberKeydown"
				v-on:click.stop.prevent="noop"
			class="group/scrubber relative h-7 cursor-pointer flex-1 mx-2 flex items-center touch-none select-none">
				<span
					v-if="state.showScrubPreview"
					class="pointer-events-none absolute bottom-6 -translate-x-1/2 rounded bg-black/85 px-1.5 py-1 text-[11px] font-medium leading-none text-white shadow"
				v-bind:style="{ left: `${state.previewPosition}%` }">{{ scrubberPreviewLabel }}</span>
                <div
					class="relative h-0.5 bg-white/45 rounded-full w-full overflow-hidden transition-all duration-150 group-hover/scrubber:h-1"
				v-bind:class="{ 'h-1': state.isScrubbing }">
					<span class="absolute inset-y-0 left-0 block max-w-full bg-white/25" v-bind:style="{width: `${state.bufferedBar}%`}"></span>
                    <span
						class="absolute inset-y-0 left-0 block max-w-full bg-white"
						v-bind:class="state.isScrubbing ? '' : 'transition-width ease-in-out'"
					v-bind:style="{width: `${displayProgress}%`}"></span>
                </div>
				<span
					aria-hidden="true"
					class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white shadow transition-all duration-150"
					v-bind:class="state.isScrubbing ? 'size-3 opacity-100' : 'size-2 opacity-0 group-hover/scrubber:size-3 group-hover/scrubber:opacity-100'"
				v-bind:style="{ left: `${displayProgress}%` }"></span>
            </div>
			<div class="flex items-center">
				<div class="ml-2 inline-flex items-center gap-2 flex-1">
					<VideoDurationTime v-if="state.isPlaying || state.isScrubbing" v-bind:videoDuration="$filters.secondsToDuration(state.playbackTime)"></VideoDurationTime>
					<VideoDurationTime v-else v-bind:videoDuration="duration"></VideoDurationTime>
				</div>

				<PrimaryIconButton
					v-on:click="togglePlay"
					v-bind:iconName="state.isPlaying ? 'pause' : 'play'"
                    iconType="solid"
					buttonColor="text-white"
					iconSize="icon-small"
				hoverText="text-white/70 hover:text-white"></PrimaryIconButton>
				<PrimaryIconButton
					v-on:click="toggleFullscreen"
					iconName="maximize-02"
                    iconType="line"
					buttonColor="text-white"
					iconSize="icon-small"
				hoverText="text-white/70 hover:text-white"></PrimaryIconButton>
				<PrimaryIconButton
					v-on:click="togglePIP"
					iconName="picture-in-picture"
                    iconType="line"
					buttonColor="text-white"
					iconSize="icon-small"
				hoverText="text-white/70 hover:text-white"></PrimaryIconButton>
				<PrimaryIconButton
					v-on:click="toggleMute"
					v-bind:iconName="state.isMuted ? 'volume-x' : 'volume-max'"
					buttonColor="text-white"
					iconSize="icon-small"
				hoverText="text-white/70 hover:text-white"></PrimaryIconButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, watch, reactive, onMounted, onUnmounted } from 'vue';
	import { useIntersectionObserver } from '@/kernel/vue/composables/inter-obs/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { buildVideoPresentationMetadata, videoFrameAspectStyle } from '@/kernel/services/media/video-metadata.js';
	import {
		registerVideoPlaybackCandidate,
		requestVideoPlayback,
		setVideoPlaybackManualPause,
		updateVideoPlaybackCandidate
	} from '@/kernel/services/media/video-playback-arbiter/index.js';

	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';

	export default defineComponent({
		emits: ['surface-click'],
		props: {
			videoUrl: {
				type: String,
				required: true
			},
			duration: {
				type: Object,
				default: () => ({})
			},
			thumbnailUrl: {
				type: String,
				required: true
			},
			isPortrait: {
				type: Boolean,
				default: false
			},
			aspectRatio: {
				type: [Number, String],
				default: null
			},
			metadata: {
				type: Object,
				default: () => ({})
			},
			postId: {
				type: [Number, String],
				default: null
			},
			mediaId: {
				type: [Number, String],
				default: null
			},
			surfaceClickAction: {
				type: String,
				default: 'toggle'
			}
		},
		setup: function(props, context) {
			const { isIntersecting, intersectionRatio, observerEntry, videoPlayerRef } = useIntersectionObserver({
				threshold: 0.5
			});
			const playbackCandidateId = `desktop-feed-video-${props.postId || 'post'}-${props.mediaId || 'media'}-${Math.random().toString(36).slice(2)}`;
			const unregisterPlaybackCandidate = registerVideoPlaybackCandidate({
				id: playbackCandidateId,
				activate: () => {
					playVideo();
				},
				deactivate: () => {
					pauseVideo();
				}
			});

			const state = reactive({
				isMuted: true,
				isLoaded: false,
				isPlaying: false,
				progressBar: 0,
				bufferedBar: 0,
				playbackTime: 0,
				durationSeconds: 0,
				isScrubbing: false,
				scrubTime: 0,
				previewTime: 0,
				previewPosition: 0,
				showScrubPreview: false,
				wasPlayingBeforeScrub: false,
				watchStartedAt: null,
				watchMsSinceFlush: 0,
				totalWatchMs: 0,
				lastPlaybackTime: 0,
				presentationMetadata: {},
				loopCount: 0,
				sessionId: `video-${Date.now()}-${Math.random().toString(36).slice(2)}`,
				telemetryTimer: null
			});

			let progressTimer = null;
			let activeScrubPointerId = null;
			let lastScrubSeekedAt = 0;
			const scrubSeekIntervalMs = 90;

			const playerFrameStyle = computed(() => {
				return videoFrameAspectStyle({
					...(props.metadata || {}),
					...(state.presentationMetadata || {}),
					aspect_ratio: state.presentationMetadata?.aspect_ratio || props.aspectRatio || props.metadata?.aspect_ratio
				}, props.isPortrait);
			});

			const displayProgress = computed(() => {
				const durationSeconds = videoDurationSeconds();
				const displayTime = state.isScrubbing ? state.scrubTime : state.playbackTime;

				return durationSeconds ? clamp((displayTime / durationSeconds) * 100, 0, 100) : 0;
			});

			const scrubberDisplayTime = computed(() => {
				return state.isScrubbing ? state.scrubTime : state.playbackTime;
			});

			const scrubberPreviewLabel = computed(() => {
				return formatDurationText(state.previewTime);
			});

			const scrubberAriaValue = computed(() => {
				return formatDurationText(scrubberDisplayTime.value);
			});

			function startProgressUpdater() {
				stopProgressUpdater();

                function updateProgress() {
					if(! videoPlayerRef.value || state.isScrubbing) {
						return false;
					}

					syncProgressFromVideo();

                    if(state.isPlaying) {
                        progressTimer = requestAnimationFrame(updateProgress);
                    }
                }

                progressTimer = requestAnimationFrame(updateProgress);
            }

            function stopProgressUpdater() {
				if(progressTimer) {
					cancelAnimationFrame(progressTimer);
					progressTimer = null;
				}
            }

			function clamp(value, min, max) {
				return Math.min(Math.max(value, min), max);
			}

			function formatDurationText(seconds) {
				const safeSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
				const hours = Math.floor(safeSeconds / 3600);
				const minutes = Math.floor((safeSeconds % 3600) / 60);
				const remainingSeconds = safeSeconds % 60;
				const minuteLabel = minutes.toString().padStart(2, '0');
				const secondLabel = remainingSeconds.toString().padStart(2, '0');

				if(hours) {
					return `${hours.toString().padStart(2, '0')}:${minuteLabel}:${secondLabel}`;
				}

				return `${minuteLabel}:${secondLabel}`;
			}

			function syncBufferedProgress() {
				const videoElement = videoPlayerRef.value;
				const durationSeconds = videoDurationSeconds();

				if(! videoElement || ! durationSeconds || ! videoElement.buffered?.length) {
					state.bufferedBar = 0;
					return false;
				}

				const bufferedEnd = videoElement.buffered.end(videoElement.buffered.length - 1);

				state.bufferedBar = clamp((bufferedEnd / durationSeconds) * 100, 0, 100);
			}

			function syncProgressFromVideo() {
				const videoElement = videoPlayerRef.value;

				if(! videoElement) {
					return false;
				}

				const currentTime = videoElement.currentTime || 0;
				const durationSeconds = videoDurationSeconds();

				state.durationSeconds = durationSeconds;
				state.progressBar = durationSeconds ? clamp((currentTime / durationSeconds) * 100, 0, 100) : 0;
				state.playbackTime = currentTime;
				syncBufferedProgress();

				if(durationSeconds && state.lastPlaybackTime > 1 && currentTime < (state.lastPlaybackTime - 0.75)) {
					state.loopCount += 1;
					flushVideoTelemetry('video_loop');
				}

				state.lastPlaybackTime = currentTime;
			}

			const syncPlaybackCandidate = () => {
				updateVideoPlaybackCandidate(playbackCandidateId, {
					isReady: state.isLoaded && ! state.isScrubbing,
					isVisible: Boolean(isIntersecting.value),
					ratio: intersectionRatio.value,
					rect: observerEntry.value?.boundingClientRect || null
				});
			};

			watch([isIntersecting, intersectionRatio], () => {
				syncPlaybackCandidate();
			});

			watch(() => state.isLoaded, () => {
				syncPlaybackCandidate();
			});

			watch(() => state.isScrubbing, () => {
				syncPlaybackCandidate();
			});

			const handleVideoReady = () => {
				state.isLoaded = true;
				updatePresentationMetadata();
				syncProgressFromVideo();
				syncPlaybackCandidate();
			};

			const updatePresentationMetadata = () => {
				const videoElement = videoPlayerRef.value;

				if(! videoElement?.videoWidth || ! videoElement?.videoHeight) {
					return;
				}

				state.presentationMetadata = buildVideoPresentationMetadata(
					videoElement.videoWidth,
					videoElement.videoHeight,
					videoElement.duration
				);
			};

			onMounted(() => {
				setMuted();

				if(videoPlayerRef.value && videoPlayerRef.value.readyState >= 1) {
					handleVideoReady();
				}

				if(videoPlayerRef.value) {
					videoPlayerRef.value.addEventListener('loadedmetadata', handleVideoReady);
					videoPlayerRef.value.addEventListener('canplay', handleVideoReady);
					videoPlayerRef.value.addEventListener('durationchange', handleVideoReady);
					videoPlayerRef.value.addEventListener('progress', syncBufferedProgress);
				}

				syncPlaybackCandidate();
			});

			onUnmounted(() => {
				if(videoPlayerRef.value) {
					videoPlayerRef.value.removeEventListener('loadedmetadata', handleVideoReady);
					videoPlayerRef.value.removeEventListener('canplay', handleVideoReady);
					videoPlayerRef.value.removeEventListener('durationchange', handleVideoReady);
					videoPlayerRef.value.removeEventListener('progress', syncBufferedProgress);
					flushVideoTelemetry('video_watch');
					pauseVideo();
				}

				stopProgressUpdater();
				unregisterPlaybackCandidate();
			});

			const setMuted = () => {
				state.isMuted = localStorage.getItem('videoPlayerMuted') !== '0';

				if(videoPlayerRef.value) {
					videoPlayerRef.value.muted = state.isMuted;
				}
			}

			const playVideo = () => {
				if(state.isLoaded && videoPlayerRef.value && ! state.isScrubbing) {
					if(state.isPlaying && ! videoPlayerRef.value.paused) {
						return;
					}

					colibriEventBus.emit('media:pause-all');
					videoPlayerRef.value.play().then(() => {
						setMuted();
						state.isPlaying = true;
						startWatchSession();
						startProgressUpdater();
						startTelemetryTimer();
					}).catch((error) => {
						state.isPlaying = false;
						stopProgressUpdater();
						stopTelemetryTimer();

						if(error.name === 'NotAllowedError') {
							console.info('Cannot play video because user has not interacted with the page yet');
						}
					});
				}
			};

			const pauseVideo = () => {
				if(videoPlayerRef.value) {
					if(state.isPlaying || ! videoPlayerRef.value.paused) {
						flushVideoTelemetry('video_watch');
					}

					videoPlayerRef.value.pause();
				}

				state.isPlaying = false;
				stopProgressUpdater();
				stopTelemetryTimer();
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

			const videoDurationSeconds = () => {
				const videoDuration = Number(videoPlayerRef.value?.duration || 0);

				if(Number.isFinite(videoDuration) && videoDuration > 0) {
					return videoDuration;
				}

				const propDuration = Number(props.duration?.seconds || 0);

				return Number.isFinite(propDuration) && propDuration > 0 ? propDuration : 0;
			};

			const flushVideoTelemetry = (eventType = 'video_watch') => {
				collectWatchTime();

				if(! props.postId || ! props.mediaId || ! videoPlayerRef.value) {
					return false;
				}

				const watchSeconds = Math.round(state.watchMsSinceFlush / 100) / 10;
				const durationSeconds = videoDurationSeconds();
				const completionRate = durationSeconds ? Math.round((state.totalWatchMs / 1000 / durationSeconds) * 10000) / 10000 : 0;

				if(watchSeconds < 1 && eventType !== 'video_loop') {
					if(state.isPlaying) {
						startWatchSession();
					}

					return false;
				}

				const eventName = (eventType === 'video_watch' && completionRate < 0.35) ? 'video_skip' : eventType;

				colibriAPI().userTimeline().with({
					events: [{
						event_type: eventName,
						post_id: props.postId,
						media_id: props.mediaId,
						watch_time_seconds: watchSeconds,
						duration_seconds: durationSeconds,
						current_time_seconds: Math.round(videoPlayerRef.value.currentTime * 10) / 10,
						completion_rate: completionRate,
						loop_count: state.loopCount,
						session_id: state.sessionId
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

			const toggleMute = () => {
				state.isMuted = !state.isMuted;

				if(videoPlayerRef.value) {
					videoPlayerRef.value.muted = state.isMuted;
				}

				if(state.isMuted) {
					localStorage.setItem('videoPlayerMuted', '1');
				}
				else {
					localStorage.setItem('videoPlayerMuted', '0');
				}
			};

            const toggleFullscreen = () => {
                if(document.fullscreenElement) {
                    document.exitFullscreen();
                }
                else if(videoPlayerRef.value) {
                    videoPlayerRef.value.requestFullscreen();
                }
            };

            const togglePIP = () => {
                if(document.pictureInPictureElement) {
                    document.exitPictureInPicture();
                }
                else if(videoPlayerRef.value) {
                    videoPlayerRef.value.requestPictureInPicture();
                }
            };

			const scrubPositionFromPointer = (event) => {
				const scrubber = event.currentTarget;
				const durationSeconds = videoDurationSeconds();

				if(! scrubber || ! durationSeconds) {
					return null;
				}

				const rect = scrubber.getBoundingClientRect();

				if(! rect.width) {
					return null;
				}

				const percentage = clamp((event.clientX - rect.left) / rect.width, 0, 1);

				return {
					percentage: percentage,
					time: durationSeconds * percentage,
					position: clamp(percentage * 100, 4, 96)
				};
			};

			const updateScrubState = (scrubPosition) => {
				const durationSeconds = videoDurationSeconds();

				if(! scrubPosition || ! durationSeconds) {
					return false;
				}

				state.durationSeconds = durationSeconds;
				state.scrubTime = scrubPosition.time;
				state.previewTime = scrubPosition.time;
				state.previewPosition = scrubPosition.position;
				state.showScrubPreview = true;
				state.progressBar = clamp((scrubPosition.time / durationSeconds) * 100, 0, 100);
				state.playbackTime = scrubPosition.time;
				state.lastPlaybackTime = scrubPosition.time;
			};

			const syncScrubFrame = (time, force = false) => {
				if(! videoPlayerRef.value) {
					return false;
				}

				const now = window.performance?.now ? window.performance.now() : Date.now();

				if(force || (now - lastScrubSeekedAt) >= scrubSeekIntervalMs) {
					videoPlayerRef.value.currentTime = time;
					lastScrubSeekedAt = now;
				}
			};

			const startScrubbing = (event) => {
				if(event.button !== undefined && event.button !== 0) {
					return false;
				}

				const scrubPosition = scrubPositionFromPointer(event);

				if(! scrubPosition || ! videoPlayerRef.value) {
					return false;
				}

				activeScrubPointerId = event.pointerId;
				state.wasPlayingBeforeScrub = ! videoPlayerRef.value.paused;
				state.isScrubbing = true;

				if(state.wasPlayingBeforeScrub) {
					collectWatchTime();
					videoPlayerRef.value.pause();
					stopProgressUpdater();
					stopTelemetryTimer();
				}

				try {
					event.currentTarget.setPointerCapture(event.pointerId);
				}
				catch (error) {}

				updateScrubState(scrubPosition);
				syncScrubFrame(scrubPosition.time, true);
			};

			const previewScrubbing = (event) => {
				if(state.isScrubbing && event.pointerId === activeScrubPointerId) {
					const scrubPosition = scrubPositionFromPointer(event);

					if(scrubPosition) {
						updateScrubState(scrubPosition);
						syncScrubFrame(scrubPosition.time);
					}
				}
				else if(event.pointerType === 'mouse') {
					const scrubPosition = scrubPositionFromPointer(event);

					if(scrubPosition) {
						state.previewTime = scrubPosition.time;
						state.previewPosition = scrubPosition.position;
						state.showScrubPreview = true;
					}
				}
			};

			const endScrubbing = (event) => {
				if(! state.isScrubbing || event.pointerId !== activeScrubPointerId) {
					return false;
				}

				const scrubPosition = scrubPositionFromPointer(event);

				if(scrubPosition) {
					updateScrubState(scrubPosition);
					syncScrubFrame(scrubPosition.time, true);
				}

				try {
					event.currentTarget.releasePointerCapture(event.pointerId);
				}
				catch (error) {}

				const shouldResume = state.wasPlayingBeforeScrub && isIntersecting.value;

				activeScrubPointerId = null;
				state.isScrubbing = false;
				state.wasPlayingBeforeScrub = false;
				state.showScrubPreview = false;

				if(shouldResume) {
					requestVideoPlayback(playbackCandidateId);
				}
				else {
					state.isPlaying = false;
					stopProgressUpdater();
					stopTelemetryTimer();
				}
			};

			const clearScrubPreview = () => {
				if(! state.isScrubbing) {
					state.showScrubPreview = false;
				}
			};

			const seekBy = (seconds) => {
				const durationSeconds = videoDurationSeconds();

				if(! durationSeconds || ! videoPlayerRef.value) {
					return false;
				}

				const nextTime = clamp((videoPlayerRef.value.currentTime || 0) + seconds, 0, durationSeconds);
				const nextPercentage = nextTime / durationSeconds;

				updateScrubState({
					time: nextTime,
					percentage: nextPercentage,
					position: clamp(nextPercentage * 100, 4, 96)
				});

				syncScrubFrame(nextTime, true);
				state.showScrubPreview = false;
			};

			const handleScrubberKeydown = (event) => {
				const handledKeys = ['ArrowLeft', 'ArrowDown', 'ArrowRight', 'ArrowUp', 'Home', 'End'];

				if(! handledKeys.includes(event.key)) {
					return false;
				}

				event.preventDefault();
				event.stopPropagation();

				const stepSeconds = event.shiftKey ? 10 : 5;

				if(event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
					seekBy(-stepSeconds);
				}
				else if(event.key === 'ArrowRight' || event.key === 'ArrowUp') {
					seekBy(stepSeconds);
				}
				else if(event.key === 'Home') {
					seekBy(-videoDurationSeconds());
				}
				else if(event.key === 'End') {
					seekBy(videoDurationSeconds());
				}
			};

			const noop = () => {};

				return {
				videoPlayerRef: videoPlayerRef,
				state: state,
				playerFrameStyle: playerFrameStyle,
				displayProgress: displayProgress,
				scrubberDisplayTime: scrubberDisplayTime,
				scrubberPreviewLabel: scrubberPreviewLabel,
				scrubberAriaValue: scrubberAriaValue,
				toggleMute: toggleMute,
                toggleFullscreen: toggleFullscreen,
                togglePIP: togglePIP,
                startScrubbing: startScrubbing,
                previewScrubbing: previewScrubbing,
                endScrubbing: endScrubbing,
                clearScrubPreview: clearScrubPreview,
                handleScrubberKeydown: handleScrubberKeydown,
				noop: noop,
				handleVideoSurfaceClick: () => {
					if(props.surfaceClickAction === 'emit') {
						context.emit('surface-click');

						return;
					}

					if(state.isLoaded) {
						if(state.isPlaying) {
							setVideoPlaybackManualPause(playbackCandidateId, true);
							pauseVideo();
						}
						else {
							setVideoPlaybackManualPause(playbackCandidateId, false);
							requestVideoPlayback(playbackCandidateId);
						}
					}
				},
				togglePlay: () => {
					if(state.isLoaded) {
						if(state.isPlaying) {
							setVideoPlaybackManualPause(playbackCandidateId, true);
							pauseVideo();
						}
						else {
							setVideoPlaybackManualPause(playbackCandidateId, false);
							requestVideoPlayback(playbackCandidateId);
						}
					}
				}
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			VideoDurationTime: VideoDurationTime
		}
	});
</script>
