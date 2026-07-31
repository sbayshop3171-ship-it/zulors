<template>
	<div class="overflow-hidden border-t border-bord-card">
		<div class="bg-black flex justify-center rounded-xs overflow-hidden relative" v-bind:style="frameStyle(videoItem)" v-for="videoItem in postMedia" v-bind:key="videoItem.id">
			<div class="size-full">
				<img v-bind:src="videoItem.thumbnail_url" class="block size-full object-cover" alt="Image">
			</div>
			<div class="absolute bottom-4 right-4 inline-block">
				<VideoDurationTime v-bind:videoDuration="videoItem.metadata.duration"></VideoDurationTime>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import { isVideoPortrait, videoFrameAspectStyle } from '@/kernel/services/media/video-metadata.js';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';

	export default defineComponent({
		props: {
			postMedia: {
				type: Object,
				required: true
			}
		},
		setup: function() {
			return {
				frameStyle: (videoItem) => {
					return videoFrameAspectStyle(videoItem.metadata, isVideoPortrait(videoItem.metadata));
				}
			};
		},
		components: {
			VideoDurationTime: VideoDurationTime
		}
	});
</script>
