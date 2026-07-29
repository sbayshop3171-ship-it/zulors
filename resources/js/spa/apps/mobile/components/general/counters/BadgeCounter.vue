<template>
	<span v-bind:class="[color]" class="font-mono inline-block h-[17px] text-[12px] !text-white rounded-full px-1.5 leading-[17px] align-middle">
		{{ count }}
	</span>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			count: {
				type: [Number, String],
				default: 0
			},
			color: {
				type: String,
				default: 'bg-red-900'
			}
		}
	});
</script>
