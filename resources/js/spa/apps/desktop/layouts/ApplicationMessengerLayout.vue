<template>
	<RouterView v-slot="{ Component }">
		<component v-bind:is="Component"></component>
	</RouterView>

	<AccountSwitcherModal></AccountSwitcherModal>

	<ConfirmationModal></ConfirmationModal>

	<ReportModal></ReportModal>

	<ToastNotification></ToastNotification>

    <LightboxPlayer></LightboxPlayer>

    <CallOverlay v-bind:callStore="callStore"></CallOverlay>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted, watch } from 'vue';
	import { useRoute } from 'vue-router';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { useInboxStore } from '@D/store/chats/inbox.store.js';
	import { useCallStore } from '@D/store/calls/call.store.js';
	import useToastNotificationStore from '@D/store/toast/toast.store.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { routeRealtimeNotification } from '@/kernel/services/realtime/notification-router.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import ReportModal from '@D/components/reports/ReportModal.vue';
	import ConfirmationModal from '@D/components/general/modals/prompt/ConfirmationModal.vue';
	import ToastNotification from '@D/components/notifications/toast/ToastNotification.vue';
	import AccountSwitcherModal from '@D/components/accounts/AccountSwitcherModal.vue';
    import LightboxPlayer from '@D/components/lightbox/LightboxPlayer.vue';
    import CallOverlay from '@/kernel/vue/components/calls/CallOverlay.vue';

	export default defineComponent({
		setup: function() {
			const authStore = useAuthStore();
			const inboxStore = useInboxStore();
			const callStore = useCallStore();
			const toastStore = useToastNotificationStore();
			const route = useRoute();
			let isListening = false;
			let unreadRefreshTimer = null;

			const getAuthChannel = () => {
				return BRD.getChannel('AUTH_USER', [authStore.userData.id]);
			};

			const getActiveChatId = () => {
				return inboxStore.activeChatId || (route.name === 'messenger_chat' ? route.params.chat_id : null);
			};

			const syncActiveChatFromRoute = () => {
				if(route.name === 'messenger_chat' && route.params.chat_id) {
					inboxStore.setActiveChatId(route.params.chat_id);
				}
				else {
					inboxStore.setActiveChatId(null);
				}
			};

			const getChatToastText = (messageData = {}) => {
				let senderName = messageData.relations?.user?.name || __t('chat.new_message_prefix');
				let previewText = inboxStore.getMessagePreview(messageData, false) || __t('labels.message');

				return `${senderName}: ${previewText}`;
			};

			const syncMessengerInbox = (event) => {
				routeRealtimeNotification(event, {
					callStore,
					inboxStore,
					toastStore,
					sounds: colibriSounds,
					authUserId: authStore.userData.id,
					activeChatId: getActiveChatId(),
					getChatToastText,
				});
			};

			const syncCallFromRoute = () => {
				callStore.bootstrapFromRouteOrCurrentCall({
					chatId: getActiveChatId(),
					callUuid: route.query.call || null,
					action: route.query.action || null,
					intent: route.query.intent || null
				}).catch(() => {});
			};

			const reconcileVisibleCall = () => {
				if(! callStore.isVisible) {
					return;
				}

				callStore.reconcileActiveCall({
					setupIfNeeded: true
				}).catch(() => {});
			};

			const attachRealtimeListener = () => {
				if(isListening || ! window.ColibriBRD || ! authStore.userData) {
					return;
				}

				ColibriBRD.private(getAuthChannel()).notification(syncMessengerInbox);
				isListening = true;
			};

			const detachRealtimeListener = () => {
				if(! isListening || ! window.ColibriBRD || ! authStore.userData) {
					return;
				}

				ColibriBRD.private(getAuthChannel()).stopListeningForNotification(syncMessengerInbox);
				isListening = false;
			};

			const refreshUnreadState = (delay = 0) => {
				if(unreadRefreshTimer) {
					window.clearTimeout(unreadRefreshTimer);
				}

				unreadRefreshTimer = window.setTimeout(() => {
					if(document.visibilityState === 'hidden') {
						return;
					}

					inboxStore.syncUnreadState();
				}, delay);
			};

			const handleWSStatus = (event) => {
				if(event.detail.connected) {
					attachRealtimeListener();
					refreshUnreadState(150);
					reconcileVisibleCall();
				}
			};

			const handleFocus = () => {
				refreshUnreadState(150);
				reconcileVisibleCall();
			};

			const handleVisibilityChange = () => {
				if(document.visibilityState === 'visible') {
					refreshUnreadState(150);
					reconcileVisibleCall();
				}
			};

			onMounted(() => {
				if(! authStore.userData) {
					return;
				}

				attachRealtimeListener();
				syncActiveChatFromRoute();
				syncCallFromRoute();
				window.addEventListener('colibri:ws-status', handleWSStatus);
				window.addEventListener('focus', handleFocus);
				document.addEventListener('visibilitychange', handleVisibilityChange);
			});

			onUnmounted(() => {
				detachRealtimeListener();
				window.removeEventListener('colibri:ws-status', handleWSStatus);
				window.removeEventListener('focus', handleFocus);
				document.removeEventListener('visibilitychange', handleVisibilityChange);
				inboxStore.setActiveChatId(null);

				if(unreadRefreshTimer) {
					window.clearTimeout(unreadRefreshTimer);
				}
			});

			watch(() => route.fullPath, () => {
				syncActiveChatFromRoute();
				syncCallFromRoute();
			});

			return {
				callStore: callStore
			};
		},
		components: {
			AccountSwitcherModal: AccountSwitcherModal,
			ConfirmationModal: ConfirmationModal,
			ReportModal: ReportModal,
			ToastNotification: ToastNotification,
            LightboxPlayer: LightboxPlayer,
            CallOverlay: CallOverlay,
		}
	});
</script>
