<template>
	<div class="p-4 flex items-center leading-none">
		<div class="shrink-0 w-12">
			<PrimaryIconButton 
				v-on:click="$emit('close')"
				buttonColor="text-lab-pr"
				iconSize="icon-medium"
			iconName="chevron-left"></PrimaryIconButton>
		</div>
		<div class="flex-1 overflow-hidden">
			<h4 class="text-title-3 font-medium text-lab-pr text-center truncate">
				{{ title }}
			</h4>
		</div>
		<div class="shrink-0 w-12 flex justify-end">
			<slot></slot>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		emits: ['close'],
		props: {
			title: {
				type: String,
				required: true
			}
		},
		components: {
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>