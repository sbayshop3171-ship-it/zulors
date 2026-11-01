<template>
	<div class="flex">
		<PublicationVideoProcessing
			v-if="!canPlayImmediately"
			v-bind:mediaItem="mediaItem"
			v-bind:isPortrait="isPortrait"
			v-bind:aspectRatio="mediaAspectRatio"></PublicationVideoProcessing>
		<div v-else
			ref="mediaViewportRef"
			v-bind:style="mediaFrameStyle"
			v-bind:class="mediaFrameClass"
		class="relative bg-fill-pr block border border-bord-card rounded-xl overflow-hidden">
			<img v-if="mediaItem.thumbnail_url" v-bind:src="mediaItem.thumbnail_url" alt="" aria-hidden="true" loading="lazy" decoding="async" class="pointer-events-none absolute inset-0 size-full object-cover">
			<VideoPlayer
				v-if="isNearViewport"
				v-bind:postId="mediaItem.mediaable_id"
				v-bind:mediaId="mediaItem.id"
				v-bind:mediaItem="mediaItem"
				v-bind:thumbnailUrl="mediaItem.thumbnail_url"
			v-bind:duration="mediaItem.metadata.duration"
			v-bind:isPortrait="isPortrait"
			v-bind:metadata="mediaItem.metadata"
			v-bind:aspectRatio="mediaAspectRatio"
			v-bind:sponsoredFeed="sponsoredFeed"
			v-bind:surfaceClickAction="openReelsOnTap ? 'emit' : 'toggle'"
			v-on:surface-click="$emit('open-reels')"
			></VideoPlayer>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, defineAsyncComponent, onMounted, onUnmounted } from 'vue';
	import { useNearViewport } from '@/kernel/vue/composables/near-viewport/index.js';
	import { useTimelineStore } from '@D/store/timeline/timeline.store.js';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { isVideoPortrait, normalizeVideoAspectRatio } from '@/kernel/services/media/video-metadata.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	export default defineComponent({
		emits: ['open-reels'],
		props: {
			postMedia: {
                type: Object,
                default: {}
            },
			sponsoredFeed: { type: Boolean, default: false },
			openReelsOnTap: {
				type: Boolean,
				default: false
            }
		},
		setup: function(props) {
			const { targetRef: mediaViewportRef, isNearViewport } = useNearViewport({ rootMargin: '500px 0px' });
			const timelineStore = useTimelineStore();
			const authStore = useAuthStore();
			const mediaItem = computed(() => {
				return props.postMedia[0] || {};
			});
			const canPlayImmediately = computed(() => {
				const item = mediaItem.value;

				if(! item?.id || MediaStatusUtils.isFailed(item.status)) {
					return false;
				}

				return MediaStatusUtils.isProcessed(item.status)
					|| (['r2_temp', 'r2_direct'].includes(item.metadata?.provider)
						&& item.metadata?.upload_state === 'uploaded'
						&& Boolean(item.preview_url));
			});

			const replaceCurrentMedia = (mediaData) => {
				if(! mediaData?.id || mediaData.mediaable_id != mediaItem.value.mediaable_id || mediaData.id != mediaItem.value.id) {
					return;
				}

				const mediaIndex = Array.isArray(props.postMedia)
					? props.postMedia.findIndex((item) => item.id == mediaData.id)
					: -1;

				if(mediaIndex !== -1) {
					props.postMedia.splice(mediaIndex, 1, mediaData);
				}

				else if(mediaItem.value?.id) {
					Object.assign(mediaItem.value, mediaData);
				}

				timelineStore.setPostMedia(mediaData);
			};

			const syncMediaEvent = (event) => {
				replaceCurrentMedia(event?.data || event);
			};

			onMounted(() => {
				if(window.ColibriBRD) {
					ColibriBRD.channel(BRD.getChannel('PUBLIC_TIMELINE')).listen(BRD.getEvent('TIMELINE_MEDIA_PROCESSED'), syncMediaEvent);
					ColibriBRD.private(BRD.getChannel('AUTH_USER', [authStore.userData.id])).listen(BRD.getEvent('TIMELINE_MEDIA_UPDATED'), syncMediaEvent);
				}

				colibriEventBus.on('timeline:media-updated', syncMediaEvent);
			});

			onUnmounted(() => {
				if(window.ColibriBRD) {
					ColibriBRD.channel(BRD.getChannel('PUBLIC_TIMELINE')).stopListening(BRD.getEvent('TIMELINE_MEDIA_PROCESSED'), syncMediaEvent);
					ColibriBRD.private(BRD.getChannel('AUTH_USER', [authStore.userData.id])).stopListening(BRD.getEvent('TIMELINE_MEDIA_UPDATED'), syncMediaEvent);
				}

				colibriEventBus.off('timeline:media-updated', syncMediaEvent);
			});

			const isPortrait = computed(() => {
				return isVideoPortrait(mediaItem.value.metadata, Boolean(mediaItem.value.metadata?.is_portrait));
			});

			const mediaAspectRatio = computed(() => {
				return normalizeVideoAspectRatio(mediaItem.value.metadata, isPortrait.value);
			});

			const mediaFrameClass = computed(() => {
				return 'w-full';
			});

			const mediaFrameStyle = computed(() => ({
				aspectRatio: props.sponsoredFeed ? '1 / 1' : String(mediaAspectRatio.value)
			}));

			return {
				mediaItem: mediaItem,
				mediaViewportRef: mediaViewportRef,
				isNearViewport: isNearViewport,
				canPlayImmediately: canPlayImmediately,
				MediaStatusUtils: MediaStatusUtils,
				isPortrait: isPortrait,
				mediaAspectRatio: mediaAspectRatio,
				mediaFrameClass: mediaFrameClass,
				mediaFrameStyle: mediaFrameStyle
			};
		},
		components: {
			VideoPlayer: defineAsyncComponent(() => import('@D/components/players/video/VideoPlayer.vue')),
			PublicationVideoProcessing: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/media/state/PublicationVideoProcessing.vue');
            })
		}
	});
</script>
