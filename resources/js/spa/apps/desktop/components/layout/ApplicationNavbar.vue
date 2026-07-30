<template>
    <div v-if="userData" class="flex flex-col gap-3">
        <div class="block">
            <RouterLink v-bind:to="{ name: 'home_index' }" v-slot="{ isActive }" class="block">
                <div class="flex items-center" v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="home-smile" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.home') }}
                    </span>
                </div>
            </RouterLink>
        </div>

        <div class="block">
            <RouterLink v-bind:to="{ name: 'explore_posts' }" v-slot="{ isActive }" class="block">
                <div class="flex items-center"  v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="hash-02"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.explore') }}
                    </span>
                </div>
            </RouterLink>
        </div>
        <div class="block">
            <div v-on:click="openNotificationsModal" class="flex items-center sidenav-inactive cursor-pointer">
                <span class="size-icon-normal shrink-0">
                    <SvgIcon name="bell-01" type="line"></SvgIcon>
                </span>
                <span class="ml-3 text-[19px]">
                    {{ $t('labels.notifications') }}

                    <BadgeCounter v-if="notificationsCount.raw" v-bind:count="notificationsCount.formatted"></BadgeCounter>
                </span>
            </div>
        </div>
        <div class="block">
            <RouterLink v-bind:to="{ name: 'messenger_index' }" v-slot="{ isActive }" class="block">
                <div class="flex items-center"  v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="message-chat-circle" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.messages') }}

                        <BadgeCounter v-if="inboxCount.raw" v-bind:count="inboxCount.formatted"></BadgeCounter>
                    </span>
                </div>
            </RouterLink>
        </div>
        <div class="block" v-if="$config('features.marketplace.enabled')">
            <RouterLink v-bind:to="{ name: 'marketplace_index' }" v-slot="{ isActive }" class="block">
                <div class="flex items-center" v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="shopping-bag-03" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.marketplace') }}
                    </span>
                </div>
            </RouterLink>
        </div>
        <div class="block" v-if="$config('features.jobs.enabled')">
            <RouterLink v-bind:to="{ name: 'jobs_index' }" v-slot="{ isActive }" class="block">
                <div  class="flex items-center" v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="briefcase-01" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.jobs') }}
                    </span>
                </div>
            </RouterLink>
        </div>
        
        <div class="block">
            <RouterLink v-bind:to="{ name: 'profile_index', params: { id: userData.username } }" v-slot="{ isActive }" class="block">
                <div  class="flex items-center sidenav-inactive">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="user-01" type="line"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.my_profile') }}
                    </span>
                </div>
            </RouterLink>
        </div>
        <div class="block pl-icon-normal pr-6">
            <span class="block bg-bord-sc h-px mx-3"></span>
        </div>
        <div class="block">
            <NavbarDropdown></NavbarDropdown>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, defineAsyncComponent, onMounted, onUnmounted } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useNotificationsStore } from '@D/store/notifications/notifications.store.js';
    import { useInboxStore } from '@D/store/chats/inbox.store.js';
    import { useTimelineStore } from '@D/store/timeline/timeline.store.js';
    import { useExplorePostsStore } from '@D/store/explore/posts.store.js';
    import { useExplorePeopleStore } from '@D/store/explore/people.store.js';
    import useToastNotificationStore from '@D/store/toast/toast.store.js';
    import { colibriSounds } from '@/kernel/services/sounds/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import BadgeCounter from '@D/components/general/counters/BadgeCounter.vue';
    import BRD from '@/kernel/websockets/brd/index.js';

    export default defineComponent({
        setup: function() {
            const authStore = useAuthStore();
            const notificationsStore = useNotificationsStore();
            const inboxStore = useInboxStore();
            const timelineStore = useTimelineStore();
            const explorePostsStore = useExplorePostsStore();
            const explorePeopleStore = useExplorePeopleStore();
            const toastStore = useToastNotificationStore();
            let isListening = false;
            let unreadRefreshTimer = null;
            let navigationWarmHandle = null;
            let navigationWarmHandleIsIdle = false;
            const notificationsCount = computed(() => {
                return notificationsStore.unreadCount;
            });

            const inboxCount = computed(() => {
                return inboxStore.unreadCount;
            });

            const getAuthChannel = () => {
                return BRD.getChannel('AUTH_USER', [authStore.userData.id]);
            };

            const getChatToastText = (messageData = {}) => {
                let senderName = messageData.relations?.user?.name || __t('chat.new_message_prefix');
                let previewText = inboxStore.getMessagePreview(messageData, false) || __t('labels.message');

                return `${senderName}: ${previewText}`;
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

            const syncSidebarNotifications = (event) => {
                if(event.type === 'chat.notification') {
                    let shouldNotify = inboxStore.handleIncomingMessageNotification(event.data, authStore.userData.id);

                    if(shouldNotify) {
                        toastStore.add(getChatToastText(event.data), 4000);
                    }
                }
                else {
                    notificationsStore.setUnreadNotificationsCount(event.data);
                    colibriEventBus.emit('notifications:received');
                }

                if(colibriSounds.isNotificationsSoundEnabled()) {
                    if(event.type === 'chat.notification') {
                        colibriSounds.backgroundChatMessageReceived();
                    }
                    else {
                        colibriSounds.notificationReceived();
                    }
                }
            };

            const attachRealtimeListener = () => {
                if(isListening || ! window.ColibriBRD || ! authStore.userData) {
                    return;
                }

                ColibriBRD.private(getAuthChannel()).notification(syncSidebarNotifications);
                isListening = true;
            };

            const detachRealtimeListener = () => {
                if(! isListening || ! window.ColibriBRD || ! authStore.userData) {
                    return;
                }

                ColibriBRD.private(getAuthChannel()).stopListeningForNotification(syncSidebarNotifications);
                isListening = false;
            };

            const handleWSStatus = (event) => {
                if(event.detail.connected) {
                    attachRealtimeListener();
                    refreshUnreadState(150);
                }
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

                    timelineStore.initialLoad();
                    explorePostsStore.warmFirstPage();
                    explorePeopleStore.warmFirstPage();

                    import('@D/views/home/HomeIndex.vue');
                    import('@D/views/explore/children/posts/ExplorePosts.vue');
                    import('@D/views/explore/children/people/ExplorePeople.vue');
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

                notificationsStore.fetchUnreadCount();
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
                notificationsCount: notificationsCount,
                inboxCount: inboxCount,
                userData: computed(() => {
                    return authStore.userData;
                }),
                openNotificationsModal: () => {
                    notificationsStore.openNotifications();
                }
            };
        },
        components: {
            NavbarDropdown: defineAsyncComponent(() => {
                return import('@D/components/layout/parts/navbar/NavbarDropdown.vue');
            }),
            BadgeCounter: BadgeCounter
        }
    });
</script>
