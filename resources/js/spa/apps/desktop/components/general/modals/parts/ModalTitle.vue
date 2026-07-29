<template>
	<h4 class="text-par-l font-semibold flex-1 text-lab-pr2 truncate">
		{{ title }}
	</h4>
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