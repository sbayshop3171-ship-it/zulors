<template>
	<div
		v-bind:class="frameWidthClass"
		v-bind:style="frameStyle"
	class="post-video-preview-frame bg-black border border-bord-card flex justify-center rounded-2xl overflow-hidden relative">
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
				v-on:play="setPreviewPlaying(true)"
				v-on:pause="setPreviewPlaying(false)"
				v-on:ended="setPreviewPlaying(false)"
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
		<button
			v-if="videoUrl && !isPreviewPlaying"
			type="button"
			class="absolute left-1/2 top-1/2 z-10 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 text-white shadow-lg ring-1 ring-white/70 transition hover:bg-black/75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
			v-bind:aria-label="playbackError ? 'Retry video preview' : 'Play video preview'"
			v-on:click="playPreview">
			<svg v-if="!playbackError" viewBox="0 0 24 24" class="ml-1 size-7 fill-current" aria-hidden="true"><path d="M7 4.75v14.5L19 12 7 4.75Z"></path></svg>
			<svg v-else viewBox="0 0 24 24" class="size-7 fill-current" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-2h2v2Zm0-4h-2V7h2v6Z"></path></svg>
		</button>
		<div v-if="mediaItem.metadata?.preview_unavailable || playbackError" class="pointer-events-none absolute inset-x-3 bottom-14 z-10 rounded-lg bg-black/75 px-3 py-2 text-center text-xs text-white" role="status">
			This video cannot play in this preview on your device. You can still publish it; try a supported MP4 video for an in-editor preview.
		</div>

		<div v-if="mediaDuration" class="pointer-events-none absolute left-4 top-4 rounded-full bg-black/60 px-2.5 py-1">
			<VideoDurationTime v-bind:videoDuration="mediaDuration"></VideoDurationTime>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
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
			const isPreviewPlaying = ref(false);
			const playbackError = ref(false);
			const setPreviewPlaying = (playing) => {
				isPreviewPlaying.value = playing;
				if (playing) playbackError.value = false;
			};
			const playPreview = async (event) => {
				const video = event.currentTarget.closest('.post-video-preview-frame')?.querySelector('video');
				if (!video) return;
				playbackError.value = false;
				try {
					await video.play();
					setPreviewPlaying(true);
				}
				catch {
					playbackError.value = true;
				}
			};
			return {
				isPreviewPlaying,
				playbackError,
				setPreviewPlaying,
				playPreview,
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
					playbackError.value = true;
					isPreviewPlaying.value = false;
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
