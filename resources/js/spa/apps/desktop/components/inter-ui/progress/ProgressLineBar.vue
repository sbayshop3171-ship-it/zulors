<template>
	<div class="bg-fill-qt flex">
		<div class="bg-brand-900 smoothing min-w-3" v-bind:class="[height]" v-bind:style="{ width: progress + '%' }"></div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			progress: {
				type: Number,
				default: 0
			},
			height: {
				type: String,
				default: 'h-0.5'
			}
		}
	});
</script>