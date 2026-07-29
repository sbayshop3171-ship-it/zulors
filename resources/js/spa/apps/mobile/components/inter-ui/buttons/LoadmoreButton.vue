<template>
	<div class="flex justify-center py-4">
		<PrimaryIconButton iconSize="6" iconName="plus-circle" iconType="line"></PrimaryIconButton>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		components: {
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>