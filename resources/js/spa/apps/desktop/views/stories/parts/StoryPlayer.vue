<template>
    <div class="w-full h-full items-center overflow-hidden relative">
        <div class="absolute top-0 left-0 right-0 z-10 text-white pt-2 px-3 pb-6 from-black/60 to-transparent bg-gradient-to-b">
            <template v-if="playerState.isInitialized">
                <StoryPlayback v-bind:storyData="storyData"></StoryPlayback>
                <StoryHeader
                    v-on:play="playStory"
                v-on:pause="pauseStory"></StoryHeader>
            </template>
        </div>
        <div class="absolute left-3 z-10 top-half">
            <StorySliderButton v-on:click="showPrevItem" v-bind:directionBack="true" v-if="hasPrevItems"></StorySliderButton>
        </div>
        <div class="absolute right-3 z-10 top-half">
            <StorySliderButton v-on:click="showNextItem" v-if="hasNextItems"></StorySliderButton>
        </div>

        <template v-if="playerState.isInitialized">
            <StoryVideo v-if="isVideo" v-bind:frameData="playerState.frameData" v-bind:key="playerState.frameData.id"></StoryVideo>
            <StoryImage v-else v-bind:frameData="playerState.frameData" v-bind:key="playerState.frameData.id"></StoryImage>
        </template>

        <div v-if="playerState.isInitialized" class="absolute bottom-0 left-0 right-0 z-10 text-white pt-8 pb-3 from-black/60 to-transparent bg-gradient-to-t">
            <StoryContent></StoryContent>
            <StoryViews v-if="playerState.isOwner"></StoryViews>
            <StoryReply v-else></StoryReply>
        </div>
        
        <StoryModals v-if="playerState.isInitialized"></StoryModals>
    </div>
</template>

