<template>
	<div
		v-for="mediaItem in postMedia"
		v-bind:key="mediaItem.preview_key || mediaItem.id || mediaItem.preview_url || mediaItem.source_url"
		v-bind:class="[(mediaItem.deleted ? 'opacity-20' : '')]"
		v-bind:style="frameStyle(mediaItem)"
	class="post-video-preview-frame bg-black flex justify-center overflow-hidden relative">
		<div v-if="canDelete" class="absolute top-3 right-3 z-10 inline-block">
			<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
		</div>
		<div class="size-full">
			<video
				v-if="videoUrl(mediaItem)"
				v-on:loadedmetadata="captureVideoMetadata(mediaItem, $event)"
				v-on:play="setPreviewPlaying(mediaItem, true)"
				v-on:pause="setPreviewPlaying(mediaItem, false)"
				v-on:ended="setPreviewPlaying(mediaItem, false)"
				v-on:error="captureVideoError(mediaItem)"
				controls
				playsinline
				webkit-playsinline
				preload="metadata"
				v-bind:poster="mediaItem.thumbnail_url"
				v-bind:src="videoUrl(mediaItem)"
			class="w-full h-full object-contain"></video>
			<img v-else-if="mediaItem.thumbnail_url" v-bind:src="mediaItem.thumbnail_url" class="size-full object-contain" alt="Video thumbnail">
			<div v-else class="size-full flex-center text-white" role="status" aria-label="Video preview unavailable">
				<span class="size-icon-large"><SvgIcon name="video-recorder" type="line"></SvgIcon></span>
			</div>
		</div>
		<button
			v-if="videoUrl(mediaItem) && !isPreviewPlaying(mediaItem)"
			type="button"
			class="absolute left-1/2 top-1/2 z-10 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 text-white shadow-lg ring-1 ring-white/70 transition hover:bg-black/75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
			v-bind:aria-label="playbackError(mediaItem) ? 'Retry video preview' : 'Play video preview'"
			v-on:click="playPreview(mediaItem, $event)">
			<svg v-if="!playbackError(mediaItem)" viewBox="0 0 24 24" class="ml-1 size-7 fill-current" aria-hidden="true"><path d="M7 4.75v14.5L19 12 7 4.75Z"></path></svg>
			<svg v-else viewBox="0 0 24 24" class="size-7 fill-current" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-2h2v2Zm0-4h-2V7h2v6Z"></path></svg>
		</button>
		<div v-if="mediaItem.metadata && mediaItem.metadata.duration" class="pointer-events-none absolute left-4 top-4 rounded-full bg-black/60 px-2.5 py-1">
			<VideoDurationTime v-bind:videoDuration="mediaItem.metadata.duration"></VideoDurationTime>
		</div>
		<div v-if="mediaItem.metadata?.preview_unavailable || playbackError(mediaItem)" class="pointer-events-none absolute inset-x-3 bottom-14 z-10 rounded-lg bg-black/75 px-3 py-2 text-center text-xs text-white" role="status">
			This video cannot play in this preview on your device. You can still publish it; try a supported MP4 video for an in-editor preview.
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
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
				const previewState = ref({});
				const previewKey = (mediaItem) => String(mediaItem.preview_key || mediaItem.client_uid || mediaItem.id || mediaItem.preview_url || mediaItem.source_url || 'video');
				const stateFor = (mediaItem) => previewState.value[previewKey(mediaItem)] || {};
				const setPreviewPlaying = (mediaItem, playing) => {
					previewState.value[previewKey(mediaItem)] = { ...stateFor(mediaItem), playing, error: playing ? false : stateFor(mediaItem).error };
				};
				const playPreview = async (mediaItem, event) => {
					const video = event.currentTarget.closest('.post-video-preview-frame')?.querySelector('video');
					if (!video) return;
					previewState.value[previewKey(mediaItem)] = { playing: false, error: false };
					try {
						await video.play();
						setPreviewPlaying(mediaItem, true);
					}
					catch {
						previewState.value[previewKey(mediaItem)] = { playing: false, error: true };
					}
				};
				return {
					canDelete: computed(() => {
						return props.canDelete;
					}),
					videoUrl: (mediaItem) => {
						return mediaItem.preview_url || mediaItem.source_url;
					},
					previewKey,
					isPreviewPlaying: (mediaItem) => Boolean(stateFor(mediaItem).playing),
					playbackError: (mediaItem) => Boolean(stateFor(mediaItem).error),
					setPreviewPlaying,
					playPreview,
					frameStyle: (mediaItem) => {
						return videoFrameAspectStyle(mediaItem.metadata, isVideoPortrait(mediaItem.metadata));
					},
					captureVideoMetadata: (mediaItem, event) => {
						const videoElement = event.currentTarget;
						const width = mediaItem.native_file_id && mediaItem.width ? mediaItem.width : videoElement.videoWidth;
						const height = mediaItem.native_file_id && mediaItem.height ? mediaItem.height : videoElement.videoHeight;
						const presentation = buildVideoPresentationMetadata(width, height, videoElement.duration);

						if(presentation.dimensions) {
							mediaItem.width = presentation.dimensions.width;
							mediaItem.height = presentation.dimensions.height;
						}
						if(presentation.duration_seconds) mediaItem.duration_seconds = presentation.duration_seconds;

						applyVideoPresentationMetadata(mediaItem, presentation);
						if(mediaItem.metadata?.preview_unavailable) {
							applyVideoPresentationMetadata(mediaItem, { preview_unavailable: false });
						}
					},
					captureVideoError: (mediaItem) => {
						applyVideoPresentationMetadata(mediaItem, { preview_unavailable: true });
						previewState.value[previewKey(mediaItem)] = { playing: false, error: true };
					}
			};
		},
		components: {
			MediaDeleteButton: MediaDeleteButton,
			VideoDurationTime: VideoDurationTime,
		}
	});
</script>
