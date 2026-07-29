<template>
	<ActionSheet v-on:close="$emit('close')">
		<ReactionsPicker v-on:add="$emit('add', $event)"></ReactionsPicker>
	</ActionSheet>
</template>

<script>
	import { defineAsyncComponent } from 'vue';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
    import ReactionsPicker from '@M/components/reactions/ReactionsPicker.vue';

	export default {
		emits: ['close', 'add'],
		components: {
			ActionSheet: ActionSheet,
			ReactionsPicker: ReactionsPicker
		}
	}
</script>