<script>
    import { defineComponent, onMounted, ref, provide, reactive, onUnmounted, onBeforeUnmount, computed } from 'vue';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    
    import StoryVideo from '@D/views/stories/parts/media/StoryVideo.vue';
    import StoryImage from '@D/views/stories/parts/media/StoryImage.vue';
    import StoryViews from '@D/views/stories/parts/footer/StoryViews.vue';
    import StoryContent from '@D/views/stories/parts/footer/StoryContent.vue';
    import StoryReply from '@D/views/stories/parts/footer/StoryReply.vue';
    import StoryPlayback from '@D/views/stories/parts/header/StoryPlayback.vue';
    import StoryHeader from '@D/views/stories/parts/header/StoryHeader.vue';
    import StoryModals from '@D/views/stories/parts/modals/StoryModals.vue';
    import StorySliderButton from '@D/views/stories/parts/StorySliderButton.vue';
    
    export default defineComponent({
        props: {
            storyItem: {
                type: Object,
                required: true
            }
        },
        emits: ['finish', 'view'],
        setup: function(props, context) {
            const storyData = ref(props.storyItem);

            const playerState = reactive({
                isInitialized: false,
                frameIndex: 0,
                frameData: null,
                isPaused: false,
                startTime: null,
                storyAuthor: storyData.value.relations.user,
                permissions: storyData.value.permissions,
                isOwner: storyData.value.meta.is_owner,
                animationFrameId: null
            });

            provide('playerState', playerState);
            provide('storyUrl', storyData.value.url);

            onMounted(() => {
                // Assign playable state object for each 
                // story item on init.

                storyData.value.relations.frames = storyData.value.relations.frames.map((frameItem) => {
                    frameItem.playable = {
                        playing: false,
                        played: false,
                        progress: 0,
                        leftDuration: 0,
                        duration: frameItem.duration_seconds
                    }

                    return frameItem;
                });

                playerState.frameData = storyData.value.relations.frames[0];
                playerState.frameData.playable.playing = true;

                playerState.isInitialized = true;

                colibriEventBus.on('story:play', playStory);
                colibriEventBus.on('story:pause', pauseStory);

                viewStory();
            });

            onBeforeUnmount(() => {
                clearProgressInterval();
            });

            onUnmounted(() => {
                colibriEventBus.off('story:play', playStory);
                colibriEventBus.off('story:pause', pauseStory);
            });

            const viewStory = () => {
                if(! playerState.isOwner) {
                    context.emit('view', playerState.frameData.id);
                }
            }

            const showPrevItem = () => {
                pauseStory();

                /*
                Reset current story item before stepping back
                */

                playerState.frameData.playable = {
                    playing: false,
                    played: false,
                    progress: 0,
                    leftDuration: 0,
                    duration: playerState.frameData.duration_seconds
                }

                playerState.frameIndex -= 1;

                playerState.frameData = storyData.value.relations.frames[playerState.frameIndex];

                /*
                Reset prev story item after stepping back
                */

                playerState.frameData.playable = {
                    playing: true,
                    played: false,
                    progress: 0,
                    leftDuration: 0,
                    duration: playerState.frameData.duration_seconds
                }
            }
            
            const showNextItem = () => {
                pauseStory();

                playerState.frameData.playable = {
                    playing: false,
                    played: true,
                    progress: 0,
                    leftDuration: 0,
                    duration: playerState.frameData.duration_seconds
                }

                playerState.frameIndex += 1;

                playerState.frameData = storyData.value.relations.frames[playerState.frameIndex];
 
                playerState.frameData.playable.playing = true;

                viewStory();
            }

            const handleStoryFinish = () => {
                if(playerState.frameIndex < (storyData.value.relations.frames.length - 1)) {
                    showNextItem();
                }
                else{
                    pauseStory();
                    context.emit('finish');
                }
            }

            const storyProgress = () => {
                let duration = playerState.frameData.playable.duration;
                
                if (playerState.frameData.playable.leftDuration > 0) {
                    playerState.startTime = Date.now() - (duration - playerState.frameData.playable.leftDuration) * 1000;
                } 
                else {
                    playerState.startTime = Date.now();
                }
                
                const tick = () => {
                    if(playerState.isPaused) {
                        clearProgressInterval();
                        return false;
                    }

                    const timePassed = (Date.now() - playerState.startTime) / 1000;

                    playerState.frameData.playable.leftDuration = (duration - timePassed);

                    playerState.frameData.playable.progress = Math.min((timePassed / duration) * 100, 100);

                    if (timePassed < duration) {
                        if (playerState.isPaused) {
                            clearProgressInterval();
                            return false;
                        }
                        else{
                            requestAnimationFrame(tick);   
                        }
                    }
                    else {
                        pauseStory();
                        clearProgressInterval();
                        handleStoryFinish();
                        return false;
                    }
                };

                playerState.animationFrameId = requestAnimationFrame(tick);
            };

            const clearProgressInterval = () => {
                cancelAnimationFrame(playerState.animationFrameId);
                playerState.animationFrameId = null;
            };

            const playStory = () => {
                playerState.isPaused = false;

                storyProgress();
            }

            const pauseStory = () => {
                playerState.isPaused = true;
            }

            return {
                storyData: storyData,
                playerState: playerState,
                showNextItem: showNextItem,
                showPrevItem: showPrevItem,
                pauseStory: pauseStory,
                playStory: playStory,
                hasNextItems: computed(() => {
                    if(playerState.frameIndex < (storyData.value.relations.frames.length - 1)) {
                        return true;
                    }
                    return false;
                }),
                isVideo: computed(() => {
                    if (playerState.frameData.type == 'video') {
                        return true;
                    }

                    return false;
                }),
                hasPrevItems: computed(() => {
                    if(playerState.frameIndex > 0) {
                        return true;
                    }

                    return false;
                })
            }
        },
        components: {
            StoryPlayback: StoryPlayback,
            StoryHeader: StoryHeader,
            StoryVideo: StoryVideo,
            StoryImage: StoryImage,
            StoryViews: StoryViews,
            StoryContent: StoryContent,
            StoryReply: StoryReply,
            StorySliderButton: StorySliderButton,
            StoryModals: StoryModals
        }
    });
</script>