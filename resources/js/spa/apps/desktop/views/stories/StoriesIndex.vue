<template>
    <div class="inset-0 bg-[#101214] z-50 fixed py-14 2xl:py-24 overflow-y-auto">
        <span v-if="currentStory" class="fixed top-4 left-4 2xl:top-8 2xl:left-8 text-par-m text-white opacity-80">
            {{ currentStory.relations.user.name }} <span class="opacity-50">({{ currentStory.relations.frames.length }})</span>
        </span>
        <button v-on:click="closeStories" class="fixed top-4 right-4 2xl:top-8 2xl:right-8 text-par-m text-white opacity-60 hover:opacity-100">
            {{ $t('labels.close') }}
        </button>
        <swiper-container v-on:swiperslidechange="handleSlideChange" init="false">
            <swiper-slide v-on:click="slideToStory(idx)" v-for="(storyItem, idx) in stories" class="w-[360px] h-[640px] 2xl:w-[400px] 2xl:h-[712px] shadow-xl" v-bind:key="storyItem.story_uuid">
                <StoryPlayer 
                    v-if="activeSlideIndex == idx" 
                    v-bind:storyItem="storyItem"
                    v-on:view="handleStoryView"
                    v-bind:key="storyPlayerKey(storyItem)"
                v-on:finish="handleStoryFinish"></StoryPlayer>
                <StoryCard v-else v-bind:storyItem="storyItem"></StoryCard>
            </swiper-slide>
        </swiper-container>
    </div>
</template>

<script>
    import { computed, defineComponent, onMounted, onUnmounted, ref } from 'vue';
    import { useRouter } from 'vue-router';
    import { register  } from 'swiper/element/bundle';
    import { useStoriesStore } from '@D/store/stories/stories.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import StoryPlayer from '@D/views/stories/parts/StoryPlayer.vue';
    import StoryCard from '@D/views/stories/parts/StoryCard.vue';
    
    register();

    export default defineComponent({
        props: {
            story_uuid: {
                type: String,
                required: true
            }
        },
        setup: function(props) {
            const storiesStore = useStoriesStore();
            const router = useRouter();
            const activeSlideIndex = ref(0);
            const stories = computed(() => {
                return storiesStore.stories;
            });

            var storiesSwiper;
            let processingPollTimer = null;

            const currentStory = computed(() => {
                return stories.value[activeSlideIndex.value];
            });

            const handleStoryDelete = async (frameId) => {
                if(! currentStory.value) {
                    return;
                }

                await storiesStore.deleteStory(currentStory.value.story_uuid, frameId);

                if(! stories.value.length) {
                    closeStories();
                }
            }

            const storyHasProcessingFrames = (storyItem) => {
                return storyItem?.relations?.frames?.some((frameItem) => {
                    const isProcessing = frameItem.status === 'processing' || frameItem.media?.status === 'processing';

                    return isProcessing && ! ['failed', 'ready'].includes(frameItem.progress?.stage);
                });
            };

            const stopProcessingPoll = () => {
                if(processingPollTimer) {
                    clearInterval(processingPollTimer);
                    processingPollTimer = null;
                }
            };

            const refreshCurrentStory = async () => {
                if(! currentStory.value) {
                    stopProcessingPoll();
                    closeStories();

                    return;
                }

                try {
                    await storiesStore.fetchStory(currentStory.value.story_uuid);
                } catch (error) {
                    stopProcessingPoll();
                    closeStories();

                    return;
                }

                if(activeSlideIndex.value >= stories.value.length) {
                    activeSlideIndex.value = Math.max(0, stories.value.length - 1);
                }

                if(! stories.value.length) {
                    stopProcessingPoll();
                    closeStories();

                    return;
                }

                if(! stories.value.some(storyHasProcessingFrames)) {
                    stopProcessingPoll();
                }
            };

            const startProcessingPoll = () => {
                if(processingPollTimer) {
                    return;
                }

                processingPollTimer = setInterval(refreshCurrentStory, 3000);
            };

            onMounted(async () => {
                try {
                    await storiesStore.fetchStory(props.story_uuid);
                } catch (error) {
                    alert(error.message);
                    closeStories();

                    return;
                }

                if(stories.value.some(storyHasProcessingFrames)) {
                    startProcessingPoll();
                }

                storiesSwiper = document.querySelector('swiper-container');

                Object.assign(storiesSwiper, {
                    slidesPerView: 'auto',
                    centeredSlides: true,
                    spaceBetween: 8,
                    allowTouchMove: false,
                    effect: false,
                    speed: 600,
                    keyboard:  {
                        enabled: true,
                        onlyInViewport: true
                    }
                });

                colibriEventBus.on('story:delete', handleStoryDelete);
                storiesSwiper.initialize();
            });

            onUnmounted(() => {
                colibriEventBus.off('story:delete', handleStoryDelete);
                stopProcessingPoll();
            });

            const closeStories = () => {
                router.push({
                    name: 'home_index'
                });
            }

            return {
                stories: stories,
                currentStory: currentStory,
                activeSlideIndex: activeSlideIndex,
                handleStoryView: (frameId) => {
                    const frameData = currentStory.value.relations.frames.find((frameItem) => {
                        return frameItem.id === frameId;
                    });

                    if(frameData && frameData.activity.is_seen === false) {
                        storiesStore.recordStoryView(currentStory.value.story_uuid, frameId);
                    }
                },
                handleSlideChange: (event) => {
                    activeSlideIndex.value = event.target.swiper.activeIndex;

                    router.replace({
                        name: 'stories_index', 
                        params: {
                            story_uuid: currentStory.value.story_uuid
                        }
                    });
                },
                slideToStory: (storyIndex) => {
                    storiesSwiper.swiper.slideTo(storyIndex);
                },
                storyPlayerKey: (storyItem) => {
                    const framesKey = storyItem.relations.frames.map((frameItem) => {
                        return `${frameItem.id}:${frameItem.status}:${frameItem.progress?.display || 0}`;
                    }).join('|');

                    return `${storyItem.story_uuid}:${framesKey}`;
                },
                closeStories: closeStories,
                handleStoryFinish: () => {
                    if(activeSlideIndex.value < (stories.value.length - 1)) {
                        storiesSwiper.swiper.slideNext();
                    }
                    else{
                        closeStories();
                    }
                }
            }
        },
        components: {
            StoryPlayer: StoryPlayer,
            StoryCard: StoryCard
        }
    });
</script>
