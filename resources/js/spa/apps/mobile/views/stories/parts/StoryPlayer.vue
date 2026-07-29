<template>
    <div class="w-full h-full items-center overflow-hidden relative">
        <div class="absolute top-0 left-0 right-0 z-10 text-white pt-4 px-4 pb-6 from-black/60 to-transparent bg-gradient-to-b">
            <template v-if="playerState.isInitialized">
                <StoryPlayback v-bind:storyData="storyData" v-bind:key="storyData.story_uuid"></StoryPlayback>
                <StoryHeader v-on:pause="pauseStory" v-on:play="playStory"></StoryHeader>
            </template>
        </div>
        <div class="absolute w-20 left-0 z-10 top-22 bottom-22 cursor-pointer" v-if="hasPrevItems" v-on:click="showPrevItem">
        </div>
        <div class="absolute w-20 right-0 z-10 top-22 bottom-22 cursor-pointer" v-if="hasNextItems" v-on:click="showNextItem">
        </div>

        <template v-if="playerState.isInitialized">
            <div class="h-full w-full cursor-pointer relative" v-on:click="toggleStory">
                <StoryVideo v-if="isVideo" v-bind:frameData="playerState.frameData" v-bind:key="playerState.frameData.id"></StoryVideo>
                <StoryImage v-else v-bind:frameData="playerState.frameData" v-bind:key="playerState.frameData.id"></StoryImage>
            </div>
        </template>

        <div v-if="playerState.isInitialized" class="absolute bottom-0 left-0 right-0 z-10 text-white pt-8 pb-4 from-black/60 to-transparent bg-gradient-to-t">
            <StoryContent></StoryContent>
            <StoryViews v-if="playerState.isOwner"></StoryViews>
        </div>
        
        <StoryModals v-if="playerState.isInitialized"></StoryModals>
    </div>
</template>

<script>
    import { defineComponent, onMounted, ref, provide, reactive, onUnmounted, onBeforeUnmount, computed } from 'vue';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    
    import StoryVideo from '@M/views/stories/parts/media/StoryVideo.vue';
    import StoryImage from '@M/views/stories/parts/media/StoryImage.vue';
    import StoryViews from '@M/views/stories/parts/footer/StoryViews.vue';
    import StoryContent from '@M/views/stories/parts/footer/StoryContent.vue';
    import StoryPlayback from '@M/views/stories/parts/header/StoryPlayback.vue';
    import StoryHeader from '@M/views/stories/parts/header/StoryHeader.vue';
    import StoryModals from '@M/views/stories/parts/modals/StoryModals.vue';
    
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
                toggleStory: () => {
                    if(playerState.isPaused) {
                        playStory();
                    }
                    else {
                        pauseStory();
                    }
                },
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
            StoryModals: StoryModals
        }
    });
</script>