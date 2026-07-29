<template>
	<div class="overflow-hidden border-t border-bord-card">
		<div v-if="postMedia.length >= 2" class="grid grid-cols-2 gap-0.5">
			<div class="aspect-square overflow-hidden" v-for="(imageItem, idx) in postMedia.slice(0, 2)" v-bind:key="imageItem.id">
				<ProgressiveImageLoader 
					v-bind:base64Image="imageItem.lqip_base64"
					v-bind:src="imageItem.source_url"
				class="block size-full object-cover" alt="Image"></ProgressiveImageLoader>
			</div>
		</div>
		<div v-else class="block">
			<ProgressiveImageLoader 
				v-bind:base64Image="postMedia[0]['lqip_base64']"
				v-bind:src="postMedia[0].source_url"
			class="block size-full object-cover" alt="Image"></ProgressiveImageLoader>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import ProgressiveImageLoader from '@/kernel/vue/components/media/image/ProgressiveImageLoader.vue';

	export default defineComponent({
		props: {
			postMedia: {
				type: Object,
				required: true
			}
		},
		components: {
			ProgressiveImageLoader: ProgressiveImageLoader
		}
	});
</script>