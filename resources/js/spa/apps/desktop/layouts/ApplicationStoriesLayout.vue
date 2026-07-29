<template>
    <RouterView></RouterView>

    <ToastNotification></ToastNotification>
</template>

<script>
    import { defineComponent } from 'vue';

    import ToastNotification from '@D/components/notifications/toast/ToastNotification.vue';

    export default defineComponent({
        components: {
            ToastNotification: ToastNotification
        }
    });
</script>