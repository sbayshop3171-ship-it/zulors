<template>
	<div class="size-full p-4 flex items-end origin-bottom">
		<div class="rounded-md w-full popup-background-pr">
			<slot v-if="renderContent"></slot>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, onMounted, onBeforeMount } from 'vue';

	export default defineComponent({
		setup: function() {
			const renderContent = ref(false);

			onMounted(() => {
				setTimeout(() => {
					renderContent.value = true;
				}, 200);
			});

			onBeforeMount(() => {
				renderContent.value = false;
			});

			return {
				renderContent: renderContent
			};
		}
	});
</script>