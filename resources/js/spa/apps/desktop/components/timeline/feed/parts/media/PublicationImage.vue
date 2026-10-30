<template>
    <MediaImageGallery
        v-bind:mediaItems="postMedia"
        v-bind:isSensitive="isSensitive"
        v-bind:isPriority="isPriority"
        v-bind:isSelectable="true"
        v-on:select="$emit('select', $event)"
    ></MediaImageGallery>
</template>

<script>
    import { defineComponent } from 'vue';
    import MediaImageGallery from '@/kernel/vue/components/media/image/MediaImageGallery.vue';

    export default defineComponent({
        emits: ['select'],
        props: {
            postMedia: { type: Array, default: () => [] },
            isSensitive: { type: Boolean, default: false },
            sponsoredFeed: { type: Boolean, default: false },
            isPriority: { type: Boolean, default: false },
        },
        components: { MediaImageGallery },
    });
</script>
