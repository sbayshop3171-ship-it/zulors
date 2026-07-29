<template>
	<div class="fixed inset-0 z-[600] bg-black/20 backdrop-blur-xs overflow-y-auto">
		<slot></slot>
	</div>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted} from 'vue';

	export default defineComponent({
		setup: function() {
			onMounted(() => {
				freezeScroll();
			});

			onUnmounted(() => {
				unfreezeScroll();
			});
		}
	});
</script>