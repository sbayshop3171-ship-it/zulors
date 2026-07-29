<template>
    <div class="inset-0 bg-black z-50 fixed" v-bind:class="{ 'pb-5': $isStandalone() }">
        <swiper-container v-on:swiperslidechange="handleSlideChange" class="h-full" init="false">
            <swiper-slide v-on:click="slideToStory(idx)" v-for="(storyItem, idx) in stories" class="w-full h-full" v-bind:key="storyItem.story_uuid">
                <StoryPlayer v-if="activeSlideIndex == idx"
					v-bind:storyItem="storyItem"
                    v-on:view="handleStoryView"
                    v-bind:key="`${storyItem.story_uuid}-${storyItem.relations.frames.length}`"
                v-on:finish="handleStoryFinish"></StoryPlayer>

				<div v-else class="w-full h-full"></div>
            </swiper-slide>
        </swiper-container>
    </div>
</template>

<script>
    import { computed, defineComponent, onMounted, onUnmounted, ref } from 'vue';
    import { useRouter } from 'vue-router';
    import { register  } from 'swiper/element/bundle';
    import { useStoriesStore } from '@M/store/stories/stories.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import StoryPlayer from '@M/views/stories/parts/StoryPlayer.vue';
    
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

            const currentStory = computed(() => {
                return stories.value[activeSlideIndex.value];
            });

            const handleStoryDelete = async (frameId) => {
                await storiesStore.deleteStory(currentStory.value.story_uuid, frameId);

                if(! stories.value.length) {
                    closeStories();
                }
            }

            onMounted(async () => {
                try {
                    await storiesStore.fetchStory(props.story_uuid);
                } catch (error) {
                    alert(error.message);    

					closeStories();
                }

                storiesSwiper = document.querySelector('swiper-container');

                Object.assign(storiesSwiper, {
                    slidesPerView: 1,
                    centeredSlides: true,
                    spaceBetween: 0,
                    allowTouchMove: true,
                    effect: false,
                    speed: 600,
                    keyboard:  {
                        enabled: true,
                        onlyInViewport: true
                    }
                });

                colibriEventBus.on('story:delete', handleStoryDelete);
				colibriEventBus.on('story:leave', closeStories);
                storiesSwiper.initialize();
            });

            onUnmounted(() => {
                colibriEventBus.off('story:delete', handleStoryDelete);
				colibriEventBus.off('story:leave', closeStories);
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
                slideToStory: (storyIndex) => {
                    storiesSwiper.swiper.slideTo(storyIndex);
                },
                closeStories: closeStories,
                handleStoryFinish: () => {
                    if(activeSlideIndex.value < (stories.value.length - 1)) {
                        storiesSwiper.swiper.slideNext();
                    }
                    else{
                        closeStories();
                    }
                },
				handleSlideChange: (event) => {
                    activeSlideIndex.value = event.target.swiper.activeIndex;
                }
            }
        },
        components: {
            StoryPlayer: StoryPlayer
        }
    });
</script>