<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.active_sessions')"></Toolbar>

	<div v-if="! state.isLoading" class="px-4 pb-6">
		<p class="mb-6 text-par-m leading-5 text-lab-sc break-words">
			{{ $t('settings.forms.active_sessions.page_desc') }}
		</p>

		<div v-if="sessionsList.length" class="space-y-6">
			<div v-if="currentSession">
				<h6 class="mb-2 text-par-m text-lab-sc font-medium break-words">
					{{ $t('settings.forms.active_sessions.current_session') }}
				</h6>

				<div class="overflow-hidden rounded-2xl border border-bord-pr bg-bg-pr">
					<div class="flex items-start gap-3 px-4 py-4">
						<span class="flex-center size-normal-avatar shrink-0 rounded-full bg-fill-tr">
							<SvgIcon
								type="line"
								v-bind:name="currentSession.is_desktop ? 'monitor-04' : 'phone-01'"
							classes="size-icon-small text-brand-900"></SvgIcon>
						</span>

						<span class="min-w-0 flex-1">
							<span class="block text-par-m font-semibold leading-5 text-lab-pr2 break-words">
								{{ sessionTitle(currentSession) }}
							</span>
							<span v-if="currentSession.location" class="mt-1 block text-par-s leading-4 text-lab-sc break-words">
								{{ sessionLocation(currentSession) }}
							</span>
							<span v-else class="mt-1 block text-par-s leading-4 text-orange-400 break-words">
								{{ $t('settings.forms.active_sessions.location_unknown') }}
							</span>
						</span>

						<span class="shrink-0 max-w-20 text-right text-par-s leading-4" v-bind:class="currentSession.is_online ? 'text-green-500' : 'text-lab-sc'">
							{{ currentSession.is_online ? $t('labels.online') : currentSession.last_online }}
						</span>
					</div>
				</div>
			</div>

			<div>
				<h6 class="mb-2 text-par-m text-lab-sc font-medium break-words">
					{{ $t('settings.forms.active_sessions.all_sessions') }}
				</h6>

				<div class="overflow-hidden rounded-2xl border border-bord-pr bg-bg-pr divide-y divide-bord-pr">
					<div
						v-for="sessionItem in sessionsList"
						v-bind:key="sessionKey(sessionItem)"
					class="flex items-start gap-3 px-4 py-4">
						<span class="flex-center size-normal-avatar shrink-0 rounded-full bg-fill-tr">
							<SvgIcon
								type="line"
								v-bind:name="sessionItem.is_desktop ? 'monitor-04' : 'phone-01'"
							classes="size-icon-small text-brand-900"></SvgIcon>
						</span>

						<span class="min-w-0 flex-1">
							<span class="block text-par-m font-semibold leading-5 text-lab-pr2 break-words">
								{{ sessionTitle(sessionItem) }}
							</span>
							<span v-if="sessionItem.location" class="mt-1 block text-par-s leading-4 text-lab-sc break-words">
								{{ sessionLocation(sessionItem) }}
							</span>
							<span v-else class="mt-1 block text-par-s leading-4 text-orange-400 break-words">
								{{ $t('settings.forms.active_sessions.location_unknown') }}
							</span>
						</span>

						<span class="shrink-0 max-w-20 text-right text-par-s leading-4" v-bind:class="sessionItem.is_online ? 'text-green-500' : 'text-lab-sc'">
							{{ sessionItem.is_online ? $t('labels.online') : sessionItem.last_online }}
						</span>
					</div>

					<button
						v-if="sessionsList.length > 1"
						v-on:click="terminateOtherSessions"
						type="button"
					class="flex min-h-20 w-full items-center gap-3 px-4 py-4 text-left">
						<span class="flex-center size-normal-avatar shrink-0 rounded-full bg-fill-tr">
							<SvgIcon type="solid" name="log-out-01" classes="size-icon-small text-red-900"></SvgIcon>
						</span>

						<span class="min-w-0 flex-1">
							<span class="block text-par-m font-semibold leading-5 text-red-900 break-words">
								{{ $t('settings.forms.active_sessions.terminate_other_sessions') }}
							</span>
							<span class="mt-1 block text-par-s leading-4 text-lab-sc break-words">
								{{ $t('settings.forms.active_sessions.terminate_other_sessions_helper') }}
							</span>
						</span>

						<span class="shrink-0 size-6 text-lab-sc">
							<SvgIcon name="chevron-right"></SvgIcon>
						</span>
					</button>
				</div>
			</div>
		</div>

		<div v-else class="rounded-2xl border border-bord-pr bg-fill-qt p-6 text-center">
			<div class="mx-auto mb-3 size-10 text-lab-sc">
				<SvgIcon name="monitor-04" type="line"></SvgIcon>
			</div>
			<p class="text-par-m text-lab-sc">
				{{ $t('empty_state.no_data') }}
			</p>
		</div>
	</div>

	<div v-else class="flex-center h-64">
		<PrimarySpinAnimation></PrimarySpinAnimation>
	</div>
</template>

<script>
	import { defineComponent, reactive, onMounted, ref, computed } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isLoading: true
			});

			const sessionsList = ref([]);

			const fetchUserSessions = async () => {
				state.isLoading = true;

				await colibriAPI().userSettings().getFrom('sessions').then((response) => {
					sessionsList.value = response.data.data.sessions;
				}).catch((error) => {
					if(error.response) {
						toastError(error.response.data.message);
					}
				});

				state.isLoading = false;
			};

			onMounted(() => {
				fetchUserSessions();
			});

			return {
				state: state,
				sessionsList: sessionsList,
				currentSession: computed(() => {
					return sessionsList.value.find((session) => {
						return session.is_current === true;
					});
				}),
				sessionTitle: (sessionData) => {
					return `${sessionData.os.name} ${sessionData.os.version}, ${sessionData.ip_address}, ${sessionData.browser.name} ${sessionData.browser.version}`;
				},
				sessionLocation: (sessionData) => {
					return `${sessionData.location.city}, ${sessionData.location.region}, ${sessionData.location.country}`;
				},
				sessionKey: (sessionData) => {
					return `${sessionData.ip_address}-${sessionData.os.name}-${sessionData.browser.name}-${sessionData.last_online}`;
				},
				terminateOtherSessions: async () => {
					state.isLoading = true;

					await colibriAPI().userSettings().delete('sessions/terminate/other').then(() => {
						fetchUserSessions();
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});

					state.isLoading = false;
				}
			};
		},
		components: {
			Toolbar: Toolbar
		}
	});
</script>
