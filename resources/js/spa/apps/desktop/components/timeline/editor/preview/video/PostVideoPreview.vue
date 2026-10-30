<template>
	<div class="post-video-preview-shell relative flex min-h-0 w-full min-w-0 justify-center overflow-hidden">
		<img v-if="videoBackdropUrl" v-bind:src="videoBackdropUrl" class="post-video-preview-backdrop pointer-events-none absolute inset-0 size-full scale-110 object-cover opacity-45 blur-2xl" alt="" aria-hidden="true">
		<div
			ref="previewFrame"
			v-bind:style="frameStyle"
		class="post-video-preview-frame relative z-0 min-h-0 min-w-0 max-h-full max-w-full flex-none overflow-hidden">
		<template v-if="mediaItem.deleted">
			<MediaBlurOverlay></MediaBlurOverlay>
		</template>

		<template v-else>
			<div v-if="canDelete" class="absolute top-4 right-4 z-10 inline-block">
				<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
		</template>

		<div class="absolute inset-0">
			<video
				v-if="videoUrl"
				v-on:loadedmetadata="captureVideoMetadata"
				v-on:loadeddata="captureVideoBackdrop"
				v-on:click="togglePreviewPlayback"
				v-on:play="setPreviewPlaying(true)"
				v-on:pause="setPreviewPlaying(false)"
				v-on:ended="setPreviewPlaying(false)"
				v-on:error="captureVideoError"
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
		<div v-if="videoUrl" class="pointer-events-none absolute left-1/2 top-3 z-10 -translate-x-1/2 rounded-full bg-black/60 px-2.5 py-1 text-xs font-semibold text-white" aria-live="polite">
			{{ playbackSpeedLabel }}
		</div>
		<div v-if="videoUrl" class="hidden pointer-events-none absolute inset-y-0 left-2 right-2 z-10 flex items-center justify-between">
			<button type="button" class="pointer-events-auto flex size-10 items-center justify-center rounded-full bg-black/60 text-lg font-semibold text-white shadow ring-1 ring-white/60 hover:bg-black/75 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Decrease video speed" v-bind:disabled="!canChangePlaybackSpeed(-1)" v-on:click="changePlaybackSpeed(-1, $event)">−</button>
			<button type="button" class="pointer-events-auto flex size-10 items-center justify-center rounded-full bg-black/60 text-lg font-semibold text-white shadow ring-1 ring-white/60 hover:bg-black/75 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Increase video speed" v-bind:disabled="!canChangePlaybackSpeed(1)" v-on:click="changePlaybackSpeed(1, $event)">+</button>
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
	</div>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
	import { applyVideoPresentationMetadata, buildVideoPresentationMetadata } from '@/kernel/services/media/video-metadata.js';
	import { fitMediaPreviewAspectRatio, fitMediaPreviewSize } from '@/kernel/services/media/media-preview-sizing.js';
	import { useMediaPreviewBounds } from '@/kernel/vue/composables/media-preview-bounds.js';

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
			const previewFrame = ref(null);
			const { bounds, refresh } = useMediaPreviewBounds(previewFrame, 320);
			const videoDimensions = ref(null);
			const videoBackdropUrl = ref(props.mediaItem.thumbnail_url || null);
			const frameStyle = computed(() => {
				const dimensions = videoDimensions.value || props.mediaItem;
				const size = fitMediaPreviewSize(Number(dimensions.width || 0), Number(dimensions.height || 0), bounds.value.width, bounds.value.height);
				if(size.width && size.height) return { width: `${size.width}px`, height: `${size.height}px` };
				const fallback = fitMediaPreviewAspectRatio(16 / 9, bounds.value.width, bounds.value.height);
				return fallback.width && fallback.height ? { width: `${fallback.width}px`, height: `${fallback.height}px` } : { width: '100%', aspectRatio: '16 / 9' };
			});
			const playbackSpeed = ref(1);
			const playbackRates = [0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3];
			const playbackSpeedIndex = () => playbackRates.reduce((best, rate, index) => Math.abs(rate - playbackSpeed.value) < Math.abs(playbackRates[best] - playbackSpeed.value) ? index : best, 0);
			const canChangePlaybackSpeed = (direction) => {
				const next = playbackSpeedIndex() + direction;
				return next >= 0 && next < playbackRates.length;
			};
			const changePlaybackSpeed = (direction, event) => {
				event.stopPropagation();
				const video = event.currentTarget.closest('.post-video-preview-frame')?.querySelector('video');
				if (!video || !canChangePlaybackSpeed(direction)) return;
				const nextSpeed = playbackRates[playbackSpeedIndex() + direction];
				try {
					video.playbackRate = nextSpeed;
					playbackSpeed.value = nextSpeed;
				}
				catch { /* Keep the current rate when the device does not support the requested speed. */ }
			};
			const setPreviewPlaying = (playing) => {
				isPreviewPlaying.value = playing;
				if (playing) playbackError.value = false;
			};
			const videoFrom = (source) => {
				const element = source?.currentTarget || source;
				if (typeof element?.play === 'function' && 'paused' in element) return element;
				return element?.closest('.post-video-preview-frame')?.querySelector('video') || null;
			};
			const playPreview = async (source) => {
				const video = videoFrom(source);
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
			const togglePreviewPlayback = (event) => {
				const video = event.currentTarget;
				if (video.paused) return playPreview(video);
				video.pause();
				setPreviewPlaying(false);
			};
			const captureVideoBackdrop = (event) => {
				if(videoBackdropUrl.value) return;
				const videoElement = event.currentTarget;
				if(! videoElement.videoWidth || ! videoElement.videoHeight || typeof document === 'undefined') return;
				const size = fitMediaPreviewSize(videoElement.videoWidth, videoElement.videoHeight, 320, 320);
				if(! size.width || ! size.height) return;
				try {
					const canvas = document.createElement('canvas');
					canvas.width = size.width;
					canvas.height = size.height;
					canvas.getContext('2d')?.drawImage(videoElement, 0, 0, size.width, size.height);
					videoBackdropUrl.value = canvas.toDataURL('image/jpeg', 0.72);
				}
				catch { /* A cross-origin video may not allow a canvas poster; keep the aspect-fit frame. */ }
			};
			return {
				previewFrame,
				frameStyle,
				videoBackdropUrl,
				isPreviewPlaying,
				playbackError,
				playbackSpeedLabel: computed(() => `${playbackSpeed.value}×`),
				canChangePlaybackSpeed,
				changePlaybackSpeed,
				setPreviewPlaying,
				playPreview,
				togglePreviewPlayback,
				videoUrl: computed(() => {
					return props.mediaItem.preview_url || props.mediaItem.source_url;
				}),
				mediaDuration: computed(() => {
					return props.mediaItem.metadata?.duration || null;
				}),
				captureVideoMetadata: (event) => {
					const videoElement = event.currentTarget;
					videoElement.playbackRate = playbackSpeed.value;
					const width = props.mediaItem.native_file_id && props.mediaItem.width ? props.mediaItem.width : videoElement.videoWidth;
					const height = props.mediaItem.native_file_id && props.mediaItem.height ? props.mediaItem.height : videoElement.videoHeight;
					const presentation = buildVideoPresentationMetadata(width, height, videoElement.duration);
					if(width > 0 && height > 0) {
						videoDimensions.value = {
							width: presentation.dimensions?.width || width,
							height: presentation.dimensions?.height || height
						};
					}

					if(presentation.dimensions) {
						props.mediaItem.width = presentation.dimensions.width;
						props.mediaItem.height = presentation.dimensions.height;
					}
					refresh();
					if(presentation.duration_seconds) props.mediaItem.duration_seconds = presentation.duration_seconds;

					applyVideoPresentationMetadata(props.mediaItem, presentation);
					if(props.mediaItem.metadata?.preview_unavailable) {
						applyVideoPresentationMetadata(props.mediaItem, { preview_unavailable: false });
					}
				},
				captureVideoBackdrop,
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
