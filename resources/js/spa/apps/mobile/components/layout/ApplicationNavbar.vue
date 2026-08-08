<template>
	<div v-if="userData" class="mobile-safe-navbar fixed bottom-0 left-0 right-0 z-50 bg-bg-pr" v-bind:class="{ 'mobile-safe-navbar--standalone': $isStandalone() }">
		<ToastNotification></ToastNotification>
		<StoryFileUploader></StoryFileUploader>

		<div class="grid grid-cols-5 h-14">
			<div class="flex items-center justify-center">
				<RouterLink v-bind:to="{ name: 'home_index' }" class="block rounded-full transform-gpu transition-transform duration-150 ease-out active:scale-[0.94]">
					<PrimaryIconButton buttonColor="text-lab-pr" iconName="home-smile" iconType="line"></PrimaryIconButton>
				</RouterLink>
			</div>
			<div class="flex items-center justify-center">
				<RouterLink v-bind:to="{ name: 'explore_index' }" class="block rounded-full transform-gpu transition-transform duration-150 ease-out active:scale-[0.94]">
					<PrimaryIconButton buttonColor="text-lab-pr" iconName="search-lg" iconType="solid"></PrimaryIconButton>
				</RouterLink>
			</div>
			<div class="flex items-center justify-center">
				<PrimaryIconButton v-on:click="state.mainMenu.open" buttonColor="text-lab-pr" iconName="plus-square-dashed" iconType="line"></PrimaryIconButton>
			</div>
			<div class="flex items-center justify-center">
				<div class="relative">
					<RouterLink v-bind:to="{ name: 'messenger_index' }" class="block rounded-full transform-gpu transition-transform duration-150 ease-out active:scale-[0.94]">
						<PrimaryIconButton buttonColor="text-lab-pr" iconName="message-chat-circle" iconType="line"></PrimaryIconButton>
					</RouterLink>
					<span class="absolute -top-1.5 -right-1">
						<BadgeCounter v-if="inboxCount.raw" v-bind:count="inboxCount.formatted"></BadgeCounter>
					</span>
				</div>
			</div>
			<div class="flex items-center justify-center leading-zero">
				<RouterLink v-bind:to="profileRoute" class="block rounded-full transform-gpu transition-transform duration-150 ease-out active:scale-[0.94]">
					<div class="inline-flex border border-bord-card items-center justify-center size-6 rounded-full overflow-hidden transition-opacity duration-150 ease-out active:opacity-90">
						<img v-bind:src="userData.avatar_url" v-bind:alt="userData.username || 'Profile'" class="size-full object-cover">
					</div>
				</RouterLink>
			</div>
		</div>
	</div>

	<ActionSheet v-if="state.mainMenu.status" v-on:close="state.mainMenu.close" v-bind:isMuted="true">
		<div v-on:click="state.mainMenu.close">
			<ActionSheetGroup>
				<RouterLink v-bind:to="{ name: 'post_editor' }">
					<ActionSheetItem v-bind:notLast="true" iconName="publication-01" v-bind:textLabel="$t('labels.create_labels.post')"></ActionSheetItem>
				</RouterLink>
				<ActionSheetItem v-on:click="createStory" v-bind:notLast="businessActions.length > 0" iconName="create-story-01" v-bind:textLabel="$t('labels.create_labels.story')"></ActionSheetItem>
				<template v-for="(businessAction, index) in businessActions" v-bind:key="businessAction.href">
					<a v-bind:href="businessAction.href">
						<ActionSheetItem
							v-bind:notLast="index < (businessActions.length - 1)"
							v-bind:iconName="businessAction.icon"
							v-bind:iconType="businessAction.iconType"
						v-bind:textLabel="businessAction.label"></ActionSheetItem>
					</a>
				</template>
			</ActionSheetGroup>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, computed, reactive, onMounted, onUnmounted } from 'vue';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';
	import useToastNotificationStore from '@M/store/toast/toast.store.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { makeProfileRoute } from '@/kernel/support/profile-routing/index.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ToastNotification from '@M/components/notifications/toast/ToastNotification.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import StoryFileUploader from '@M/views/editors/stories/StoryFileUploader.vue';
	import BadgeCounter from '@M/components/general/counters/BadgeCounter.vue';

	export default defineComponent({
		setup: function() {
			const authStore = useAuthStore();
			const inboxStore = useInboxStore();
			const toastStore = useToastNotificationStore();
			const state = reactive({
				mainMenu: useMenu()
			});
			let isListening = false;
			let unreadRefreshTimer = null;
			let navigationWarmHandle = null;
			let navigationWarmHandleIsIdle = false;

			const inboxCount = computed(() => {
                return inboxStore.unreadCount;
            });

			const profileRoute = computed(() => {
				return makeProfileRoute(authStore.userData?.username);
			});

			const getAuthChannel = () => {
				return BRD.getChannel('AUTH_USER', [authStore.userData.id]);
			};

			const getChatToastText = (messageData = {}) => {
				let senderName = messageData.relations?.user?.name || __t('chat.new_message_prefix');
				let previewText = inboxStore.getMessagePreview(messageData, false) || __t('labels.message');

				return `${senderName}: ${previewText}`;
			};

			const syncInboxNotification = (event) => {
				if(event.type !== 'chat.notification') {
					return;
				}

				let shouldNotify = inboxStore.handleIncomingMessageNotification(event.data, authStore.userData.id);

				if(shouldNotify) {
					toastStore.add(getChatToastText(event.data), 4000);

					if(colibriSounds.isNotificationsSoundEnabled()) {
						colibriSounds.backgroundChatMessageReceived();
					}
				}
			};

			const attachRealtimeListener = () => {
				if(isListening || ! window.ColibriBRD || ! authStore.userData) {
					return;
				}

				ColibriBRD.private(getAuthChannel()).notification(syncInboxNotification);
				isListening = true;
			};

			const detachRealtimeListener = () => {
				if(! isListening || ! window.ColibriBRD || ! authStore.userData) {
					return;
				}

				ColibriBRD.private(getAuthChannel()).stopListeningForNotification(syncInboxNotification);
				isListening = false;
			};

			const handleWSStatus = (event) => {
				if(event.detail.connected) {
					attachRealtimeListener();
					refreshUnreadState(150);
				}
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

			const handleFocus = () => {
				refreshUnreadState(150);
			};

			const handleVisibilityChange = () => {
				if(document.visibilityState === 'visible') {
					refreshUnreadState(150);
				}
			};

			const warmPrimaryNavigation = () => {
				const warm = () => {
					navigationWarmHandle = null;
					navigationWarmHandleIsIdle = false;

					if(document.visibilityState === 'hidden') {
						return;
					}

					import('@M/views/home/HomeIndex.vue');
					import('@M/views/explore/children/posts/ExplorePosts.vue');
					import('@M/views/explore/children/reels/ExploreReels.vue');
					import('@M/views/explore/children/people/ExplorePeople.vue');
				};

				if('requestIdleCallback' in window) {
					navigationWarmHandleIsIdle = true;
					navigationWarmHandle = window.requestIdleCallback(warm, { timeout: 1800 });
				}
				else {
					navigationWarmHandle = window.setTimeout(warm, 800);
				}
			};

			onMounted(() => {
				if(! authStore.userData) {
					return;
				}

				inboxStore.fetchUnreadCount();
				window.addEventListener('colibri:ws-status', handleWSStatus);
				window.addEventListener('focus', handleFocus);
				document.addEventListener('visibilitychange', handleVisibilityChange);
				attachRealtimeListener();
				warmPrimaryNavigation();
			});

			onUnmounted(() => {
				window.removeEventListener('colibri:ws-status', handleWSStatus);
				window.removeEventListener('focus', handleFocus);
				document.removeEventListener('visibilitychange', handleVisibilityChange);

				if(unreadRefreshTimer) {
					window.clearTimeout(unreadRefreshTimer);
				}

				if(navigationWarmHandle) {
					if(navigationWarmHandleIsIdle && 'cancelIdleCallback' in window) {
						window.cancelIdleCallback(navigationWarmHandle);
					}
					else {
						window.clearTimeout(navigationWarmHandle);
					}
				}

				detachRealtimeListener();
			});

			return {
				userData: computed(() => {
					return authStore.userData;
				}),
				state: state,
				createStory: function() {
					colibriEventBus.emit('story:create');
				},
				businessActions: computed(() => {
					if(! config('features.business_accounts.enabled')) {
						return [];
					}

					let actions = [];

					if(config('features.marketplace.enabled')) {
						actions.push({
							icon: 'shopping-bag-03',
							iconType: 'line',
							label: __t('labels.create_labels.product'),
							href: embedder('routes.business_market_create')
						});
					}

					if(config('features.jobs.enabled')) {
						actions.push({
							icon: 'briefcase-01',
							iconType: 'line',
							label: __t('labels.create_labels.job'),
							href: embedder('routes.business_jobs_create')
						});
					}

					if(config('features.ads.enabled')) {
						actions.push({
							icon: 'bar-chart-12',
							iconType: 'line',
							label: __t('labels.create_labels.campaign'),
							href: embedder('routes.business_ads_create')
						});
					}

					return actions;
				}),
				inboxCount: inboxCount,
				profileRoute: profileRoute
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			ToastNotification: ToastNotification,
			ActionSheet: ActionSheet,
			ActionSheetItem: ActionSheetItem,
			ActionSheetGroup: ActionSheetGroup,
			StoryFileUploader: StoryFileUploader,
			BadgeCounter: BadgeCounter
		}
	});
</script>
