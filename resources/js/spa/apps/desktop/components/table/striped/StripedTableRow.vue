<template>
	<div class="flex justify-between py-2 px-4 even:bg-fill-qt">
		<span class="text-par-s font-semibold text-lab-pr2">
			{{ labelText }}
		</span>
		<span class="text-par-s font-semibold text-lab-sc">
			{{ valueText }}
		</span>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			labelText: {
				type: String,
				required: true
			},
			valueText: {
				type: String,
				required: true
			}
		}
	});
</script>