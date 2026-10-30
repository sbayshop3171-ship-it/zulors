<template>
	<MediaImageGallery class="post-image-preview-stage" v-bind:mediaItems="postMedia">
		<template #overlay="{ mediaItem }">
			<div v-if="canDelete && ! mediaItem.deleted" class="absolute right-3 top-3 z-20 inline-block">
				<MediaDeleteButton v-on:click.stop="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
		</template>
	</MediaImageGallery>
</template>

<script>
	import { defineComponent } from 'vue';
	import MediaImageGallery from '@/kernel/vue/components/media/image/MediaImageGallery.vue';
	import MediaDeleteButton from '@M/views/editors/post/parts/buttons/MediaDeleteButton.vue';

	export default defineComponent({
		emits: ['delete'],
		props: {
			postMedia: { type: Array, required: true },
			canDelete: { type: Boolean, default: true },
		},
		components: { MediaImageGallery, MediaDeleteButton },
	});
</script>
