<template>
	<ApplicationHeader v-if="! hideHeader"></ApplicationHeader>

	<div class="mobile-app-content" v-bind:class="{ 'mobile-app-content--no-navbar': hideNavbar }">
		<div class="mobile-app-stage">
			<RouterView v-slot="{ Component, route }">
				<Transition v-bind:name="routeTransition.name">
					<div v-bind:key="route.fullPath" class="mobile-route-view">
						<component v-bind:is="Component"></component>
					</div>
				</Transition>
			</RouterView>
		</div>
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
	import { useMobileRouteTransition } from '@M/core/services/route-transition/index.js';

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
			const routeTransition = useMobileRouteTransition();
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
				}),
				routeTransition: routeTransition
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

<style scoped>
	.mobile-app-stage {
		position: relative;
		min-height: 100%;
		overflow-x: hidden;
	}

	.mobile-route-view {
		min-height: 100%;
		background: var(--bg-pr);
	}

	.mobile-route-slide-next-enter-active,
	.mobile-route-slide-next-leave-active,
	.mobile-route-slide-prev-enter-active,
	.mobile-route-slide-prev-leave-active,
	.mobile-route-fade-enter-active,
	.mobile-route-fade-leave-active {
		will-change: transform, opacity;
		backface-visibility: hidden;
		transform: translate3d(0, 0, 0);
	}

	.mobile-route-slide-next-enter-active,
	.mobile-route-slide-next-leave-active,
	.mobile-route-slide-prev-enter-active,
	.mobile-route-slide-prev-leave-active {
		transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), opacity 220ms ease-out;
	}

	.mobile-route-fade-enter-active,
	.mobile-route-fade-leave-active {
		transition: opacity 160ms ease-out, transform 160ms ease-out;
	}

	.mobile-route-slide-next-leave-active,
	.mobile-route-slide-prev-leave-active,
	.mobile-route-fade-leave-active {
		position: absolute;
		inset: 0;
		width: 100%;
		pointer-events: none;
	}

	.mobile-route-slide-next-enter-from {
		transform: translate3d(14%, 0, 0);
		opacity: 0.8;
	}

	.mobile-route-slide-next-leave-to {
		transform: translate3d(-8%, 0, 0);
		opacity: 0.94;
	}

	.mobile-route-slide-prev-enter-from {
		transform: translate3d(-14%, 0, 0);
		opacity: 0.8;
	}

	.mobile-route-slide-prev-leave-to {
		transform: translate3d(8%, 0, 0);
		opacity: 0.94;
	}

	.mobile-route-fade-enter-from,
	.mobile-route-fade-leave-to {
		transform: translate3d(0, 0.35rem, 0);
		opacity: 0;
	}

	@media (prefers-reduced-motion: reduce) {
		.mobile-route-slide-next-enter-active,
		.mobile-route-slide-next-leave-active,
		.mobile-route-slide-prev-enter-active,
		.mobile-route-slide-prev-leave-active,
		.mobile-route-fade-enter-active,
		.mobile-route-fade-leave-active {
			transition-duration: 1ms;
		}
	}
</style>
