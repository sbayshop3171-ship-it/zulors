<template>
    <template v-if="appLoading">
        <div class="zulors-boot-shell zulors-boot-shell--desktop" role="status" aria-label="Loading Zulors">
            <span class="zulors-boot-corner zulors-boot-corner--left">{{ $t('labels.hi_there') }}</span>
            <span class="zulors-boot-corner zulors-boot-corner--right">{{ $t('labels.one_moment') }}...</span>
            <img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="zulors-boot-logo">
        </div>
    </template>
    <template v-else>
        <ApplicationMainLayout v-if="isMainLayout"></ApplicationMainLayout>

        <ApplicationMessengerLayout v-else-if="isMessengerLayout"></ApplicationMessengerLayout>

        <ApplicationFlatLayout v-else-if="isFlatLayout"></ApplicationFlatLayout>

        <ApplicationStoriesLayout v-else-if="isStoriesLayout"></ApplicationStoriesLayout>

        <ApplicationInfoLayout v-else-if="isInfoLayout"></ApplicationInfoLayout> 
    </template>

    <NetworkStatusBar></NetworkStatusBar>
</template>

<script>
    import { defineComponent, computed, onMounted, onUnmounted, ref, defineAsyncComponent } from 'vue';
    import { useRoute, useRouter } from 'vue-router';
    import { useAppStore } from '@D/store/app/app.store.js';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useTimelineStore } from '@D/store/timeline/timeline.store.js';
    import { useExploreReelsStore } from '@D/store/explore/reels.store.js';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import BRD from '@/kernel/websockets/brd/index.js';

    import { Layouts } from '@D/core/constants/layouts.js';
    
    import ApplicationMainLayout from '@D/layouts/ApplicationMainLayout.vue';
    import NetworkStatusBar from '@D/components/layout/parts/network/NetworkStatusBar.vue';

    const maxBootScreenMs = 320;
    
    export default defineComponent({
        setup: function(_, context) {
            const route = useRoute();
            const router = useRouter();
            const appStore = useAppStore();
            const authStore = useAuthStore();
            const timelineStore = useTimelineStore();
            const reelsStore = useExploreReelsStore();
            const hasCachedBootstrap = appStore.hydrateCachedBootstrap();
            const appLoading = ref(! hasCachedBootstrap);
            let realtimeChannel = null;
            let appShellReady = false;
            let bootDeadlineTimer = null;
            let reelsWarmupHandle = null;
            let reelsWarmupScheduled = false;

            const layoutType = computed(() => {
                return route.meta.layout;
            });

            window.userInteracted = false;

            const handleUserInteraction = () => {
                window.userInteracted = true;
                removeInteractionListeners();
            };

            const removeInteractionListeners = () => {
                window.removeEventListener('click', handleUserInteraction);
                window.removeEventListener('keydown', handleUserInteraction);
                window.removeEventListener('mousemove', handleUserInteraction);
                window.removeEventListener('touchstart', handleUserInteraction);
            };

            const setupInteractionListeners = () => {
                window.addEventListener('click', handleUserInteraction);
                window.addEventListener('keydown', handleUserInteraction);
                window.addEventListener('mousemove', handleUserInteraction);
                window.addEventListener('touchstart', handleUserInteraction);
            };

            const fetchUpdatedPost = async (event) => {
                const hashId = event?.data?.hash_id;

                if(! hashId) {
                    return false;
                }

                try {
                    const response = await colibriAPI().userTimeline().getFrom(`post/${hashId}`);

                    colibriEventBus.emit('timeline:post-updated', response.data.data.post);
                } catch (error) {
                    //
                }
            };

            const setupRealtimePostUpdates = () => {
                if(window.ColibriBRD && ! realtimeChannel) {
                    realtimeChannel = window.ColibriBRD.channel(BRD.getChannel('PUBLIC_TIMELINE'));
                    realtimeChannel.listen(BRD.getEvent('TIMELINE_POST_UPDATED'), fetchUpdatedPost);
                }
            };

            const revealAppShell = () => {
                if(appShellReady) {
                    return;
                }

                appShellReady = true;
                primeHomeFeed();
                appLoading.value = false;

                setupInteractionListeners();
                setupRealtimePostUpdates();
                scheduleReelsWarmup();
            };

            const primeHomeFeed = () => {
                if(authStore.authCheck) {
                    timelineStore.warmFirstPage().catch(() => {});
                }
            };

            const clearReelsWarmup = () => {
                if(! reelsWarmupHandle || typeof window === 'undefined') {
                    return;
                }

                if(typeof reelsWarmupHandle === 'number') {
                    window.clearTimeout(reelsWarmupHandle);
                }
                else if('cancelIdleCallback' in window) {
                    window.cancelIdleCallback(reelsWarmupHandle);
                }

                reelsWarmupHandle = null;
            };

            const scheduleReelsWarmup = () => {
                if(reelsWarmupScheduled || ! authStore.authCheck || typeof window === 'undefined') {
                    return;
                }

                reelsWarmupScheduled = true;

                const runWarmup = () => {
                    reelsWarmupHandle = null;
                    reelsStore.prefetchFirstPage().catch(() => {});
                };

                if('requestIdleCallback' in window) {
                    reelsWarmupHandle = window.requestIdleCallback(runWarmup, {
                        timeout: 1800
                    });
                }
                else {
                    reelsWarmupHandle = window.setTimeout(runWarmup, 1100);
                }
            };

            const finishBootstrap = async (isBootstrapped) => {
                if(bootDeadlineTimer) {
                    window.clearTimeout(bootDeadlineTimer);
                    bootDeadlineTimer = null;
                }

                if (! isBootstrapped) {
                    revealAppShell();
                    return;
                }

                if(route.meta.auth && ! authStore.authCheck) {
                    window.location.href = embedder('routes.user_auth_index');
                    return;
                }

                revealAppShell();
                setupRealtimePostUpdates();
            };

            onMounted(async () => {
                if(hasCachedBootstrap || authStore.authCheck) {
                    revealAppShell();
                }

                bootDeadlineTimer = window.setTimeout(() => {
                    revealAppShell();
                }, maxBootScreenMs);

                try {
                    const isBootstrapped = await appStore.bootstrapApplication();

                    await finishBootstrap(isBootstrapped);
                } catch (error) {
                    if(bootDeadlineTimer) {
                        window.clearTimeout(bootDeadlineTimer);
                        bootDeadlineTimer = null;
                    }

                    console.error('Failed to bootstrap desktop application', error);

                    revealAppShell();

                    if (router.currentRoute.value.name !== 'bootstrap_error') {
                        await router.push({ name: 'bootstrap_error' });
                    }
                }
            });

            onUnmounted(() => {
                if(bootDeadlineTimer) {
                    window.clearTimeout(bootDeadlineTimer);
                }

                removeInteractionListeners();
                clearReelsWarmup();

                if(realtimeChannel) {
                    realtimeChannel.stopListening(BRD.getEvent('TIMELINE_POST_UPDATED'));
                }
            });

            return {
                appLoading: appLoading,
                isMainLayout: computed(() => {
                    return layoutType.value == Layouts.MAIN;
                }),
                isStoriesLayout: computed(() => {
                    return layoutType.value == Layouts.STORIES;
                }),
                isInfoLayout: computed(() => {
                    return layoutType.value == Layouts.INFO;
                }),
                isFlatLayout: computed(() => {
                    return layoutType.value == Layouts.FLAT;
                }),
                isMessengerLayout: computed(() => {
                    return layoutType.value == Layouts.MESSENGER;
                })
            }
        },
        components: {
            NetworkStatusBar: NetworkStatusBar,
            ApplicationMainLayout: ApplicationMainLayout,
            ApplicationStoriesLayout: defineAsyncComponent(() => {
                return import('@D/layouts/ApplicationStoriesLayout.vue');
            }),
            ApplicationInfoLayout: defineAsyncComponent(() => {
                return import('@D/layouts/ApplicationInfoLayout.vue');
            }),
            ApplicationFlatLayout: defineAsyncComponent(() => {
                return import('@D/layouts/ApplicationFlatLayout.vue');
            }),
            ApplicationMessengerLayout: defineAsyncComponent(() => {
                return import('@D/layouts/ApplicationMessengerLayout.vue');
            })
        }
    });
</script>
