<template>
    <PostContentImageLayout
        v-bind:postMedia="postMedia"
        v-bind:isSensitive="isSensitive"
        v-bind:isPriority="isPriority"
        v-bind:isSelectable="true"
        v-on:select="$emit('select', $event)"
    ></PostContentImageLayout>
</template>

<script>
    import { defineComponent } from 'vue';
    import PostContentImageLayout from '@/kernel/vue/components/media/image/PostContentImageLayout.vue';

    export default defineComponent({
        emits: ['select'],
        props: {
            postMedia: { type: Array, default: () => [] },
            isSensitive: { type: Boolean, default: false },
            sponsoredFeed: { type: Boolean, default: false },
            isPriority: { type: Boolean, default: false },
        },
        components: { PostContentImageLayout },
    });
</script>
