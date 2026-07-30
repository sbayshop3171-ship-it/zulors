<template>
	<template v-if="appLoading">
		<div class="zulors-boot-shell zulors-boot-shell--mobile" role="status" aria-label="Loading Zulors">
            <header class="zulors-boot-mobile-header">
                <span class="zulors-boot-line zulors-boot-line--short" aria-hidden="true"></span>
                <span class="zulors-boot-title">Zulors</span>
                <span class="zulors-boot-dot" aria-hidden="true"></span>
            </header>

            <main class="zulors-boot-feed">
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
            </main>
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
	import { useRoute, useRouter } from 'vue-router';
	import { Layouts } from '@M/core/constants/layouts.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import ApplicationMainLayout from '@M/layouts/ApplicationMainLayout.vue';
	import PostEditorLayout from '@M/layouts/PostEditorLayout.vue';
	import MessengerLayout from '@M/layouts/MessengerLayout.vue';
	import FlatLayout from '@M/layouts/FlatLayout.vue';

	export default defineComponent({
		setup: function() {
			const route = useRoute();
			const router = useRouter();
			const appLoading = ref(true);
			const appStore = useAppStore();
			const authStore = useAuthStore();
			let realtimeChannel = null;

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

					colibriEventBus.on('auth:logout', logoutUser);
					setupRealtimePostUpdates();
                } catch (error) {
                    console.error('Failed to bootstrap mobile application', error);

                    appLoading.value = false;

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
