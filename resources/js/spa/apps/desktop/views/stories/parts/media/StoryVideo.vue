<template>
    <div class="story-media-stage">
        <StoryMediaLoader v-show="isLoading" class="absolute inset-0 z-20" v-bind:lqipBase64="frameData.media.lqip_base64"></StoryMediaLoader>
        <video
            v-if="showBlurBackdrop && mediaSourceUrl"
            ref="storyBackdropVideo"
            v-bind:src="mediaSourceUrl"
            class="story-media-backdrop"
            webkit-playsinline
            playsinline
            muted
            preload="metadata"
            aria-hidden="true"
            tabindex="-1"
        ></video>
        <div v-if="showBlurBackdrop" class="story-media-backdrop-shade"></div>
        <video
            v-show="! isLoading"
            ref="storyVideo"
            v-bind:src="mediaSourceUrl"
            v-bind:class="foregroundFitClass"
            v-on:loadedmetadata="handleLoadedMetadata"
            v-on:loadeddata="onLoaded"
            v-on:play="syncBackdropVideo"
            v-on:pause="syncBackdropVideo"
            v-on:timeupdate="syncBackdropVideo"
            class="story-media-foreground"
            webkit-playsinline
            playsinline
            preload="metadata"
        >
            <source v-bind:src="mediaSourceUrl" v-bind:type="frameData.media.mime || 'video/mp4'">
        </video>
    </div>
</template>
<script>
    import { computed, defineComponent, ref, inject, onMounted, watch, onBeforeUnmount } from 'vue';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { elementVideoDimensions, shouldUseStoryBlurBackdrop, storyMediaObjectFitClass } from '@/kernel/services/media/story-media-presentation.js';
    import StoryMediaLoader from '@D/views/stories/parts/media/StoryMediaLoader.vue';

    export default defineComponent({
        props: {
            frameData: {
                type: Object,
                required: true
            }
        },
        setup: function(props) {
            const storyVideo = ref(null);
            const storyBackdropVideo = ref(null);
            const isLoading = ref(true);
            const frameData = ref(props.frameData);
            const playerState = inject('playerState');
            const loadedDimensions = ref({});
            const mediaItem = computed(() => {
                return frameData.value.media || {};
            });
            const mediaSourceUrl = computed(() => {
                return mediaItem.value.source_url || mediaItem.value.preview_url || '';
            });
            
            onMounted(() => {
                if (localStorage.getItem('stories_videos_muted')) {
                    storyVideo.value.muted = true;
                }
                else{
                    storyVideo.value.muted = false;
                }

                watch(() => { return playerState.isPaused; }, () => {
                    pauseToggle();
                });

                colibriEventBus.on('story:unmute', unmuteVideo);

                colibriEventBus.on('story:mute', muteVideo);
            });

            onBeforeUnmount(() => {
                colibriEventBus.off('story:unmute', unmuteVideo);
                colibriEventBus.off('story:mute', muteVideo);
            });

            const muteVideo = () => {
                storyVideo.value.muted = true;
            }

            const unmuteVideo = () => {
                storyVideo.value.muted = false;
            }

            const pauseToggle = () => {
                if(playerState.isPaused) {
                    storyVideo.value.pause();
                }
                else{
                    playVideo(storyVideo.value);
                }

                syncBackdropVideo();
            }

            const playVideo = (videoElement) => {
                const playPromise = videoElement?.play?.();

                if(playPromise?.catch) {
                    playPromise.catch(() => {});
                }
            }

            const syncBackdropVideo = () => {
                const foregroundVideo = storyVideo.value;
                const backdropVideo = storyBackdropVideo.value;

                if(! foregroundVideo || ! backdropVideo) {
                    return;
                }

                try {
                    if(backdropVideo.readyState > 0 && Number.isFinite(foregroundVideo.currentTime) && Math.abs(backdropVideo.currentTime - foregroundVideo.currentTime) > 0.2) {
                        backdropVideo.currentTime = foregroundVideo.currentTime;
                    }

                    if(foregroundVideo.paused || foregroundVideo.ended) {
                        backdropVideo.pause();
                    }
                    else {
                        playVideo(backdropVideo);
                    }
                } catch (error) {}
            }

            return {
                isLoading: isLoading,
                frameData: frameData,
                storyVideo: storyVideo,
                storyBackdropVideo: storyBackdropVideo,
                mediaSourceUrl: mediaSourceUrl,
                foregroundFitClass: computed(() => {
                    return storyMediaObjectFitClass(mediaItem.value, loadedDimensions.value);
                }),
                showBlurBackdrop: computed(() => {
                    return shouldUseStoryBlurBackdrop(mediaItem.value, loadedDimensions.value);
                }),
                handleLoadedMetadata: (event) => {
                    loadedDimensions.value = elementVideoDimensions(event.target);
                    syncBackdropVideo();
                },
                syncBackdropVideo: syncBackdropVideo,
                onLoaded: () => {
                    playVideo(storyVideo.value);
                    isLoading.value = false;
                    syncBackdropVideo();
                    colibriEventBus.emit('story:play');
                }
            }
        },
        components: {
            StoryMediaLoader: StoryMediaLoader
        }
    });
</script>
