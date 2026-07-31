<template>
	<div
		v-for="mediaItem in postMedia"
		v-bind:key="mediaItem.id || mediaItem.preview_url || mediaItem.source_url"
		v-bind:class="[(mediaItem.deleted ? 'opacity-20' : '')]"
	class="bg-black flex justify-center overflow-hidden relative">
		<div v-if="canDelete" class="absolute top-3 right-3 inline-block">
			<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
		</div>
		<div class="w-full">
			<video
				v-on:loadedmetadata="captureVideoMetadata(mediaItem, $event)"
				controls
				playsinline
				preload="metadata"
				v-bind:poster="mediaItem.thumbnail_url"
				v-bind:src="videoUrl(mediaItem)"
			class="block w-full h-auto"></video>
		</div>
		<div v-if="mediaItem.metadata && mediaItem.metadata.duration" class="pointer-events-none absolute left-4 top-4 rounded-full bg-black/60 px-2.5 py-1">
			<VideoDurationTime v-bind:videoDuration="mediaItem.metadata.duration"></VideoDurationTime>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';
	import { applyVideoPresentationMetadata, buildVideoPresentationMetadata } from '@/kernel/services/media/video-metadata.js';

	import MediaDeleteButton from '@M/views/editors/post/parts/buttons/MediaDeleteButton.vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';

	export default defineComponent({
		props: {
				postMedia: {
					type: Object,
					required: true
				},
				canDelete: {
					type: Boolean,
					default: true
				}
			},
			emits: ['delete'],
			setup: function(props) {
				return {
					canDelete: computed(() => {
						return props.canDelete;
					}),
					videoUrl: (mediaItem) => {
						return mediaItem.preview_url || mediaItem.source_url;
					},
					captureVideoMetadata: (mediaItem, event) => {
						const videoElement = event.currentTarget;

						applyVideoPresentationMetadata(mediaItem, buildVideoPresentationMetadata(
							videoElement.videoWidth,
							videoElement.videoHeight,
							videoElement.duration
						));
					}
			};
		},
		components: {
			MediaDeleteButton: MediaDeleteButton,
			VideoDurationTime: VideoDurationTime,
		}
	});
</script>
