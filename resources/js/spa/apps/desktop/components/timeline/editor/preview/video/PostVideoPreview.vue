<template>
	<div class="bg-black border border-bord-card flex justify-center rounded-2xl max-h-[620px] overflow-hidden relative">
		<template v-if="mediaItem.deleted">
			<MediaBlurOverlay></MediaBlurOverlay>
		</template>

		<template v-else>
			<div v-if="canDelete" class="absolute top-4 right-4 inline-block">
				<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
		</template>

		<div class="w-full">
			<video
				controls
				playsinline
				preload="metadata"
				v-bind:poster="mediaItem.thumbnail_url"
				v-bind:src="videoUrl"
			class="w-full h-full object-contain"></video>
		</div>

		<div v-if="mediaDuration" class="pointer-events-none absolute left-4 top-4 rounded-full bg-black/60 px-2.5 py-1">
			<VideoDurationTime v-bind:videoDuration="mediaDuration"></VideoDurationTime>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	import MediaBlurOverlay from '@D/components/timeline/editor/animations/MediaBlurOverlay.vue';
	import MediaDeleteButton from '@D/components/timeline/editor/buttons/MediaDeleteButton.vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';

	export default defineComponent({
		props: {
			mediaItem: {
				type: Object,
				required: true
			},
			canDelete: {
				type: Boolean,
				default: true
			}
		},
		emits: ['delete'],
		setup: function(props, context) {
			return {
				videoUrl: computed(() => {
					return props.mediaItem.preview_url || props.mediaItem.source_url;
				}),
				mediaDuration: computed(() => {
					return props.mediaItem.metadata?.duration || null;
				}),
				canDelete: computed(() => {
					return props.canDelete;
				})
			};
		},
		components: {
			MediaBlurOverlay: MediaBlurOverlay,
			MediaDeleteButton: MediaDeleteButton,
			VideoDurationTime: VideoDurationTime,
		}
	});
</script>
