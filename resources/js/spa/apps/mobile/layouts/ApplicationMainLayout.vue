<template>
	<ApplicationHeader v-if="! hideHeader"></ApplicationHeader>

	<div class="mobile-app-content" v-bind:class="{ 'mobile-app-content--no-navbar': hideNavbar }">
		<RouterView v-slot="{ Component, route }">
			<component v-bind:is="Component" v-bind:key="route.fullPath"></component>
		</RouterView>
	</div>

	<LightboxPlayer></LightboxPlayer>

	<ConfirmationModal></ConfirmationModal>

	<ApplicationNavbar v-if="! hideNavbar"></ApplicationNavbar>

	<ReportModal></ReportModal>

	<NotificationsModal v-if="isNotificationsOpen"></NotificationsModal>
</template>

<script>
	import { defineComponent, computed, onMounted, onUnmounted } from 'vue';
	import { useRouter, useRoute } from 'vue-router';
	import { useNotificationsStore } from '@M/store/notifications/notifications.store.js';
	import BRD from '@/kernel/websockets/brd/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';
	import useToastNotificationStore from '@M/store/toast/toast.store.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { usePostEditorStore } from '@M/store/timeline/editor.store.js';

	import ApplicationHeader from '@M/components/layout/ApplicationHeader.vue';
	import ApplicationNavbar from '@M/components/layout/ApplicationNavbar.vue';
	import LightboxPlayer from '@M/components/lightbox/LightboxPlayer.vue';
	import ConfirmationModal from '@M/components/general/modals/prompt/ConfirmationModal.vue';
	import ReportModal from '@M/components/reports/ReportModal.vue';
	import NotificationsModal from '@M/components/notifications/native/NotificationsModal.vue';


	export default defineComponent({
		setup: function() {
			const notificationsStore = useNotificationsStore();
			const authStore = useAuthStore();
			const inboxStore = useInboxStore();
			const toastStore = useToastNotificationStore();
			const postEditorStore = usePostEditorStore();
			const router = useRouter();
			const route = useRoute();
			let isListening = false;

			const openEditor = (data) => {
				if(data?.editPost) {
					postEditorStore.startEditingPost(data.editPost);
				}
				else if(data?.mentionName) {
					postEditorStore.mentionName = data.mentionName;
				}

				router.push({
					name: 'post_editor'
				});
			};

			const getChatToastText = (messageData = {}) => {
				let senderName = messageData.relations?.user?.name || __t('chat.new_message_prefix');
				let previewText = inboxStore.getMessagePreview(messageData, false) || __t('labels.message');

				return `${senderName}: ${previewText}`;
			};

			const getAuthChannel = () => {
				return BRD.getChannel('AUTH_USER', [authStore.userData.id]);
			};

			const syncAppNotifications = (event) => {
				if(event.type === 'chat.notification') {
					let shouldNotify = inboxStore.handleIncomingMessageNotification(event.data, authStore.userData.id);

					if(shouldNotify) {
						toastStore.add(getChatToastText(event.data), 4000);

						if(colibriSounds.isNotificationsSoundEnabled()) {
							colibriSounds.backgroundChatMessageReceived();
						}
					}
				}
				else {
					notificationsStore.setUnreadNotificationsCount(event.data);
					colibriEventBus.emit('notifications:received');

					colibriSounds.notificationReceived();
				}
			};

			onMounted(() => {
				if(window.ColibriBRD && authStore.userData) {
                    ColibriBRD.private(getAuthChannel()).notification(syncAppNotifications);
					isListening = true;
                }

				colibriEventBus.on('post-editor:open', openEditor);
			});

			onUnmounted(() => {
                if(isListening && window.ColibriBRD && authStore.userData) {
                    ColibriBRD.private(getAuthChannel()).stopListeningForNotification(syncAppNotifications);
                }

				colibriEventBus.off('post-editor:open', openEditor);
            });

			return {
				isNotificationsOpen: computed(() => {
					return notificationsStore.isOpen;
				}),
				hideNavbar: computed(() => {
					return route.meta.hideNavbar || false;
				}),
				hideHeader: computed(() => {
					return route.meta.hideHeader || false;
				})
			};
		},
		components: {
			ApplicationHeader: ApplicationHeader,
			ApplicationNavbar: ApplicationNavbar,
			LightboxPlayer: LightboxPlayer,
			ConfirmationModal: ConfirmationModal,
			ReportModal: ReportModal,
			NotificationsModal: NotificationsModal
		}
	});
</script>
