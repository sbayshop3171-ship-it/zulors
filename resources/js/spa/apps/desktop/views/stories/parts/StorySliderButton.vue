<template>
	<button class="inline-block size-6 rounded-full bg-white/70 hover:bg-white/90 shadow-md pl-0.5 transition-all">
        <SvgIcon v-bind:name="directionBack ? 'chevron-left' : 'chevron-right'" classes="size-5 text-black/70"></SvgIcon>
    </button>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			directionBack: false
		}
	});
</script>