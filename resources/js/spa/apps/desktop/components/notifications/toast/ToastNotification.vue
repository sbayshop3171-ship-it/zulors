<template>
    <Teleport to="body">
        <div v-if="toastNotifications.length" v-cloak class="fixed bottom-6 z-50 left-6 right-6 sticky-bar-bottom-fix">
            <div v-for="toastData in toastNotifications" class="flex justify-end mb-2 last:mb-0">
                <ToastItem v-on:dismiss="dismissMessage" v-bind:key="toastData.id" v-bind:toastData="toastData"></ToastItem>
            </div>
        </div>
    </Teleport>
</template>
<script>
    import { defineComponent, computed } from 'vue';
    import useToastNotificationStore from '@D/store/toast/toast.store.js';
    import ToastItem from '@D/components/notifications/toast/parts/ToastItem.vue';

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