<template>
	<ActionSheet v-on:close="$emit('hide')">
		<div class="px-4 max-h-80 overflow-y-auto pb-4">
			<p class="text-par-m text-lab-pr markdown-text" v-html="$mdInline(storyContent)"></p>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, inject, computed } from 'vue';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';

	export default defineComponent({
		emits: ['hide'],
		setup: function() {
			const playerState = inject('playerState');
			
			return {
				storyContent: computed(() => {
					return playerState.frameData.content;
				})
			};
		},
		components: {
			ActionSheet: ActionSheet
		}
	});
</script>