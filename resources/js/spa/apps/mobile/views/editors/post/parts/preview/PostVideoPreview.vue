<template>
	<div v-for="mediaItem in postMedia" v-bind:key="mediaItem.preview_key || mediaItem.id || mediaItem.preview_url || mediaItem.source_url" class="post-video-preview-shell relative flex min-h-0 w-full min-w-0 justify-center overflow-hidden">
		<img v-if="videoBackdropUrl(mediaItem)" v-bind:src="videoBackdropUrl(mediaItem)" class="post-video-preview-backdrop pointer-events-none absolute inset-0 size-full scale-110 object-cover opacity-45 blur-2xl" alt="" aria-hidden="true">
		<div
			ref="previewFrame"
			v-bind:class="[(mediaItem.deleted ? 'opacity-20' : '')]"
			v-bind:style="frameStyle(mediaItem)"
		class="post-video-preview-frame relative z-0 min-h-0 min-w-0 max-h-full max-w-full flex-none overflow-hidden">
		<div v-if="canDelete" class="absolute top-3 right-3 z-10 inline-block">
			<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
		</div>
		<div class="absolute inset-0">
			<video
				v-if="videoUrl(mediaItem)"
				v-on:loadedmetadata="captureVideoMetadata(mediaItem, $event)"
				v-on:loadeddata="captureVideoBackdrop(mediaItem, $event)"
				v-on:click="togglePreviewPlayback(mediaItem, $event)"
				v-on:play="setPreviewPlaying(mediaItem, true)"
				v-on:pause="setPreviewPlaying(mediaItem, false)"
				v-on:ended="setPreviewPlaying(mediaItem, false)"
				v-on:error="captureVideoError(mediaItem)"
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
		<div v-if="videoUrl(mediaItem)" class="pointer-events-none absolute left-1/2 top-3 z-10 -translate-x-1/2 rounded-full bg-black/60 px-2.5 py-1 text-xs font-semibold text-white" aria-live="polite">
			{{ playbackSpeedLabel(mediaItem) }}
		</div>
		<div v-if="videoUrl(mediaItem)" class="hidden pointer-events-none absolute inset-y-0 left-2 right-2 z-10 flex items-center justify-between">
			<button type="button" class="pointer-events-auto flex size-10 items-center justify-center rounded-full bg-black/60 text-lg font-semibold text-white shadow ring-1 ring-white/60 hover:bg-black/75 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Decrease video speed" v-bind:disabled="!canChangePlaybackSpeed(mediaItem, -1)" v-on:click="changePlaybackSpeed(mediaItem, -1, $event)">−</button>
			<button type="button" class="pointer-events-auto flex size-10 items-center justify-center rounded-full bg-black/60 text-lg font-semibold text-white shadow ring-1 ring-white/60 hover:bg-black/75 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Increase video speed" v-bind:disabled="!canChangePlaybackSpeed(mediaItem, 1)" v-on:click="changePlaybackSpeed(mediaItem, 1, $event)">+</button>
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
	</div>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
	import { applyVideoPresentationMetadata, buildVideoPresentationMetadata } from '@/kernel/services/media/video-metadata.js';
	import { fitMediaPreviewAspectRatio, fitMediaPreviewSize } from '@/kernel/services/media/media-preview-sizing.js';
	import { useMediaPreviewBounds } from '@/kernel/vue/composables/media-preview-bounds.js';

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
					const previewFrame = ref(null);
					const { bounds, refresh } = useMediaPreviewBounds(previewFrame, 350);
				const previewState = ref({});
				const videoDimensions = ref({});
				const videoBackdrops = ref({});
				const playbackRates = [0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3];
				const previewKey = (mediaItem) => String(mediaItem.preview_key || mediaItem.client_uid || mediaItem.id || mediaItem.preview_url || mediaItem.source_url || 'video');
				const stateFor = (mediaItem) => previewState.value[previewKey(mediaItem)] || {};
				const setPreviewPlaying = (mediaItem, playing) => {
					previewState.value[previewKey(mediaItem)] = { ...stateFor(mediaItem), playing, error: playing ? false : stateFor(mediaItem).error };
				};
				const videoFrom = (source) => {
					const element = source?.currentTarget || source;
					if (typeof element?.play === 'function' && 'paused' in element) return element;
					return element?.closest('.post-video-preview-frame')?.querySelector('video') || null;
				};
				const playbackSpeed = (mediaItem) => stateFor(mediaItem).speed || 1;
				const playbackSpeedIndex = (mediaItem) => playbackRates.reduce((best, rate, index) => Math.abs(rate - playbackSpeed(mediaItem)) < Math.abs(playbackRates[best] - playbackSpeed(mediaItem)) ? index : best, 0);
				const canChangePlaybackSpeed = (mediaItem, direction) => {
					const next = playbackSpeedIndex(mediaItem) + direction;
					return next >= 0 && next < playbackRates.length;
				};
				const playbackSpeedLabel = (mediaItem) => `${playbackSpeed(mediaItem)}×`;
				const frameStyle = (mediaItem) => {
					const dimensions = videoDimensions.value[previewKey(mediaItem)] || mediaItem;
					const size = fitMediaPreviewSize(Number(dimensions.width || 0), Number(dimensions.height || 0), bounds.value.width, bounds.value.height);
					if(size.width && size.height) return { width: `${size.width}px`, height: `${size.height}px` };
					const fallback = fitMediaPreviewAspectRatio(16 / 9, bounds.value.width, bounds.value.height);
					return fallback.width && fallback.height ? { width: `${fallback.width}px`, height: `${fallback.height}px` } : { width: '100%', aspectRatio: '16 / 9' };
				};
				const videoBackdropUrl = (mediaItem) => videoBackdrops.value[previewKey(mediaItem)] || mediaItem.thumbnail_url || null;
				const captureVideoBackdrop = (mediaItem, event) => {
					if(videoBackdropUrl(mediaItem)) return;
					const videoElement = event.currentTarget;
					if(! videoElement.videoWidth || ! videoElement.videoHeight || typeof document === 'undefined') return;
					const size = fitMediaPreviewSize(videoElement.videoWidth, videoElement.videoHeight, 320, 320);
					if(! size.width || ! size.height) return;
					try {
						const canvas = document.createElement('canvas');
						canvas.width = size.width;
						canvas.height = size.height;
						canvas.getContext('2d')?.drawImage(videoElement, 0, 0, size.width, size.height);
						videoBackdrops.value = { ...videoBackdrops.value, [previewKey(mediaItem)]: canvas.toDataURL('image/jpeg', 0.72) };
					}
					catch { /* A cross-origin video may not allow a canvas poster; keep the aspect-fit frame. */ }
				};
				const changePlaybackSpeed = (mediaItem, direction, event) => {
					event.stopPropagation();
					const video = videoFrom(event);
					if (!video || !canChangePlaybackSpeed(mediaItem, direction)) return;
					const nextSpeed = playbackRates[playbackSpeedIndex(mediaItem) + direction];
					try {
						video.playbackRate = nextSpeed;
						previewState.value[previewKey(mediaItem)] = { ...stateFor(mediaItem), speed: nextSpeed };
					}
					catch { /* Keep the current rate when the device does not support the requested speed. */ }
				};
				const playPreview = async (mediaItem, source) => {
					const video = videoFrom(source);
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
				const togglePreviewPlayback = (mediaItem, event) => {
					const video = event.currentTarget;
					if (video.paused) return playPreview(mediaItem, video);
					video.pause();
					setPreviewPlaying(mediaItem, false);
				};
				return {
					previewFrame,
					frameStyle,
					videoBackdropUrl,
					canDelete: computed(() => {
						return props.canDelete;
					}),
					videoUrl: (mediaItem) => {
						return mediaItem.preview_url || mediaItem.source_url;
					},
					playbackSpeedLabel,
					canChangePlaybackSpeed,
					changePlaybackSpeed,
					previewKey,
					isPreviewPlaying: (mediaItem) => Boolean(stateFor(mediaItem).playing),
					playbackError: (mediaItem) => Boolean(stateFor(mediaItem).error),
					setPreviewPlaying,
					playPreview,
					togglePreviewPlayback,
					captureVideoMetadata: (mediaItem, event) => {
						const videoElement = event.currentTarget;
						videoElement.playbackRate = playbackSpeed(mediaItem);
						const width = mediaItem.native_file_id && mediaItem.width ? mediaItem.width : videoElement.videoWidth;
						const height = mediaItem.native_file_id && mediaItem.height ? mediaItem.height : videoElement.videoHeight;
						const presentation = buildVideoPresentationMetadata(width, height, videoElement.duration);
						if(width > 0 && height > 0) {
							videoDimensions.value = {
								...videoDimensions.value,
								[previewKey(mediaItem)]: { width: presentation.dimensions?.width || width, height: presentation.dimensions?.height || height }
							};
						}

						if(presentation.dimensions) {
							mediaItem.width = presentation.dimensions.width;
							mediaItem.height = presentation.dimensions.height;
						}
						refresh();
						if(presentation.duration_seconds) mediaItem.duration_seconds = presentation.duration_seconds;

						applyVideoPresentationMetadata(mediaItem, presentation);
						if(mediaItem.metadata?.preview_unavailable) {
							applyVideoPresentationMetadata(mediaItem, { preview_unavailable: false });
						}
					},
					captureVideoBackdrop,
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
