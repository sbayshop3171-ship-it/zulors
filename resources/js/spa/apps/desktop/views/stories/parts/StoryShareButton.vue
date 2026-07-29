<template>
	<PrimaryIconButton 
		v-on:click="shareStory" 
		iconName="share-06" 
		iconType="line"
		hoverText="hover:text-white hover:opacity-100"
	buttonColor="text-white opacity-70"></PrimaryIconButton>
</template>

<script>
	import { defineComponent } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		setup: function() {
			return {
				shareStory: () => {
					colibriEventBus.emit('story:share');
				}
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>