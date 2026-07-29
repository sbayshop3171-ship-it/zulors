<template>
	<div class="mb-8 px-4">
		<h6 class="text-par-s text-center" v-bind:class="[color]" v-html="text">
		</h6>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			text: {
				type: String,
				default: ''
			},
			color: {
				type: String,
				default: 'text-lab-sc'
			}
		}
	});
</script>