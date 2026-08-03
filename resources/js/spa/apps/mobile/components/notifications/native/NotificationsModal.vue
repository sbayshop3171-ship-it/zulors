<template>
	<div class="fixed inset-0 z-100 popup-background-tr">
		<div class="h-full flex flex-col">
			<div class="border-b border-b-bord-pr">
				<Toolbar v-bind:title="$t('notifs.notifications_title')" v-on:close="closeModal"></Toolbar>
				<div class="grid grid-cols-3">
					<button v-on:click="handleTabChange('all')" class="border-b-2 font-medium truncate text-par-m text-center px-4 py-3" v-bind:class="[state.tab == 'all' ? 'border-b-lab-pr text-lab-pr' : 'border-b-transparent text-lab-sc']">
						{{ $t('notifs.all_notifications') }}
					</button>
					<button v-on:click="handleTabChange('mentions')" class="border-b-2 font-medium truncate text-par-m text-center px-4 py-3" v-bind:class="[state.tab == 'mentions' ? 'border-b-lab-pr text-lab-pr' : 'border-b-transparent text-lab-sc']">
						{{ $t('notifs.mentions') }}
					</button>
					<button v-on:click="handleTabChange('important')" class="border-b-2 font-medium truncate text-par-m text-center px-4 py-3" v-bind:class="[state.tab == 'important' ? 'border-b-lab-pr text-lab-pr' : 'border-b-transparent text-lab-sc']">
						{{ $t('notifs.important_notifications') }}
					</button>
				</div>
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
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, onMounted, reactive, onUnmounted } from 'vue';
	import { useNotificationsStore } from '@M/store/notifications/notifications.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { registerNativeBackHandler } from '@M/core/services/native-back/index.js';
	import { useRouter } from 'vue-router';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import NotificationItem from '@M/components/notifications/native/parts/NotificationItem.vue';
	import NotificationItemSkeleton from '@M/components/notifications/native/parts/NotificationItemSkeleton.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				tab: 'all',
				isLoading: true
			});

			const router = useRouter();
			const notificationsStore = useNotificationsStore();
			let unregisterNativeBackHandler = null;

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
				unregisterNativeBackHandler = registerNativeBackHandler(() => {
					closeModal();

					return true;
				});

				freezeScroll();
            });

			onUnmounted(() => {
				colibriEventBus.off('notifications:received', handleNotificationReceived);

				unfreezeScroll();

				if(unregisterNativeBackHandler) {
					unregisterNativeBackHandler();
				}
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
					handleRouting: (routeData) => {
						closeModal();

						if(routeData.external_url) {
							window.location.href = routeData.external_url;
						}
						else {
							router.push(routeData);
						}
					},
				closeModal: closeModal,
				isEmpty: computed(() => {
					// Check if all groups are empty.
					// If so, return true.
					return groupedNotifications.value.every((group) => group.notifications.length === 0);
				})
			};
		},
		components: {
			Toolbar: Toolbar,
			NotificationItem: NotificationItem,
			NotificationItemSkeleton: NotificationItemSkeleton
		}
	});
</script>
