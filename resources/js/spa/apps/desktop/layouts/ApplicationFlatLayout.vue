<template>
    <RouterView v-slot="{ Component, route }">
        <component v-bind:is="Component" v-bind:key="route.fullPath"></component>
    </RouterView>

    <ToastNotification></ToastNotification>
    <AccountSwitcherModal></AccountSwitcherModal>

    <ConfirmationModal></ConfirmationModal>
</template>

<script>
    import { defineComponent } from 'vue';

    import ToastNotification from '@D/components/notifications/toast/ToastNotification.vue';
    import AccountSwitcherModal from '@D/components/accounts/AccountSwitcherModal.vue';
    import ConfirmationModal from '@D/components/general/modals/prompt/ConfirmationModal.vue';

    export default defineComponent({
        components: {
            ToastNotification: ToastNotification,
            AccountSwitcherModal: AccountSwitcherModal,
            ConfirmationModal: ConfirmationModal
        }
    });
</script>
