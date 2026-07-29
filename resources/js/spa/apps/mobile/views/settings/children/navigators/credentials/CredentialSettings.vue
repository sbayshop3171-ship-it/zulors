<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.account_credentials')"></Toolbar>

	<div v-if="! state.isLoading" class="pb-6">
		<div class="px-4 mb-6">
			<p class="text-par-m leading-5 text-lab-sc break-words">
				{{ $t('settings.forms.account_credentials.page_desc') }}
			</p>
		</div>

		<div class="mx-4 mb-6 overflow-hidden rounded-2xl border border-bord-pr bg-bg-pr divide-y divide-bord-pr">
			<SectionLink
				iconName="mail-04"
				v-bind:link="{ name: 'settings_email' }"
				v-bind:captionText="formData.email"
			v-bind:titleText="$t('settings.navigators.email')"></SectionLink>

			<SectionLink
				iconName="phone-01"
				v-bind:link="{ name: 'settings_phone' }"
				v-bind:captionText="formData.phone || $t('settings.navigators.phone_caption')"
			v-bind:titleText="$t('settings.navigators.phone')"></SectionLink>

			<SectionLink
				iconName="lock-03"
				v-bind:link="{ name: 'settings_password' }"
				v-bind:captionText="$t('settings.navigators.password_caption')"
			v-bind:titleText="$t('settings.navigators.password')"></SectionLink>
		</div>

		<div class="px-4 mb-2">
			<h6 class="text-par-m text-lab-sc font-medium break-words">
				{{ $t('settings.navigators.security_check') }}
			</h6>
		</div>

		<div class="mx-4 overflow-hidden rounded-2xl border border-bord-pr bg-bg-pr divide-y divide-bord-pr">
			<SectionLink
				iconName="monitor-04"
				v-bind:link="{ name: 'settings_sessions' }"
				v-bind:captionText="$t('settings.navigators.active_sessions_caption')"
			v-bind:titleText="$t('settings.navigators.active_sessions')"></SectionLink>

			<label class="flex min-h-20 items-center gap-3 px-4 py-3">
				<span class="shrink-0 size-6 text-lab-pr">
					<SvgIcon name="log-in-02" type="line"></SvgIcon>
				</span>

				<span class="min-w-0 flex-1">
					<span class="block text-par-m font-bold text-lab-pr2 break-words">
						{{ $t('settings.navigators.login_notification') }}
					</span>
					<span class="block text-par-m leading-5 text-lab-sc break-words">
						{{ $t('settings.navigators.login_notification_caption') }}
					</span>
				</span>

				<span class="shrink-0">
					<span v-if="state.isSubmitting" class="inline-block colibri-primary-animation"></span>
					<SecondarySwitcher
						v-else
						v-bind:modelValue="formData.login_notification"
					v-on:update:modelValue="updateLoginNotification"></SecondarySwitcher>
				</span>
			</label>
		</div>
	</div>

	<div v-else class="flex-center h-64">
		<PrimarySpinAnimation></PrimarySpinAnimation>
	</div>
</template>

<script>
	import { defineComponent, ref, reactive, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import SectionLink from '@M/components/forms/SectionLink.vue';
	import SecondarySwitcher from '@M/components/inter-ui/switchers/SecondarySwitcher.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isLoading: true,
				isSubmitting: false
			});

			const formData = ref({
				email: '',
				phone: '',
				login_notification: false
			});

			onMounted(async () => {
				await colibriAPI().userSettings().getFrom('account/credentials/settings').then((response) => {
					let settings = response.data.data;

					formData.value.email = settings.email;
					formData.value.phone = settings.phone;
					formData.value.login_notification = settings.security_settings.login_notification;
				}).catch((error) => {
					if(error.response) {
						toastError(error.response.data.message);
					}
				});

				state.isLoading = false;
			});

			return {
				state: state,
				formData: formData,
				updateLoginNotification: async (loginNotification) => {
					if(state.isSubmitting === false) {
						let previousValue = formData.value.login_notification;

						formData.value.login_notification = loginNotification;
						state.isSubmitting = true;

						await colibriAPI().userSettings().with({
							login_notification: loginNotification
						}).putTo('notifications/login/update').then(() => {
							toastSuccess(__t('toast.forms.changes_saved'));
						}).catch((error) => {
							formData.value.login_notification = previousValue;

							if(error.response) {
								toastError(error.response.data.message);
							}
						});

						state.isSubmitting = false;
					}
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			SectionLink: SectionLink,
			SecondarySwitcher: SecondarySwitcher
		}
	});
</script>
