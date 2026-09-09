<template>
	<div
		v-for="mediaItem in postMedia"
		v-bind:key="mediaItem.preview_key || mediaItem.id || mediaItem.preview_url || mediaItem.source_url"
		v-bind:class="[(mediaItem.deleted ? 'opacity-20' : '')]"
		v-bind:style="frameStyle(mediaItem)"
	class="bg-black flex justify-center overflow-hidden relative">
		<div v-if="canDelete" class="absolute top-3 right-3 z-10 inline-block">
			<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
		</div>
		<div class="size-full">
			<video
				v-if="videoUrl(mediaItem)"
				v-on:loadedmetadata="captureVideoMetadata(mediaItem, $event)"
				controls
				playsinline
				preload="metadata"
				v-bind:poster="mediaItem.thumbnail_url"
				v-bind:src="videoUrl(mediaItem)"
			class="w-full h-full object-cover"></video>
			<img v-else-if="mediaItem.thumbnail_url" v-bind:src="mediaItem.thumbnail_url" class="size-full object-cover" alt="Video thumbnail">
			<div v-else class="size-full flex-center text-white" role="status" aria-label="Video preview unavailable">
				<span class="size-icon-large"><SvgIcon name="video-recorder" type="line"></SvgIcon></span>
			</div>
		</div>
		<div v-if="mediaItem.metadata && mediaItem.metadata.duration" class="pointer-events-none absolute left-4 top-4 rounded-full bg-black/60 px-2.5 py-1">
			<VideoDurationTime v-bind:videoDuration="mediaItem.metadata.duration"></VideoDurationTime>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';
	import { applyVideoPresentationMetadata, buildVideoPresentationMetadata, isVideoPortrait, videoFrameAspectStyle } from '@/kernel/services/media/video-metadata.js';

	import MediaDeleteButton from '@M/views/editors/post/parts/buttons/MediaDeleteButton.vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';

	export default defineComponent({
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
			emits: ['delete'],
			setup: function(props) {
				return {
					canDelete: computed(() => {
						return props.canDelete;
					}),
					videoUrl: (mediaItem) => {
						return mediaItem.preview_url || mediaItem.source_url;
					},
					frameStyle: (mediaItem) => {
						return videoFrameAspectStyle(mediaItem.metadata, isVideoPortrait(mediaItem.metadata));
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
