<template>
	<ChatCallout variant="danger" iconName="signal-03" iconType="solid" v-bind:title="$t('chat.alerts.ws_connection.title')" v-bind:description="$t('chat.alerts.ws_connection.desc')"></ChatCallout>
</template>

<script>
	import { defineComponent } from 'vue';

	import ChatCallout from '@D/views/messenger/parts/ChatCallout.vue';

	export default defineComponent({
		components: {
			ChatCallout: ChatCallout
		}
	});
</script>