<template>
	<div class="flex">
		<PublicationVideoProcessing
			v-if="MediaStatusUtils.isProcessing(mediaItem.status)"
		v-bind:mediaItem="mediaItem"></PublicationVideoProcessing>

		<div v-else class="overflow-hidden">
			<VideoPlayer
				v-bind:postId="mediaItem.mediaable_id"
				v-bind:mediaId="mediaItem.id"
				v-bind:thumbnailUrl="mediaItem.thumbnail_url"
				v-bind:duration="mediaItem.metadata.duration"
			v-bind:videoUrl="mediaItem.source_url"></VideoPlayer>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, defineAsyncComponent } from 'vue';
	import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';

	import VideoPlayer from '@M/components/players/video/VideoPlayer.vue';

	export default defineComponent({
		props: {
			postMedia: {
                type: Object,
                default: {}
            }
		},
		setup: function(props) {
			const mediaItem = computed(() => {
				return props.postMedia[0];
			});

			return {
				mediaItem: mediaItem,
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
