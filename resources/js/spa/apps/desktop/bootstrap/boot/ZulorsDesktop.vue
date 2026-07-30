<template>
    <template v-if="appLoading">
        <div class="zulors-boot-shell zulors-boot-shell--desktop" role="status" aria-label="Loading Zulors">
            <aside class="zulors-boot-desktop-sidebar" aria-hidden="true">
                <div class="zulors-boot-dot"></div>
                <div class="zulors-boot-nav-item"></div>
                <div class="zulors-boot-nav-item"></div>
                <div class="zulors-boot-nav-item"></div>
                <div class="zulors-boot-nav-item"></div>
                <div class="zulors-boot-nav-item"></div>
            </aside>

            <main class="zulors-boot-desktop-main">
                <header class="zulors-boot-desktop-header">Home</header>

                <div class="zulors-boot-feed">
                    <div class="zulors-boot-story" aria-hidden="true">
                        <span class="zulors-boot-avatar"></span>
                        <span class="zulors-boot-line zulors-boot-line--short"></span>
                    </div>

                    <article class="zulors-boot-post" aria-hidden="true">
                        <div class="zulors-boot-post-head">
                            <span class="zulors-boot-avatar"></span>
                            <span class="zulors-boot-lines">
                                <span class="zulors-boot-line zulors-boot-line--medium"></span>
                                <span class="zulors-boot-line zulors-boot-line--short"></span>
                            </span>
                        </div>
                        <div class="zulors-boot-media"></div>
                        <div class="zulors-boot-actions">
                            <span class="zulors-boot-action"></span>
                            <span class="zulors-boot-action"></span>
                            <span class="zulors-boot-action"></span>
                        </div>
                    </article>
                </div>
            </main>

            <aside class="zulors-boot-desktop-aside" aria-hidden="true">
                <div class="zulors-boot-title">Zulors</div>
                <div class="zulors-boot-suggestion">
                    <span class="zulors-boot-avatar"></span>
                    <span class="zulors-boot-line"></span>
                </div>
                <div class="zulors-boot-suggestion">
                    <span class="zulors-boot-avatar"></span>
                    <span class="zulors-boot-line"></span>
                </div>
                <div class="zulors-boot-suggestion">
                    <span class="zulors-boot-avatar"></span>
                    <span class="zulors-boot-line"></span>
                </div>
            </aside>
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
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import BRD from '@/kernel/websockets/brd/index.js';

    import { Layouts } from '@D/core/constants/layouts.js';
    
    import ApplicationMainLayout from '@D/layouts/ApplicationMainLayout.vue';
    import NetworkStatusBar from '@D/components/layout/parts/network/NetworkStatusBar.vue';
    
    export default defineComponent({
        setup: function(_, context) {
            const appLoading = ref(true);
            const route = useRoute();
            const router = useRouter();
            const appStore = useAppStore();
            const authStore = useAuthStore();
            let realtimeChannel = null;

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

            onMounted(async () => {
                try {
                    const isBootstrapped = await appStore.bootstrapApplication();

                    if (! isBootstrapped) {
                        appLoading.value = false;
                        return;
                    }

                    if(route.meta.auth && ! authStore.authCheck) {
                        window.location.href = embedder('routes.user_auth_index');
                        return;
                    }

                    appLoading.value = false;

                    setupInteractionListeners();
                    setupRealtimePostUpdates();
                } catch (error) {
                    console.error('Failed to bootstrap desktop application', error);

                    appLoading.value = false;

                    if (router.currentRoute.value.name !== 'bootstrap_error') {
                        await router.push({ name: 'bootstrap_error' });
                    }
                }
            });

            onUnmounted(() => {
                removeInteractionListeners();

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
