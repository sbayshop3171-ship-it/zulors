<template>
	<div class="block py-4 px-3">
		<h3 class="text-par-n font-semibold text-lab-pr2 text-center">
			{{ title}}
		</h3>
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