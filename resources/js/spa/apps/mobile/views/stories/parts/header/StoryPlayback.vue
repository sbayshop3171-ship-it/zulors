<template>
	<div class="flex gap-1 mb-3 overflow-hidden">
		<div v-for="(frameItem, key) in storyData.relations.frames" class="h-0.5 bg-white/60 flex-1 min-w-1" v-bind:key="key">
			<div v-if="frameItem.playable.played" class="h-full bg-white w-full"></div>
			<div v-else-if="frameItem.playable.playing" class="h-full bg-white" v-bind:style="{ width: `${frameItem.playable.progress}%` }"></div>
			<div v-else class="h-full bg-white w-0"></div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			storyData: {
				type: Object,
				required: true
			}	
		}
	});
</script>