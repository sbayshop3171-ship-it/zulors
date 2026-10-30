<template>
	<div ref="stageElement" class="post-image-preview-stage relative isolate grid min-h-0 w-full min-w-0 overflow-hidden" v-bind:style="stageStyle">
		<img v-if="postMedia.length === 1 && (postMedia[0].preview_url || postMedia[0].source_url)" v-bind:src="postMedia[0].preview_url || postMedia[0].source_url" class="pointer-events-none absolute inset-0 size-full scale-110 object-cover opacity-45 blur-2xl" alt="" aria-hidden="true">
		<div v-for="(mediaItem, index) in postMedia"
			v-bind:key="mediaItem.preview_key || mediaItem.id || mediaItem.preview_url || mediaItem.source_url"
			v-bind:class="[isHeroTile(index), isWideTile(index), (mediaItem.deleted ? 'opacity-20' : '')]"
			v-bind:style="frameStyle(mediaItem)"
		class="relative z-0 isolate min-h-0 min-w-0 overflow-hidden bg-fill-tr">
			<img v-if="postMedia.length > 1 && (mediaItem.preview_url || mediaItem.source_url)" v-bind:src="mediaItem.preview_url || mediaItem.source_url" class="absolute inset-0 size-full scale-110 object-cover opacity-45 blur-xl" alt="" aria-hidden="true">
			<img v-if="mediaItem.preview_url || mediaItem.source_url" v-on:load="captureImageDimensions(mediaItem, $event)" v-bind:src="mediaItem.preview_url || mediaItem.source_url" v-bind:class="postMedia.length === 1 ? 'post-image-preview-content size-full object-contain' : 'post-image-preview-content relative z-0 size-full object-contain'" alt="Image">

			<div v-if="canDelete && ! mediaItem.deleted" class="absolute top-3 right-3 inline-block z-10">
				<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
	import { fitMediaPreviewSize } from '@/kernel/services/media/media-preview-sizing.js';
	import { useMediaPreviewBounds } from '@/kernel/vue/composables/media-preview-bounds.js';
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

			return {
				stageElement,
				stageStyle: computed(() => {
					const count = props.postMedia.length;
					const columns = count < 2 ? 1 : count <= 4 ? 2 : count <= 6 ? 3 : 4;
					if (count === 1) return { display: 'grid', width: '100%', height: 'auto', placeItems: 'center' };
					return {
						display: 'grid', width: '100%', height: 'min(46dvh, calc(100dvh - 350px))',
						gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
						gridTemplateRows: `repeat(${Math.max(1, Math.ceil(count / columns))}, minmax(0, 1fr))`,
					};
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
				isHeroTile: (index) => props.postMedia.length === 3 && index === 0 ? 'row-span-2' : '',
				isWideTile: (index) => {
					const count = props.postMedia.length;
					const columns = count <= 4 ? 2 : count <= 6 ? 3 : 4;
				const remainder = count % columns;
				const span = columns - remainder + 1;
				return count >= 5 && remainder > 0 && index === count - 1 ? ({ 2: 'col-span-2', 3: 'col-span-3', 4: 'col-span-4' }[span] || '') : '';
				},
					canDelete: computed(() => {
						return props.canDelete;
					})
				}
			},
		components: {
			MediaDeleteButton: MediaDeleteButton
		}
	})
</script>
