<template>
	<div>
        <div class="relative flex w-full max-w-[72vw] justify-center overflow-hidden rounded-2xl bg-black cursor-pointer sm:max-w-80">
            <video
                v-on:click="togglePlay"
                class="block w-full h-auto"
                v-bind:poster="thumbnailUrl"
                preload="metadata"
                playsinline
                webkit-playsinline
                muted
                ref="videoPlayerRef"
            >
                <source v-bind:src="videoUrl" type="video/mp4">
            </video>
            <div v-if="! state.isLoaded" class="absolute backdrop-blur-xs inset-0 flex-center w-full h-full bg-black/50">
                <div class="size-12">
                    <SpinnerIcon strokeColor="stroke-white" strokeWidth="1"></SpinnerIcon>
                </div>
            </div>

            <div class="from-black/20 to-transparent flex justify-center bg-gradient-to-b absolute top-0 left-0 right-0 py-3">
                <div class="translate-x-1">
                    <PrimaryIconButton
                        v-on:click="toggleMute"
                        v-bind:iconName="state.isMuted ? 'volume-x' : 'volume-max'"
                        buttonColor="text-white"
                        iconSize="icon-small"
                    hoverText="hover:text-white/80"></PrimaryIconButton>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-center mt-2">
            <VideoDurationTime v-bind:videoDuration="$filters.secondsToDuration(state.playbackTime)" textColor="text-lab-sc"></VideoDurationTime>
        </div>
    </div>
</template>

<script>
	import { defineComponent, watch, reactive, onMounted, onUnmounted } from 'vue';
	import { useIntersectionObserver } from '@/kernel/vue/composables/inter-obs/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';
	import SpinnerIcon from '@/kernel/vue/components/icons/SpinnerIcon.vue';

	export default defineComponent({
		props: {
			videoUrl: {
				type: String,
				required: true
			},
            thumbnailUrl: {
				type: String,
				required: true
			},
            duration: {
				type: Object,
				required: true
			},
		},
		setup: function(props, context) {
			const { isIntersecting, videoPlayerRef } = useIntersectionObserver({
				threshold: 0.5
			});

			const state = reactive({
				isMuted: false,
				isLoaded: false,
				isPlaying: false,
				progressBar: 0,
				playbackTime: 0
			});

            function startProgressUpdater() {
                function updateProgress() {
                    if(! videoPlayerRef.value) {
                        return false;
                    }

                    const currentTime = videoPlayerRef.value.currentTime;
                    const duration = props.duration.seconds;

                    if (! isFinite(duration) || duration === 0) {
                        return false;
                    };

                    state.progressBar = currentTime / duration;
                    state.playbackTime = Math.round(currentTime);

                    if (state.isPlaying) {
                        window.colibriVideoTimer = requestAnimationFrame(updateProgress);
                    }
                }

                window.colibriVideoTimer = requestAnimationFrame(updateProgress);
            }

			function stopProgressUpdater() {
                cancelAnimationFrame(window.colibriVideoTimer);
            }

            const handleVideoReady = () => {
                state.isLoaded = true;

                if (isIntersecting.value) {
                    playVideo();
                }
            };

			watch(isIntersecting, (newVal) => {
				if(newVal && state.isLoaded) {
					playVideo();
				}
				else {
					pauseVideo();
				}
			});

			onMounted(() => {
				if(videoPlayerRef.value.readyState >= 1) {
					handleVideoReady();
				}

				videoPlayerRef.value.addEventListener('loadedmetadata', handleVideoReady);
				videoPlayerRef.value.addEventListener('canplay', handleVideoReady);

                colibriEventBus.on('media:pause-all', pauseVideo);
			});

			onUnmounted(() => {
				if(videoPlayerRef.value) {
					videoPlayerRef.value.removeEventListener('loadedmetadata', handleVideoReady);
					videoPlayerRef.value.removeEventListener('canplay', handleVideoReady);
					pauseVideo();
				}

                colibriEventBus.off('media:pause-all', pauseVideo);
			});

			const setMuted = () => {
				if(localStorage.getItem('videoPlayerMuted')) {
					state.isMuted = true;
					videoPlayerRef.value.muted = true;
				}
				else {
					videoPlayerRef.value.muted = false;
					state.isMuted = false;
				}
			}

			const playVideo = () => {
				if(state.isLoaded) {
					videoPlayerRef.value.play().then(() => {
						setMuted();
						state.isPlaying = true;
                        state.playbackTime = 0;
                        startProgressUpdater();
					}).catch((error) => {
						if(error.name === 'NotAllowedError') {
							console.info('Cannot play video because user has not interacted with the page yet');
						}
					});
				}
			};

			const pauseVideo = () => {
				videoPlayerRef.value.pause();
				state.isPlaying = false;
				stopProgressUpdater();
			};

			const toggleMute = () => {
				state.isMuted = !state.isMuted;
				videoPlayerRef.value.muted = state.isMuted;

				if(state.isMuted) {
					localStorage.setItem('videoPlayerMuted', 1);
				}
				else {
					localStorage.removeItem('videoPlayerMuted');
				}
			};

			return {
				videoPlayerRef: videoPlayerRef,
				state: state,
				toggleMute: toggleMute,
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
			SpinnerIcon: SpinnerIcon,
			PrimaryIconButton: PrimaryIconButton,
			VideoDurationTime: VideoDurationTime
		}
	});
</script>
