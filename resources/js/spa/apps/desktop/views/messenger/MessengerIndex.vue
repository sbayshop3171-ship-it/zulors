<template>
    <div class="fixed inset-0 flex w-messenger-sidebar">
		<MessengerActionBar></MessengerActionBar>
        <MessengerArchive v-if="$route.meta.archive"></MessengerArchive>
        <MessengerNavbar v-else></MessengerNavbar>
	</div>
	<div class="fixed inset-0 ml-messenger-sidebar">
        <RouterView v-slot="{ Component, route }">
			<component
				v-bind:is="Component"
				v-bind:key="route.name === 'messenger_chat' ? 'messenger-chat-pane' : route.fullPath"></component>
		</RouterView>
	</div>
</template>

<script>
    import { defineComponent, defineAsyncComponent } from 'vue';

    import MessengerNavbar from '@D/views/messenger/history/MessengerNavbar.vue';
	import MessengerActionBar from '@D/views/messenger/parts/MessengerActionBar.vue';

    export default defineComponent({
        components: {
            MessengerNavbar: MessengerNavbar,
			MessengerActionBar: MessengerActionBar,
			MessengerArchive: defineAsyncComponent(() => {
                return import('@D/views/messenger/history/MessengerArchive.vue');
            })
        }
    });
</script>
