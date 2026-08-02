<template>
	<div class="relative shrink-0">
		<PrimaryIconButton v-on:click="openNotificationsModal" buttonColor="text-lab-pr" iconName="bell-01" iconType="line" iconAreaSize="11"></PrimaryIconButton>
		<span class="pointer-events-none absolute top-0 right-0">
			<BadgeCounter v-if="notificationsCount.raw" v-bind:count="notificationsCount.formatted"></BadgeCounter>
		</span>
	</div>
</template>

<script>
	import { defineComponent, onMounted, computed, onUnmounted } from 'vue';
	import { useNotificationsStore } from '@M/store/notifications/notifications.store.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import BadgeCounter from '@M/components/general/counters/BadgeCounter.vue';

	export default defineComponent({
		setup: function() {
			const notificationsStore = useNotificationsStore();
			const authStore = useAuthStore();
			let isListening = false;

			const notificationsCount = computed(() => {
                return notificationsStore.unreadCount;
            });

			const getAuthChannel = () => {
				return BRD.getChannel('AUTH_USER', [authStore.userData.id]);
			};

			const syncNativeNotifications = (event) => {
				if(event.type === 'main.notification') {
					notificationsStore.setUnreadNotificationsCount(event.data);

					if(colibriSounds.isNotificationsSoundEnabled()) {
						colibriSounds.notificationReceived();
					}
				}
			};

			onMounted(() => {
				notificationsStore.fetchUnreadCount();

				if(window.ColibriBRD && authStore.userData) {
                    ColibriBRD.private(getAuthChannel()).notification(syncNativeNotifications);
					isListening = true;
                }
			});

			onUnmounted(() => {
                if(isListening && window.ColibriBRD && authStore.userData) {
                    ColibriBRD.private(getAuthChannel()).stopListeningForNotification(syncNativeNotifications);
                }
            });

			return {
				notificationsCount: notificationsCount,
				openNotificationsModal: () => {
					notificationsStore.openNotifications();
				}
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			BadgeCounter: BadgeCounter
		}
	});
</script>
