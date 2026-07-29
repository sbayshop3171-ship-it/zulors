<template>
    <div v-if="toastNotifications.length" v-cloak>
        <ToastItem v-for="toastData in toastNotifications" v-on:dismiss="dismissMessage" v-bind:key="toastData.id" v-bind:toastData="toastData"></ToastItem>
    </div>
</template>
<script>
    import { defineComponent, computed } from 'vue';
    import useToastNotificationStore from '@M/store/toast/toast.store.js';
    import ToastItem from '@M/components/notifications/toast/parts/ToastItem.vue';

    export default defineComponent({
        setup: function() {
            const toastStore = useToastNotificationStore();
            const toastNotifications = computed(() => {
                return toastStore.notificationsList;
            });

            return {
                toastNotifications: toastNotifications,
                dismissMessage: (toastId) => {
                    toastStore.remove(toastId);
                }
            }
        },
        components: {
            ToastItem: ToastItem
        }
    });
</script>