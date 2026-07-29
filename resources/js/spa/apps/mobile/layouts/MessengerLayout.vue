<template>
	<RouterView v-slot="{ Component, route }">
		<component v-bind:is="Component" v-bind:key="route.fullPath"></component>
	</RouterView>

	<ConfirmationModal></ConfirmationModal>

	<ReportModal></ReportModal>

    <LightboxPlayer></LightboxPlayer>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted } from 'vue';
	import { useRoute } from 'vue-router';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';
	import useToastNotificationStore from '@M/store/toast/toast.store.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import ConfirmationModal from '@M/components/general/modals/prompt/ConfirmationModal.vue';

	import ReportModal from '@M/components/reports/ReportModal.vue';
	import LightboxPlayer from '@M/components/lightbox/LightboxPlayer.vue';

	export default defineComponent({
		setup: function() {
			const authStore = useAuthStore();
			const inboxStore = useInboxStore();
			const toastStore = useToastNotificationStore();
			const route = useRoute();
			let isListening = false;

			const getAuthChannel = () => {
				return BRD.getChannel('AUTH_USER', [authStore.userData.id]);
			};

			const getActiveChatId = () => {
				return ['messenger_chat', 'messenger_group'].includes(route.name) ? route.params.chat_id : null;
			};

			const getChatToastText = (messageData = {}) => {
				let senderName = messageData.relations?.user?.name || __t('chat.new_message_prefix');
				let previewText = inboxStore.getMessagePreview(messageData, false) || __t('labels.message');

				return `${senderName}: ${previewText}`;
			};

			const syncMessengerInbox = (event) => {
				if(event.type === 'chat.notification') {
					let shouldNotify = inboxStore.handleIncomingMessageNotification(event.data, authStore.userData.id, getActiveChatId());

					if(shouldNotify) {
						toastStore.add(getChatToastText(event.data), 4000);

						if(colibriSounds.isNotificationsSoundEnabled()) {
							colibriSounds.backgroundChatMessageReceived();
						}
					}
				}
			};

			onMounted(() => {
				if(window.ColibriBRD && authStore.userData) {
					ColibriBRD.private(getAuthChannel()).notification(syncMessengerInbox);
					isListening = true;
				}
			});

			onUnmounted(() => {
				if(isListening && window.ColibriBRD && authStore.userData) {
					ColibriBRD.private(getAuthChannel()).stopListeningForNotification(syncMessengerInbox);
				}
			});
		},
		components: {
			ConfirmationModal: ConfirmationModal,
			ReportModal: ReportModal,
			LightboxPlayer: LightboxPlayer
		}
	});
</script>
