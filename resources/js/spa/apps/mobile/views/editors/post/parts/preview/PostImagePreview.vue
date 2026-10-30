<template>
	<div class="post-image-preview-stage grid h-full min-h-0 w-full min-w-0 grid-cols-2 overflow-hidden bg-fill-tr" v-bind:style="gridStyle">
		<div v-for="(mediaItem, index) in postMedia"
			v-bind:key="mediaItem.preview_key || mediaItem.id || mediaItem.preview_url || mediaItem.source_url"
			v-bind:class="[isWideTile(index), (mediaItem.deleted ? 'opacity-20' : '')]"
		class="relative min-h-0 min-w-0 overflow-hidden">
			<img v-bind:src="mediaItem.preview_url || mediaItem.source_url" class="size-full object-cover" alt="Image">

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
					return {
						gridTemplateColumns: count === 1 ? 'minmax(0, 1fr)' : 'repeat(2, minmax(0, 1fr))',
						gridTemplateRows: `repeat(${Math.max(1, Math.ceil(count / 2))}, minmax(0, 1fr))`,
					};
				}),
				isWideTile: (index) => props.postMedia.length > 1 && props.postMedia.length % 2 === 1 && index === props.postMedia.length - 1 ? 'col-span-2' : '',
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
