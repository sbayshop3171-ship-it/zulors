<template>
	<a v-bind:href="link" target="_blank" class="block size-10 bg-fill-pr rounded-full overflow-hidden smoothing">
		<img v-bind:src="iconUrl" alt="Social Media" class="size-full">
	</a>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			link: {
				type: String,
				default: ''
			},
			iconUrl: {
				type: String,
				default: ''
			}
		}
	});
</script>