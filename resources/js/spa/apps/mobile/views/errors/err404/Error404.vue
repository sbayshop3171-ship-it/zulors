<template>
	<div class="px-4 py-12 text-center h-screen flex flex-col justify-center">
		<h4 class="text-title-3 text-lab-pr font-medium mb-1">
			{{$t('error.error_404.page_title')}}!
			<br>
			{{ $t('error.error_404.title') }}
		</h4>
		<p class="text-lab-pr3 text-par-s" v-html="$t('error.error_404.desc')"></p>
		<div class="mt-auto">
			<RouterLink v-bind:to="{ name: 'home_index' }">
				<PrimaryPillButton v-bind:buttonFluid="true" buttonSize="lg" v-bind:buttonText="$t('labels.return_home')"></PrimaryPillButton>
			</RouterLink>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		components: {
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>