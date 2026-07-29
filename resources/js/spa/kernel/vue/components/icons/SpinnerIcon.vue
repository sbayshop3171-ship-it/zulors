<template>
	<svg class="animate-spin size-full animate-duration-500" viewBox="0 0 32 32">
		<circle cx="16" cy="16" fill="none" r="14" v-bind:stroke-width="strokeWidth" v-bind:class="strokeColor" style="opacity: 0.2;"></circle>
		<circle cx="16" cy="16" fill="none" r="14" v-bind:stroke-width="strokeWidth" v-bind:class="strokeColor" style="stroke-dasharray: 80; stroke-dashoffset: 60;"></circle>
	</svg>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			strokeColor: {
				type: String,
				default: 'stroke-brand-900'
			},
			strokeWidth: {
				type: String,
				default: '2'
			}
		}
	});
</script>