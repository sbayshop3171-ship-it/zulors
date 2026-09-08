<template>
    <div class="relative flex aspect-square w-[min(68vw,256px)] items-center justify-center overflow-hidden rounded-lg bg-black text-white" role="status">
        <img v-if="media.thumbnail_url" :src="media.thumbnail_url" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
        <span class="relative px-4 text-center text-sm">{{ label }}</span>
    </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ media: { type: Object, required: true } });
const label = computed(() => {
    const metadata = props.media.metadata || {};
    if(metadata.processing_state === 'failed' || metadata.upload_state === 'failed') return 'Video processing failed';
    if(metadata.upload_state !== 'uploaded') return `Uploading video ${metadata.upload_progress || 0}%`;
    return `Processing video ${metadata.processing_progress || 0}%`;
});
</script>
