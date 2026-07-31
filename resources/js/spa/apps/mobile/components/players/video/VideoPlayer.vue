<template>
	<div
		v-bind:style="playerFrameStyle"
	class="relative flex w-full justify-center cursor-pointer bg-black overflow-hidden">
		<video
			v-on:click="togglePlay"
			class="size-full object-cover"
			ref="videoPlayerRef"
			webkit-playsinline
			playsinline
			preload="metadata"
			muted
			v-bind:poster="thumbnailUrl"
			loop="loop"
		>
			<source v-bind:src="videoUrl" type="video/mp4">
		</video>

		<div class="from-black/60 to-transparent bg-linear-to-t absolute bottom-0 left-0 right-0 px-2 pb-2 pt-6">
            <div v-on:click="seekVideo" class="h-4 cursor-pointer flex-1 mx-2 flex items-center">
                <div class="h-[2px] bg-white/50 rounded-full w-full overflow-hidden">
                    <span class="h-full block max-w-full bg-white transition-width ease-in-out" v-bind:style="{width: `${state.progressBar}%`}"></span>
                </div>
            </div>
			<div class="flex items-center gap-2">
				<div class="ml-2 inline-flex items-center gap-2 flex-1">
					<VideoDurationTime v-if="state.isPlaying" v-bind:videoDuration="$filters.secondsToDuration(state.playbackTime)"></VideoDurationTime>
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
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { buildVideoPresentationMetadata, videoFrameAspectStyle } from '@/kernel/services/media/video-metadata.js';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';

	export default defineComponent({
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
			}
		},
		setup: function(props, context) {
			const { isIntersecting, videoPlayerRef } = useIntersectionObserver({
				threshold: 0.5
			});

			const state = reactive({
				isMuted: true,
				isLoaded: false,
				isPlaying: false,
				progressBar: 0,
				playbackTime: 0,
				watchStartedAt: null,
				watchMsSinceFlush: 0,
				totalWatchMs: 0,
				lastPlaybackTime: 0,
				presentationMetadata: {},
				loopCount: 0,
				sessionId: `video-${Date.now()}-${Math.random().toString(36).slice(2)}`,
				telemetryTimer: null
			});
			const playerFrameStyle = computed(() => {
				return videoFrameAspectStyle({
					...(props.metadata || {}),
					...(state.presentationMetadata || {}),
					aspect_ratio: state.presentationMetadata?.aspect_ratio || props.aspectRatio || props.metadata?.aspect_ratio
				}, props.isPortrait);
			});

			function startProgressUpdater() {
                function updateProgress() {
					if(! videoPlayerRef.value) {
						return false;
					}

                    const currentTime = videoPlayerRef.value.currentTime;
                    const duration = videoPlayerRef.value.duration;

                    state.progressBar = duration ? Math.round((currentTime / duration) * 100) : 0;
                    state.playbackTime = Math.round(currentTime);

					if(duration && state.lastPlaybackTime > 1 && currentTime < (state.lastPlaybackTime - 0.75)) {
						state.loopCount += 1;
						flushVideoTelemetry('video_loop');
					}

					state.lastPlaybackTime = currentTime;

                    if (state.isPlaying) {
                        window.colibriVideoTimer = requestAnimationFrame(updateProgress);
                    }
                }

                window.colibriVideoTimer = requestAnimationFrame(updateProgress);
            }

            function stopProgressUpdater() {
                cancelAnimationFrame(window.colibriVideoTimer);
            }

			watch(isIntersecting, (newVal) => {
				if(newVal && state.isLoaded) {
					playVideo();
				}
				else {
					pauseVideo();
				}
			});

			const handleVideoReady = () => {
				state.isLoaded = true;
				updatePresentationMetadata();

				if(isIntersecting.value) {
					playVideo();
				}
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
				}
			});

			onUnmounted(() => {
				if(videoPlayerRef.value) {
					videoPlayerRef.value.removeEventListener('loadedmetadata', handleVideoReady);
					videoPlayerRef.value.removeEventListener('canplay', handleVideoReady);
					flushVideoTelemetry('video_watch');
					pauseVideo();
				}
			});

			const setMuted = () => {
				state.isMuted = localStorage.getItem('videoPlayerMuted') !== '0';

				if(videoPlayerRef.value) {
					videoPlayerRef.value.muted = state.isMuted;
				}
			}

			const playVideo = () => {
				if(state.isLoaded && videoPlayerRef.value) {
					videoPlayerRef.value.play().then(() => {
						setMuted();
						state.isPlaying = true;
						startWatchSession();
						startProgressUpdater();
						startTelemetryTimer();
					}).catch((error) => {
						if(error.name === 'NotAllowedError') {
							console.info('Cannot play video because user has not interacted with the page yet');
						}
					});
				}
			};

			const pauseVideo = () => {
				if(videoPlayerRef.value) {
					videoPlayerRef.value.pause();
				}

				state.isPlaying = false;
				flushVideoTelemetry('video_watch');
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
				return Number(props.duration?.seconds || videoPlayerRef.value?.duration || 0);
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

	            const seekVideo = (event) => {
	                const progressBar = event.currentTarget;
	                const rect = progressBar.getBoundingClientRect();
	                const clickPosition = (event.clientX - rect.left);
	                const percentage = (clickPosition / rect.width);
	                const newTime = (videoDurationSeconds() * percentage);

				if(! videoPlayerRef.value) {
					return false;
				}

				videoPlayerRef.value.currentTime = newTime;

                if(! state.isPlaying) {
                    playVideo();
                }
            };

				return {
					videoPlayerRef: videoPlayerRef,
					state: state,
					playerFrameStyle: playerFrameStyle,
					toggleMute: toggleMute,
				toggleFullscreen: toggleFullscreen,
                seekVideo: seekVideo,
				togglePlay: () => {
					if(state.isLoaded) {
						if(state.isPlaying) {
							pauseVideo();
						}
						else {
							playVideo();
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
