<template>
	<div class="fixed inset-0 z-50 bg-black/50 overflow-y-auto" v-bind:class="[hide ? 'invisible' : '']">
		<slot></slot>
	</div>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted, ref } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	export default defineComponent({
		setup: function() {
			const hide = ref(false);

			const hideBackdrop = () => {
				hide.value = true;
			};

			const showBackdrop = () => {
				hide.value = false;
			};

			onMounted(() => {
				colibriEventBus.on('lightbox:opened', hideBackdrop);
				colibriEventBus.on('lightbox:closed', showBackdrop);
			});

			onUnmounted(() => {
				colibriEventBus.off('lightbox:opened', hideBackdrop);
				colibriEventBus.off('lightbox:closed', showBackdrop);
			});

			return {
				hide: hide
			};
		}
	});
</script>
