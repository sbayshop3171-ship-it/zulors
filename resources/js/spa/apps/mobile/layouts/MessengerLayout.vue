<template>
	<RouterView v-slot="{ Component, route }">
		<component
			v-bind:is="Component"
			v-bind:key="route.name === 'messenger_chat' ? 'mobile-messenger-chat-pane' : route.fullPath"></component>
	</RouterView>

	<ConfirmationModal></ConfirmationModal>

	<ReportModal></ReportModal>

    <LightboxPlayer></LightboxPlayer>

    <CallOverlay v-bind:callStore="callStore"></CallOverlay>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted, watch } from 'vue';
	import { useRoute } from 'vue-router';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';
	import { useCallStore } from '@M/store/calls/call.store.js';
	import useToastNotificationStore from '@M/store/toast/toast.store.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { routeRealtimeNotification } from '@/kernel/services/realtime/notification-router.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import ConfirmationModal from '@M/components/general/modals/prompt/ConfirmationModal.vue';

	import ReportModal from '@M/components/reports/ReportModal.vue';
	import LightboxPlayer from '@M/components/lightbox/LightboxPlayer.vue';
	import CallOverlay from '@/kernel/vue/components/calls/CallOverlay.vue';

	export default defineComponent({
		setup: function() {
			const authStore = useAuthStore();
			const inboxStore = useInboxStore();
			const callStore = useCallStore();
			const toastStore = useToastNotificationStore();
			const route = useRoute();
			let isListening = false;

			const getAuthChannel = () => {
				return BRD.getChannel('AUTH_USER', [authStore.userData.id]);
			};

			const getActiveChatId = () => {
				return inboxStore.activeChatId || (['messenger_chat', 'messenger_group'].includes(route.name) ? route.params.chat_id : null);
			};

			const syncActiveChatFromRoute = () => {
				if(['messenger_chat', 'messenger_group'].includes(route.name) && route.params.chat_id) {
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

			const handleWSStatus = (event) => {
				if(event?.detail?.connected) {
					reconcileVisibleCall();
				}
			};

			const handleVisibilityChange = () => {
				if(document.visibilityState === 'visible') {
					reconcileVisibleCall();
				}
			};

			onMounted(() => {
				if(window.ColibriBRD && authStore.userData) {
					ColibriBRD.private(getAuthChannel()).notification(syncMessengerInbox);
					isListening = true;
				}

				syncActiveChatFromRoute();
				syncCallFromRoute();
				window.addEventListener('colibri:ws-status', handleWSStatus);
				window.addEventListener('focus', reconcileVisibleCall);
				window.addEventListener('zulors:app-resume', reconcileVisibleCall);
				document.addEventListener('visibilitychange', handleVisibilityChange);
			});

			onUnmounted(() => {
				if(isListening && window.ColibriBRD && authStore.userData) {
					ColibriBRD.private(getAuthChannel()).stopListeningForNotification(syncMessengerInbox);
				}

				window.removeEventListener('colibri:ws-status', handleWSStatus);
				window.removeEventListener('focus', reconcileVisibleCall);
				window.removeEventListener('zulors:app-resume', reconcileVisibleCall);
				document.removeEventListener('visibilitychange', handleVisibilityChange);
				inboxStore.setActiveChatId(null);
			});

			watch(() => route.fullPath, () => {
				syncActiveChatFromRoute();
				syncCallFromRoute();
				reconcileVisibleCall();
			});

			return {
				callStore: callStore
			};
		},
		components: {
			ConfirmationModal: ConfirmationModal,
			ReportModal: ReportModal,
			LightboxPlayer: LightboxPlayer,
			CallOverlay: CallOverlay
		}
	});
</script>
