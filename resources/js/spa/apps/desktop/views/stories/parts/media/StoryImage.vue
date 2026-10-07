<template>
    <div class="story-media-stage">
        <StoryMediaLoader v-show="isLoading" class="absolute inset-0 z-20" v-bind:lqipBase64="frameData.media.lqip_base64"></StoryMediaLoader>
        <img
            v-if="showBlurBackdrop"
            v-bind:src="frameData.media.source_url"
            class="story-media-backdrop"
            alt=""
            aria-hidden="true"
        >
        <div v-if="showBlurBackdrop" class="story-media-backdrop-shade"></div>
        <img
            v-show="! isLoading"
            v-bind:style="foregroundStyle"
            v-bind:class="foregroundFitClass"
            class="story-media-foreground"
            v-bind:src="frameData.media.source_url"
            v-on:load="onLoaded"
            alt="Image"
        >
    </div>
</template>
<script>
    import { computed, defineComponent, ref } from 'vue';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { elementImageDimensions, shouldUseStoryBlurBackdrop, storyMediaObjectFitClass } from '@/kernel/services/media/story-media-presentation.js';
    
    import StoryMediaLoader from '@D/views/stories/parts/media/StoryMediaLoader.vue';

    export default defineComponent({
        props: {
            frameData: {
                type: Object,
                required: true
            }
        },
        setup: function(props, context) {
            const isLoading = ref(true);
            const loadedDimensions = ref({});
            const mediaItem = computed(() => {
                return props.frameData.media || {};
            });
            const foregroundStyle = computed(() => {
                const transform = props.frameData?.meta?.story_image_transform?.foreground || {};
                return { transform: `translate3d(${Number(transform.translate_x || 0)}px, ${Number(transform.translate_y || 0)}px, 0) scale(${Number(transform.scale || 1)}) rotate(${Number(transform.rotation || 0)}deg)` };
            });

            return {
                isLoading: isLoading,
                foregroundFitClass: computed(() => {
                    return storyMediaObjectFitClass(mediaItem.value, loadedDimensions.value);
                }),
                showBlurBackdrop: computed(() => {
                    return shouldUseStoryBlurBackdrop(mediaItem.value, loadedDimensions.value);
                }),
                foregroundStyle: foregroundStyle,
                onLoaded: (event) => {
                    loadedDimensions.value = elementImageDimensions(event.target);
                    isLoading.value = false;
                    colibriEventBus.emit('story:play');
                }
            };
        },
        components: {
            StoryMediaLoader: StoryMediaLoader
        }
    });
</script>
