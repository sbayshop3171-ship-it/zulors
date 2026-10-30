<template>
	<div class="post-image-preview-stage grid h-full min-h-0 w-full min-w-0 grid-cols-2 overflow-hidden bg-fill-tr" v-bind:style="gridStyle">
		<div v-for="(mediaItem, index) in postMedia"
			v-bind:key="mediaItem.preview_key || mediaItem.id || mediaItem.preview_url || mediaItem.source_url"
			v-bind:class="[isHeroTile(index), isWideTile(index), (mediaItem.deleted ? 'opacity-20' : '')]"
		class="relative isolate min-h-0 min-w-0 overflow-hidden bg-fill-tr">
			<img v-if="mediaItem.preview_url || mediaItem.source_url" v-bind:src="mediaItem.preview_url || mediaItem.source_url" class="post-image-preview-backdrop absolute inset-0 size-full scale-110 object-cover opacity-45 blur-xl" alt="" aria-hidden="true">
			<img v-if="mediaItem.preview_url || mediaItem.source_url" v-bind:src="mediaItem.preview_url || mediaItem.source_url" class="post-image-preview-content relative z-0 size-full object-contain" alt="Image">

			<div v-if="canDelete && ! mediaItem.deleted" class="absolute top-3 right-3 inline-block z-10">
				<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';
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
			return {
				gridStyle: computed(() => {
					const count = props.postMedia.length;
					const columns = count < 2 ? 1 : count <= 4 ? 2 : count <= 6 ? 3 : 4;
					return {
						gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
						gridTemplateRows: `repeat(${Math.max(1, Math.ceil(count / columns))}, minmax(0, 1fr))`,
					};
				}),
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
