<template>
	<ToastNotification></ToastNotification>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('chat.group_info')">
	</Toolbar>
	<Border></Border>
</template>

<script>
	import { defineComponent } from 'vue';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import ToastNotification from '@M/components/notifications/toast/ToastNotification.vue';

	export default defineComponent({
		components: {
			Toolbar: Toolbar,
			ToastNotification: ToastNotification
		}
	});
</script>