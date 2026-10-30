<template>
	<div class="flex w-full items-center gap-px overflow-x-auto hidden-scroll">
		<div
			v-for="mediaItem in postMedia"
			v-bind:key="mediaItem.preview_key || mediaItem.id"
			v-bind:class="[tileWidth, mediaItem.deleted ? 'opacity-20' : '']"
			class="relative aspect-square shrink-0 overflow-hidden bg-fill-tr"
		>
			<img v-bind:src="mediaItem.preview_url || mediaItem.source_url" class="size-full object-cover" alt="Image">

			<div v-if="canDelete && ! mediaItem.deleted" class="absolute right-3 top-3 z-10 inline-block">
				<MediaDeleteButton v-on:click.stop="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { computed, defineComponent } from 'vue';
	import MediaDeleteButton from '@M/views/editors/post/parts/buttons/MediaDeleteButton.vue';

	export default defineComponent({
		emits: ['delete'],
		props: {
			postMedia: { type: Array, required: true },
			canDelete: { type: Boolean, default: true },
		},
		setup(props) {
			return {
				tileWidth: computed(() => {
					if(props.postMedia.length === 1) return 'w-full';
					if(props.postMedia.length === 2) return 'w-1/2';
					return 'w-40';
				}),
			};
		},
		components: { MediaDeleteButton },
	});
</script>
