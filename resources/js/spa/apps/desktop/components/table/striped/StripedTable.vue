<template>
	<div class="block">
		<h4 class="text-lab-pr text-par-l font-medium mb-2">
			{{ title }}
		</h4>

		<slot></slot>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			title: {
				type: String,
				required: true
			}
		}
	});
</script>