<template>
    <div class="h-full w-full overflow-hidden">
        <StoryMediaLoader v-if="isLoading" v-bind:lqipBase64="frameData.media.lqip_base64"></StoryMediaLoader>
        <video v-bind:class="[isLoading ? 'opacity-0' : 'opacity-100']" ref="storyVideo" webkit-playsinline muted playsinline v-on:loadedmetadata="onLoaded" v-on:loadeddata="onLoaded" class="w-full h-full object-cover">
            <source v-bind:src="frameData.media.source_url" type="video/mp4">
        </video>
    </div>
</template>
<script>
    import { defineComponent, ref, inject, onMounted, watch, onBeforeUnmount } from 'vue';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import StoryMediaLoader from '@M/views/stories/parts/media/StoryMediaLoader.vue';

    export default defineComponent({
        props: {
            frameData: {
                type: Object,
                required: true
            }
        },
        setup: function(props) {
            const storyVideo = ref(null);
            const isLoading = ref(true);
            const frameData = ref(props.frameData);
            const playerState = inject('playerState');
            
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
                    storyVideo.value.play();
                }
            }

            return {
                isLoading: isLoading,
                frameData: frameData,
                storyVideo: storyVideo,
                onLoaded: () => {
                    isLoading.value = false;
                    storyVideo.value.play();
                    colibriEventBus.emit('story:play');
                }
            }
        },
        components: {
            StoryMediaLoader: StoryMediaLoader
        }
    });
</script>