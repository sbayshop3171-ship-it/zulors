<template>
	<div
		v-bind:class="frameWidthClass"
		v-bind:style="frameStyle"
	class="bg-black border border-bord-card flex justify-center rounded-2xl overflow-hidden relative">
		<template v-if="mediaItem.deleted">
			<MediaBlurOverlay></MediaBlurOverlay>
		</template>

		<template v-else>
			<div v-if="canDelete" class="absolute top-4 right-4 z-10 inline-block">
				<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
		</template>

		<div class="size-full">
			<video
				v-if="videoUrl"
				v-on:loadedmetadata="captureVideoMetadata"
				v-on:error="captureVideoError"
				controls
				playsinline
				webkit-playsinline
				preload="metadata"
				v-bind:poster="mediaItem.thumbnail_url"
				v-bind:src="videoUrl"
			class="w-full h-full object-contain"></video>
			<img v-else-if="mediaItem.thumbnail_url" v-bind:src="mediaItem.thumbnail_url" class="size-full object-contain" alt="Video thumbnail">
			<div v-else class="size-full flex-center text-white" role="status" aria-label="Video preview unavailable">
				<span class="size-icon-large"><SvgIcon name="video-recorder" type="line"></SvgIcon></span>
			</div>
		</div>
		<div v-if="mediaItem.metadata?.preview_unavailable" class="absolute inset-x-3 bottom-14 z-10 rounded-lg bg-black/75 px-3 py-2 text-center text-xs text-white" role="status">
			This video format cannot be previewed on this device. You can still publish it; playback will be available after processing.
		</div>

		<div v-if="mediaDuration" class="pointer-events-none absolute left-4 top-4 rounded-full bg-black/60 px-2.5 py-1">
			<VideoDurationTime v-bind:videoDuration="mediaDuration"></VideoDurationTime>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';
	import { applyVideoPresentationMetadata, buildVideoPresentationMetadata, isVideoPortrait, videoFrameAspectStyle } from '@/kernel/services/media/video-metadata.js';

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
				frameWidthClass: computed(() => {
					return 'w-full';
				}),
				frameStyle: computed(() => {
					return videoFrameAspectStyle(props.mediaItem.metadata, isVideoPortrait(props.mediaItem.metadata));
				}),
				captureVideoMetadata: (event) => {
					const videoElement = event.currentTarget;
					const width = props.mediaItem.native_file_id && props.mediaItem.width ? props.mediaItem.width : videoElement.videoWidth;
					const height = props.mediaItem.native_file_id && props.mediaItem.height ? props.mediaItem.height : videoElement.videoHeight;
					const presentation = buildVideoPresentationMetadata(width, height, videoElement.duration);

					if(presentation.dimensions) {
						props.mediaItem.width = presentation.dimensions.width;
						props.mediaItem.height = presentation.dimensions.height;
					}
					if(presentation.duration_seconds) props.mediaItem.duration_seconds = presentation.duration_seconds;

					applyVideoPresentationMetadata(props.mediaItem, presentation);
					if(props.mediaItem.metadata?.preview_unavailable) {
						applyVideoPresentationMetadata(props.mediaItem, { preview_unavailable: false });
					}
				},
				captureVideoError: () => {
					applyVideoPresentationMetadata(props.mediaItem, { preview_unavailable: true });
				},
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
