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
                    v-bind:key="`${storyItem.story_uuid}-${storyItem.relations.frames.length}`"
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