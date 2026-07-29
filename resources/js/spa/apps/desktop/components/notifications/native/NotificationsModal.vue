<template>
	<Backdrop v-on:click.self="closeModal">
		<div class="w-navbar popup-background-sc fixed top-0 bottom-0 left-0 pt-4">
			<div class="h-full flex flex-col">
				<div class="border-b border-b-bord-pr">
					<div class="px-4 pb-4">
						<PageTitle v-bind:hasBack="false" v-bind:titleText="$t('notifs.notifications_title')"></PageTitle>
					</div>
					<ContentTabs>
                        <TabsItem v-on:click="handleTabChange('all')" v-bind:isActive="(state.tab == 'all')" size="sm">
                            {{ $t('notifs.all_notifications') }}
                        </TabsItem>
						<TabsItem v-on:click="handleTabChange('mentions')" v-bind:isActive="(state.tab == 'mentions')" size="sm">
							{{ $t('notifs.mentions') }}
						</TabsItem>
						<TabsItem v-on:click="handleTabChange('important')" v-bind:isActive="(state.tab == 'important')" size="sm">
							{{ $t('notifs.important_notifications') }}
						</TabsItem>
                    </ContentTabs>
				</div>
				<div class="flex-1 overflow-y-auto">
					<template v-if="state.isLoading">
						<NotificationItemSkeleton v-for="i in 7"></NotificationItemSkeleton>
					</template>
					<template v-else>
						<template v-if="isEmpty">
							<div class="py-14">
								<p class="text-par-s text-lab-sc text-center">
									{{ $t('notifs.no_notifications') }}
								</p>
							</div>
						</template>
						<template v-else v-for="notificationsGroup in groupedNotifications">
							<div v-if="notificationsGroup.notifications.length">
								<div class="px-4 py-2">
									<p class="text-par-n font-semibold text-lab-pr2">
										{{ notificationsGroup.title }}
									</p>
								</div>
								<NotificationItem v-for="notificationItem in notificationsGroup.notifications" v-on:route="handleRouting" v-bind:notificationData="notificationItem"></NotificationItem>
								<Border height="h-2" opacity="opacity-70"></Border>
							</div>
						</template>
					</template>
				</div>
				<div class="shrink-0">
					<NotificationSoundControl></NotificationSoundControl>
				</div>
			</div>
		</div>

		<div class="backdrop-close-button-container-top-right">
			<BackdropCloseButton v-on:click="closeModal"></BackdropCloseButton>
		</div>
	</Backdrop>
</template>

<script>
	import { defineComponent, computed, onMounted, reactive, onUnmounted } from 'vue';
	import { useNotificationsStore } from '@D/store/notifications/notifications.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useRouter } from 'vue-router';
	import hotkeys from 'hotkeys-js';

	import Backdrop from '@D/components/general/modals/Backdrop.vue';
	import NotificationItem from '@D/components/notifications/native/parts/NotificationItem.vue';
	import NotificationItemSkeleton from '@D/components/notifications/native/parts/NotificationItemSkeleton.vue';
	import NotificationSoundControl from '@D/components/notifications/native/parts/NotificationSoundControl.vue';
	import PageTitle from '@D/components/layout/PageTitle.vue';
	import BackdropCloseButton from '@D/components/inter-ui/buttons/BackdropCloseButton.vue';
	import ContentTabs from '@D/components/general/tabs/content/ContentTabs.vue';
    import TabsItem from '@D/components/general/tabs/content/parts/TabsItem.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				tab: 'all',
				isLoading: true
			});

			const router = useRouter();
			const notificationsStore = useNotificationsStore();

            const notifications = computed(() => {
                return notificationsStore.notifications;
            });

			const handleNotificationReceived = async () => {
				state.tab = 'all';
				await notificationsStore.fetchNotifications(state.tab);
			};
            
            onMounted(async () => {
                await notificationsStore.fetchNotifications(state.tab);

				state.isLoading = false;

                notificationsStore.fetchUnreadCount();

				colibriEventBus.on('notifications:received', handleNotificationReceived);

				hotkeys('esc', closeModal);
            });

			onUnmounted(() => {
				colibriEventBus.off('notifications:received', handleNotificationReceived);

				hotkeys.unbind('esc');
			});

			const handleTabChange = async (tab) => {
				state.tab = tab;
				state.isLoading = true;
				await notificationsStore.fetchNotifications(state.tab);
				state.isLoading = false;
			};

			const closeModal = () => {
				notificationsStore.closeNotifications();
			}

			const groupedNotifications = computed(() => {
				let sortedNotifications = [
					{
						title: __t('notifs.date_sections.today'),
						notifications: []
					},
					{
						title: __t('notifs.date_sections.yesterday'),
						notifications: []
					},
					{
						title: __t('notifs.date_sections.this_week'),
						notifications: []
					},
					{
						title: __t('notifs.date_sections.this_month'),
						notifications: []
					},
					{
						title: __t('notifs.date_sections.earlier'),
						notifications: []
					}
				];

				notifications.value.forEach((i) => {
					if(i.date.is_today) {
						// Push to Today
						sortedNotifications[0].notifications.push(i);
					}
					else if(i.date.is_yesterday) {
						// Push to Yesterday
						sortedNotifications[1].notifications.push(i);
					}
					else if(i.date.is_this_week) {
						// Push to This week
						sortedNotifications[2].notifications.push(i);
					}
					else if(i.date.is_this_month) {
						// Push to This month
						sortedNotifications[3].notifications.push(i);
					}
					else {
						// Push to Earlier
						sortedNotifications[4].notifications.push(i);
					}
				});

				return sortedNotifications;
			});

			return {
				state: state,
				groupedNotifications: groupedNotifications,
				handleTabChange: handleTabChange,
				closeModal: closeModal,
					handleRouting: (routeData) => {
						closeModal();

						if(routeData.external_url) {
							window.location.href = routeData.external_url;
						}
						else {
							router.push(routeData);
						}
					},
				isEmpty: computed(() => {
					// Check if all groups are empty.
					// If so, return true.
					return groupedNotifications.value.every((group) => group.notifications.length === 0);
				})
			};
		},
		components: {
			Backdrop: Backdrop,
			NotificationItem: NotificationItem,
			PageTitle: PageTitle,
			NotificationItemSkeleton: NotificationItemSkeleton,
			NotificationSoundControl: NotificationSoundControl,
			BackdropCloseButton: BackdropCloseButton,
			TabsItem: TabsItem,
			ContentTabs: ContentTabs,
		}
	});
</script>
