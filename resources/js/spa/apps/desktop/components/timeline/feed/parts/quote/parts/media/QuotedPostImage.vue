<template>
    <div class="overflow-hidden border-y border-bord-card">
        <PostContentImageLayout v-bind:postMedia="postMedia" layout="quote"></PostContentImageLayout>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';
    import PostContentImageLayout from '@/kernel/vue/components/media/image/PostContentImageLayout.vue';

    export default defineComponent({
        props: {
            postMedia: { type: Array, default: () => [] },
        },
        components: { PostContentImageLayout },
    });
</script>
