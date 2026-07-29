<template>
	<div class="flex">
		<PublicationVideoProcessing
			v-if="!canPlayImmediately"
			v-bind:mediaItem="mediaItem"></PublicationVideoProcessing>

		<div v-else class="overflow-hidden">
			<VideoPlayer
				v-bind:postId="mediaItem.mediaable_id"
				v-bind:mediaId="mediaItem.id"
				v-bind:thumbnailUrl="mediaItem.thumbnail_url"
				v-bind:duration="mediaItem.metadata.duration"
			v-bind:videoUrl="mediaItem.preview_url || mediaItem.source_url"></VideoPlayer>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, defineAsyncComponent, onMounted, onUnmounted } from 'vue';
	import { useTimelineStore } from '@M/store/timeline/timeline.store.js';
	import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import VideoPlayer from '@M/components/players/video/VideoPlayer.vue';

	export default defineComponent({
		props: {
			postMedia: {
                type: Object,
                default: {}
            }
		},
		setup: function(props) {
			const timelineStore = useTimelineStore();
			const mediaItem = computed(() => {
				return props.postMedia[0];
			});
			const canPlayImmediately = computed(() => {
				const item = mediaItem.value;

				return !MediaStatusUtils.isProcessing(item.status)
					|| (item.metadata?.provider === 'r2_temp'
						&& item.metadata?.upload_state === 'uploaded'
						&& Boolean(item.preview_url));
			});
			const syncProcessedMedia = (event) => {
				if(event.data?.mediaable_id == mediaItem.value.mediaable_id && event.data?.id == mediaItem.value.id) {
					timelineStore.setPostMedia(event.data);
				}
			};

			onMounted(() => {
				if(window.ColibriBRD) {
					ColibriBRD.channel(BRD.getChannel('PUBLIC_TIMELINE')).listen(BRD.getEvent('TIMELINE_MEDIA_PROCESSED'), syncProcessedMedia);
				}
			});

			onUnmounted(() => {
				if(window.ColibriBRD) {
					ColibriBRD.channel(BRD.getChannel('PUBLIC_TIMELINE')).stopListening(BRD.getEvent('TIMELINE_MEDIA_PROCESSED'), syncProcessedMedia);
				}
			});

			return {
				mediaItem: mediaItem,
				canPlayImmediately: canPlayImmediately,
				MediaStatusUtils: MediaStatusUtils
			};
		},
		components: {
			VideoPlayer: VideoPlayer,
			PublicationVideoProcessing: defineAsyncComponent(() => {
                return import('@M/components/timeline/feed/parts/media/state/PublicationVideoProcessing.vue');
            })
		}
	});
</script>
