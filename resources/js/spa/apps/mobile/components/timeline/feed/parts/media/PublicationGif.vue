<template>
    <ContentImage v-bind:mediaItem="mediaItem" v-bind:src="mediaItem?.source_url" alt="GIF">
        <div class="absolute bottom-2 left-2 z-10 inline-block">
            <GiphyAttribution></GiphyAttribution>
        </div>
    </ContentImage>
</template>

<script>
    import { computed, defineComponent } from 'vue';
    import ContentImage from '@/kernel/vue/components/media/image/ContentImage.vue';
    import GiphyAttribution from '@/kernel/vue/components/attributes/GiphyAttribution.vue';

    export default defineComponent({
        props: { postMedia: { type: Array, default: () => [] } },
        setup(props) {
            return { mediaItem: computed(() => props.postMedia[0] || {}) };
        },
        components: { ContentImage, GiphyAttribution },
    });
</script>
