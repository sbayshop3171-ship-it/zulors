<template>
	<MediaImageGallery v-bind:mediaItems="postMedia"></MediaImageGallery>
</template>

<script>
	import { defineComponent } from 'vue';
	import MediaImageGallery from '@/kernel/vue/components/media/image/MediaImageGallery.vue';

	export default defineComponent({
		props: {
			postMedia: { type: Array, default: () => [] },
		},
		components: { MediaImageGallery },
	});
</script>
