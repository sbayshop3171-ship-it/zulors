<template>
	<button class="inline-flex-center size-12 rounded-full bg-lab-pr/20 hover:bg-lab-pr/30 cursor-pointer" v-on:click="$emit('click')">
		<svg class="size-6" v-bind:class="[darkTheme ? 'text-lab-pr3' : 'text-bg-sc']" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
	</button>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		setup: function() {
			return {
				darkTheme: (embedder('theme') == 'dark')
			}
		}
	});
</script>