<template>
    <div class="fixed inset-0 flex w-messenger-sidebar">
		<MessengerActionBar></MessengerActionBar>
        <MessengerArchive v-if="$route.meta.archive"></MessengerArchive>
        <MessengerNavbar v-else></MessengerNavbar>
	</div>
	<div class="fixed inset-0 ml-messenger-sidebar">
        <RouterView v-bind:key="$route.params.chat_id || 1"></RouterView>
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