<template>
	<template v-if="appLoading">
		<div class="zulors-boot-shell zulors-boot-shell--mobile" role="status" aria-label="Loading Zulors">
            <img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="zulors-boot-logo">
        </div>
	</template>
	<template v-else>
		<ApplicationMainLayout v-if="isMainLayout"></ApplicationMainLayout>

		<FlatLayout v-if="isFlatLayout"></FlatLayout>

		<PostEditorLayout v-if="isPostEditorLayout"></PostEditorLayout>

		<MessengerLayout v-if="isMessengerLayout"></MessengerLayout>
	</template>
</template>

<script>
	import { defineComponent, computed, ref, onMounted, onUnmounted } from 'vue';
	import { useAppStore } from '@M/store/app/app.store.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useExploreReelsStore } from '@M/store/explore/reels.store.js';
	import { useRoute, useRouter } from 'vue-router';
	import { Layouts } from '@M/core/constants/layouts.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import ApplicationMainLayout from '@M/layouts/ApplicationMainLayout.vue';
	import PostEditorLayout from '@M/layouts/PostEditorLayout.vue';
	import MessengerLayout from '@M/layouts/MessengerLayout.vue';
	import FlatLayout from '@M/layouts/FlatLayout.vue';

	const maxBootScreenMs = 900;

	export default defineComponent({
		setup: function() {
			const route = useRoute();
			const router = useRouter();
			const appLoading = ref(true);
			const appStore = useAppStore();
			const authStore = useAuthStore();
			const reelsStore = useExploreReelsStore();
			let realtimeChannel = null;
			let appShellReady = false;
			let bootDeadlineTimer = null;
			let reelsWarmupHandle = null;
			let reelsWarmupScheduled = false;

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
				appLoading.value = false;

				colibriEventBus.on('auth:logout', logoutUser);
				setupRealtimePostUpdates();
				scheduleReelsWarmup();
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
				appStore.hydrateCachedBootstrap();

				if(authStore.authCheck) {
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

                    console.error('Failed to bootstrap mobile application', error);

                    revealAppShell();

                    if (router.currentRoute.value.name !== 'bootstrap_error') {
                        await router.push({ name: 'bootstrap_error' });
                    }
                }
			});

			const logoutUser = () => {
				colibriEventBus.emit('confirmation-modal:open', {
					title: __t('prompt.logout.title'),
					description: __t('prompt.logout.description'),
					confirmButtonText: __t('prompt.logout.confirm'),
					onConfirm: () => {
						authStore.logoutUser();
						window.location.href = embedder('routes.user_auth_index');
					}
				});
			}

			onUnmounted(() => {
				if(bootDeadlineTimer) {
					window.clearTimeout(bootDeadlineTimer);
				}

				clearReelsWarmup();

				colibriEventBus.off('auth:logout', logoutUser);

				if(realtimeChannel) {
                    realtimeChannel.stopListening(BRD.getEvent('TIMELINE_POST_UPDATED'));
                }
			});

			const layoutType = computed(() => {
                return route.meta.layout;
            });

			return {
				appLoading: appLoading,
				isMainLayout: computed(() => {
					return layoutType.value == Layouts.MAIN;
				}),
				isPostEditorLayout: computed(() => {
					return layoutType.value == Layouts.POST_EDITOR;
				}),
				isMessengerLayout: computed(() => {
					return layoutType.value == Layouts.MESSENGER;
				}),
				isFlatLayout: computed(() => {
					return layoutType.value == Layouts.FLAT;
				})
			};
		},
		components: {
			ApplicationMainLayout: ApplicationMainLayout,
			PostEditorLayout: PostEditorLayout,
			MessengerLayout: MessengerLayout,
			FlatLayout: FlatLayout
		}
	});
</script>
