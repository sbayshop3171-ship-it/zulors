<template>
	<div class="size-full relative">
		<img class="size-full object-cover" v-bind:src="lqipBase64" alt="Base64 LQIP">
		<div class="shadow-xs absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 size-9 bg-white/50 backdrop-blur-2xl rounded-full inline-flex-center justify-center z-10">
			<PrimarySpinAnimation></PrimarySpinAnimation>
		</div>
	</div>
</template>

<script>
    import PrimarySpinAnimation from '@D/components/general/animations/PrimarySpinAnimation.vue';
	import { defineComponent } from 'vue';
    
    export default defineComponent({
		props: {
			lqipBase64: {
				type: String,
				required: true
			}
		},
        components: {
            PrimarySpinAnimation: PrimarySpinAnimation
        }
    });
</script>