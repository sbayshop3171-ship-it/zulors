<template>
	<div class="block">
		<p class="text-par-m text-lab-pr" v-html="$mdInline(commentContent)"></p>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			commentContent: {
				type: String,
				required: true
			}
		}
	});
</script>