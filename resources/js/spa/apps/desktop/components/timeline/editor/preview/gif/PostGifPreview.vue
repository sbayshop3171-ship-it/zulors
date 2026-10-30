<template>
    <ContentImage
        v-for="mediaItem in postMedia"
        v-bind:key="mediaItem.id || mediaItem.source_url"
        v-bind:mediaItem="mediaItem"
        v-bind:src="mediaItem.metadata?.preview_url || mediaItem.source_url"
        alt="GIF"
    >
        <MediaBlurOverlay v-if="mediaItem.deleted"></MediaBlurOverlay>
        <div v-else-if="canDelete" class="absolute right-4 top-4 z-10 inline-block">
            <MediaDeleteButton v-on:click.stop="deleteMedia(mediaItem)"></MediaDeleteButton>
        </div>
        <div class="absolute bottom-2 left-2 z-10 inline-block">
            <GiphyAttribution></GiphyAttribution>
        </div>
    </ContentImage>
</template>

<script>
    import { computed, defineComponent } from 'vue';
    import ContentImage from '@/kernel/vue/components/media/image/ContentImage.vue';
    import MediaDeleteButton from '@D/components/timeline/editor/buttons/MediaDeleteButton.vue';
    import GiphyAttribution from '@/kernel/vue/components/attributes/GiphyAttribution.vue';
    import MediaBlurOverlay from '@D/components/timeline/editor/animations/MediaBlurOverlay.vue';

    export default defineComponent({
        emits: ['delete'],
        props: {
            postMedia: { type: Array, default: () => [] },
            canDelete: { type: Boolean, default: true },
        },
        setup(props, context) {
            return {
                deleteMedia: (mediaItem) => context.emit('delete', mediaItem),
                postMedia: computed(() => props.postMedia),
            };
        },
        components: { ContentImage, MediaDeleteButton, GiphyAttribution, MediaBlurOverlay },
    });
</script>
