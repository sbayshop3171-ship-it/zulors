<template>
    <div class="h-full w-full overflow-hidden">
        <StoryMediaLoader v-show="isLoading" v-bind:lqipBase64="frameData.media.lqip_base64"></StoryMediaLoader>
        <img v-show="! isLoading" class="w-full" v-bind:src="frameData.media.source_url" v-on:load="onLoaded" alt="Image">
    </div>
</template>
<script>
    import { defineComponent, ref } from 'vue';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    
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

            return {
                isLoading: isLoading,
                onLoaded: () => {
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