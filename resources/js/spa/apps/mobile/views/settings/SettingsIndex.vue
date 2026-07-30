<template>
	<div class="min-h-screen bg-bg-pr text-lab-pr">
		<div class="sticky top-0 z-50 bg-bg-pr">
			<ToastNotification></ToastNotification>
		</div>
		<RouterView></RouterView>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import ToastNotification from '@M/components/notifications/toast/ToastNotification.vue';

	export default defineComponent({
		components: {
			ToastNotification: ToastNotification
		}
	});
</script>
