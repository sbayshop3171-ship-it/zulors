<template>
	<div ref="stageElement" class="post-image-preview-stage relative isolate min-h-0 w-full min-w-0 overflow-hidden bg-fill-pr" v-bind:style="stageStyle">
		<img v-if="postMedia.length === 1 && (postMedia[0].preview_url || postMedia[0].source_url)" v-bind:src="postMedia[0].preview_url || postMedia[0].source_url" class="pointer-events-none absolute inset-0 size-full scale-110 object-cover opacity-45 blur-2xl" alt="" aria-hidden="true">
		<div v-for="(mediaItem, index) in visibleMedia"
			v-bind:key="mediaItem.preview_key || mediaItem.id || mediaItem.preview_url || mediaItem.source_url"
			v-bind:class="[getPublicationImageTileClasses(postMedia.length, index), (mediaItem.deleted ? 'opacity-20' : '')]"
			v-bind:style="frameStyle(mediaItem)"
		class="relative z-0 isolate min-h-0 min-w-0 overflow-hidden bg-fill-pr">
			<img v-if="mediaItem.preview_url || mediaItem.source_url" v-on:load="captureImageDimensions(mediaItem, $event)" v-bind:src="mediaItem.preview_url || mediaItem.source_url" v-bind:class="postMedia.length === 1 ? 'post-image-preview-content size-full object-contain' : 'post-image-preview-content relative z-0 size-full object-cover'" alt="Image">

			<div v-if="canDelete && ! mediaItem.deleted" class="absolute top-3 right-3 z-20 inline-block">
				<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
			<div v-if="isPublicationImageGalleryMoreTile(postMedia.length, index)" class="pointer-events-none absolute inset-0 z-10 flex-center bg-black/55 text-white text-title-2 font-bold">
				+{{ extraCount }}
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
	import { fitMediaPreviewSize } from '@/kernel/services/media/media-preview-sizing.js';
	import { useMediaPreviewBounds } from '@/kernel/vue/composables/media-preview-bounds.js';
	import { getPublicationImageGalleryLayout, getPublicationImageGalleryStageStyle, getPublicationImageTileClasses, isPublicationImageGalleryMoreTile } from '@/kernel/services/media/publication-image-gallery.js';
	import MediaDeleteButton from '@M/views/editors/post/parts/buttons/MediaDeleteButton.vue';

	export default defineComponent({
		emits: ['delete'],
		props: {
			postMedia: {
				type: Array,
				required: true
			},
			canDelete: {
				type: Boolean,
				default: true
			}
		},
		setup: function(props) {
			const stageElement = ref(null);
			const imageDimensions = ref({});
			const { bounds, refresh } = useMediaPreviewBounds(stageElement, 350);
			const imageKey = (mediaItem) => String(mediaItem.preview_key || mediaItem.id || mediaItem.preview_url || mediaItem.source_url || 'image');
			const dimensionsFor = (mediaItem) => imageDimensions.value[imageKey(mediaItem)] || {
				width: Number(mediaItem.width || 0), height: Number(mediaItem.height || 0),
			};
			const layout = computed(() => getPublicationImageGalleryLayout(props.postMedia.length));

			return {
				stageElement,
				visibleMedia: computed(() => props.postMedia.slice(0, layout.value.visibleCount)),
				extraCount: computed(() => layout.value.extraCount),
				stageStyle: computed(() => {
					if(props.postMedia.length === 1) return { display: 'grid', width: '100%', height: 'auto', placeItems: 'center' };
					return getPublicationImageGalleryStageStyle(props.postMedia.length, bounds.value.width, bounds.value.height);
				}),
				frameStyle: (mediaItem) => {
					if(props.postMedia.length !== 1) return {};
					const dimensions = dimensionsFor(mediaItem);
					const size = fitMediaPreviewSize(dimensions.width, dimensions.height, bounds.value.width, bounds.value.height);
					return size.width && size.height ? { width: `${size.width}px`, height: `${size.height}px` } : { width: '100%', aspectRatio: '4 / 3' };
				},
				captureImageDimensions: (mediaItem, event) => {
					const image = event.currentTarget;
					imageDimensions.value[imageKey(mediaItem)] = { width: image.naturalWidth, height: image.naturalHeight };
					refresh();
				},
				getPublicationImageTileClasses: getPublicationImageTileClasses,
				isPublicationImageGalleryMoreTile: isPublicationImageGalleryMoreTile,
				canDelete: computed(() => props.canDelete)
			};
		},
		components: {
			MediaDeleteButton
		}
	});
</script>
