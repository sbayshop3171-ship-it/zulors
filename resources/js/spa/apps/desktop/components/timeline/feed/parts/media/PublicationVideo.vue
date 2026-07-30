<template>
	<div class="flex">
		<PublicationVideoProcessing
			v-if="!canPlayImmediately"
			v-bind:mediaItem="mediaItem"
			v-bind:isPortrait="isPortrait"></PublicationVideoProcessing>
		<div v-else
			v-bind:class="[isPortrait ? 'w-72' : 'w-full']"
		class="bg-fill-pr block border border-bord-card rounded-xl overflow-hidden">
			<VideoPlayer
				v-bind:postId="mediaItem.mediaable_id"
				v-bind:mediaId="mediaItem.id"
				v-bind:thumbnailUrl="mediaItem.thumbnail_url"
				v-bind:duration="mediaItem.metadata.duration"
				v-bind:isPortrait="isPortrait"
			v-bind:videoUrl="mediaItem.preview_url || mediaItem.source_url"></VideoPlayer>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, defineAsyncComponent, onMounted, onUnmounted } from 'vue';
	import { useTimelineStore } from '@D/store/timeline/timeline.store.js';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import VideoPlayer from '@D/components/players/video/VideoPlayer.vue';

	export default defineComponent({
		props: {
			postMedia: {
                type: Object,
                default: {}
            }
		},
		setup: function(props) {
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

			return {
				mediaItem: mediaItem,
				canPlayImmediately: canPlayImmediately,
				MediaStatusUtils: MediaStatusUtils,
				isPortrait: computed(() => {
					return Boolean(mediaItem.value.metadata?.is_portrait);
				})
			};
		},
		components: {
			VideoPlayer: VideoPlayer,
			PublicationVideoProcessing: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/media/state/PublicationVideoProcessing.vue');
            })
		}
	});
</script>
