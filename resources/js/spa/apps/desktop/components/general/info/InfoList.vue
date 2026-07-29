<template>
	<div class="block">
		<div class="mb-4" v-if="hasTitle">
			<h3 class="text-lab-sc font-medium text-par-s">
				{{ listTitle }}
			</h3>
		</div>

		<div class="flex flex-col gap-5">
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
			},
			hasTitle: {
				type: Boolean,
				default: true
			}
		}
	});
</script>