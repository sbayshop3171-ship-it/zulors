<template>
	<div class="inline-block leading-none px-2.5 pt-1 pb-1.5 rounded-full bg-fill-tr">
		<span class="text-cap-l text-red-900">
			{{ labelText }}
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
			}
		}
	});
</script>