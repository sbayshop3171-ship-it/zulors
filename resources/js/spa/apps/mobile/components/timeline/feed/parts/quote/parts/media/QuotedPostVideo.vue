<template>
	<div class="overflow-hidden border-t border-bord-card">
		<div class="bg-black flex justify-center relative aspect-video" v-for="videoItem in postMedia" v-bind:key="videoItem.id">
			<div class="h-full">
				<img v-bind:src="videoItem.thumbnail_url" class="block h-full" alt="Image">
			</div>
			<div class="absolute bottom-4 right-4 inline-block">
				<VideoDurationTime v-bind:videoDuration="videoItem.metadata.duration"></VideoDurationTime>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';

	export default defineComponent({
		props: {
			postMedia: {
				type: Object,
				required: true
			}
		},
		components: {
			VideoDurationTime: VideoDurationTime
		}
	});
</script>