<template>
	<button v-bind:disabled="disabled" type="button" class="inline-flex items-center justify-center size-icon-small bg-lab-sc/70 rounded-full text-bg-sc cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
		<span class="size-icon-x-small">
			<SvgIcon name="x"></SvgIcon>
		</span>
	</button>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			disabled: {
				type: Boolean,
				default: false
			}
		}
	});
</script>