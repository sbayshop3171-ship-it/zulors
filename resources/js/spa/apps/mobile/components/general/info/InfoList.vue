<template>
	<div class="block">
		<div class="mb-2 px-4">
			<h3 class="text-lab-pr font-semibold text-par-m">
				{{ listTitle }}
			</h3>
		</div>

		<div class="block">
			<slot></slot>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			listTitle: {
				type: String,
				default: ''
			}
		}
	});
</script>