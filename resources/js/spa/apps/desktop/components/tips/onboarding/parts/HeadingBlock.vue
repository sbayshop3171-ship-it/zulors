<template>
	<div class="select-none">
		<div class="px-16">
			<h5 class="text-par-m font-medium text-lab-pr2 text-center">
				{{ title }}
			</h5>
			<p class="text-par-s text-center text-lab-pr2">
				{{ caption }}
			</p>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			title: {
				type: String,
				required: true
			},
			caption: {
				type: String,
				required: true
			}
		}
	});
</script>