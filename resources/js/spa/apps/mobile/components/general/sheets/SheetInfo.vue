<template>
	<div class="px-4 text-center">
		<div class="pb-32 pt-6">
			<div class="flex justify-center mb-4">
				<img v-bind:src="$embedder('assets.logos.url')" alt="Info" class="w-10">
			</div>
			<h4 class="text-par-l font-bold mb-1 text-lab-pr2">{{ sheetTitle }}</h4>
			<p class="text-par-m text-lab-sc">{{ sheetDesc }}</p>
		</div>
		<PrimaryPillButton v-on:click="$emit('close')" v-bind:buttonFluid="true" v-bind:buttonText="$t('labels.close')" buttonSize="lg"></PrimaryPillButton>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		emits: ['close'],
		props: {
			sheetTitle: {
				type: String,
				required: true
			},
			sheetDesc: {
				type: String,
				required: true
			}
		},
		components: {
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>