<template>
	<div class="flex justify-between border-b border-fill-sc px-4 py-3">
		<span class="text-par-s font-medium text-lab-pr truncate">
			{{ title }}
		</span>
		<MarginalTextButton v-bind:buttonText="buttonText" v-on:click="$emit('cancel')"></MarginalTextButton>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import MarginalTextButton from '@D/components/inter-ui/buttons/MarginalTextButton.vue';

	export default defineComponent({
		props: {
			title: {
				type: String,
				required: true
			},
			buttonText: {
				type: String,
				required: true
			}
		},
		emits: ['cancel'],
		components: {
			MarginalTextButton: MarginalTextButton
		}
	});
</script>