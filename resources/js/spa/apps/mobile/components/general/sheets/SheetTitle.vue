<template>
	<h5 class="text-par-l font-semibold text-lab-pr">
		{{ title }}
	</h5>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	export default defineComponent({
		props: {
			title: {
				type: [String, Number],
				required: true
			}
		}
	});
</script>