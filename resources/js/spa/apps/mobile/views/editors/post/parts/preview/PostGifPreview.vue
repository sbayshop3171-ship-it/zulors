<template>
    <ContentImage
        v-for="mediaItem in postMedia"
        v-bind:key="mediaItem.id || mediaItem.source_url"
        v-bind:mediaItem="mediaItem"
        v-bind:src="mediaItem.metadata?.preview_url || mediaItem.source_url"
        v-bind:fitToViewport="true"
        alt="GIF"
    >
        <div v-if="canDelete" class="absolute right-3 top-3 z-10 inline-block">
            <MediaDeleteButton v-on:click.stop="$emit('delete', mediaItem)"></MediaDeleteButton>
        </div>
        <div class="absolute bottom-2 left-2 z-10 inline-block">
            <GiphyAttribution></GiphyAttribution>
        </div>
    </ContentImage>
</template>

<script>
    import { defineComponent } from 'vue';
    import ContentImage from '@/kernel/vue/components/media/image/ContentImage.vue';
    import MediaDeleteButton from '@M/views/editors/post/parts/buttons/MediaDeleteButton.vue';
    import GiphyAttribution from '@/kernel/vue/components/attributes/GiphyAttribution.vue';

    export default defineComponent({
        emits: ['delete'],
        props: {
            postMedia: { type: Array, default: () => [] },
            canDelete: { type: Boolean, default: true },
        },
        components: { ContentImage, MediaDeleteButton, GiphyAttribution },
    });
</script>
