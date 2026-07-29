<template>
	<div class="flex">
		<PublicationVideoProcessing
			v-if="MediaStatusUtils.isProcessing(mediaItem.status)"
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
			v-bind:videoUrl="mediaItem.source_url"></VideoPlayer>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, defineAsyncComponent } from 'vue';
	import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';

	import VideoPlayer from '@D/components/players/video/VideoPlayer.vue';

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
				MediaStatusUtils: MediaStatusUtils,
				isPortrait: computed(() => {
					return mediaItem.value.metadata.is_portrait;
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
