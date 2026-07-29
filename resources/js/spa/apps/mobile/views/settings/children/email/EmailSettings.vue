<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.email_settings')"></Toolbar>

	<div v-if="! state.isLoading" class="px-4 pb-6">
		<p class="mb-6 text-par-m leading-5 text-lab-sc break-words">
			{{ $t('settings.forms.email_address.page_desc') }}
		</p>

		<form v-on:submit.prevent="submitForm" class="space-y-6">
			<TextInput
				inputType="email"
				v-bind:textLength="validationRules.email.max"
				v-model.trim="formData.email"
				v-bind:inputErrors="state.formErrors.email"
				v-bind:labelText="$t('settings.forms.email_address.email_address')"
			v-bind:placeholder="$t('settings.forms.email_address.email_address_placeholder')">
				<template v-slot:feedbackInfo>
					{{ $t('settings.forms.email_address.email_address_helper') }}
				</template>
			</TextInput>

			<label class="flex min-h-20 items-center gap-3 rounded-2xl border border-bord-pr bg-bg-pr px-4 py-3">
				<span class="shrink-0 size-6 text-lab-pr">
					<SvgIcon name="shield-01" type="line"></SvgIcon>
				</span>

				<span class="min-w-0 flex-1">
					<span class="block text-par-m font-bold text-lab-pr2 break-words">
						{{ $t('labels.privacy') }}
					</span>
					<span class="block text-par-m leading-5 text-lab-sc break-words">
						{{ $t('settings.forms.email_address.privacy_helper') }}
					</span>
				</span>

				<span class="shrink-0">
					<SecondarySwitcher v-model="formData.email_privacy"></SecondarySwitcher>
				</span>
			</label>

			<div class="sticky bottom-0 bg-bg-pr pt-2 pb-safe-bottom">
				<PrimaryPillButton
					v-bind:buttonFluid="true"
					v-bind:isDisabled="isSubmitDenied"
					v-bind:loading="state.isSubmitting"
					buttonRole="accent"
					buttonType="submit"
				v-bind:buttonText="$t('labels.save_changes')"></PrimaryPillButton>
			</div>
		</form>
	</div>

	<div v-else class="flex-center h-64">
		<PrimarySpinAnimation></PrimarySpinAnimation>
	</div>
</template>

<script>
	import { defineComponent, reactive, ref, onMounted, computed } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import TextInput from '@M/components/forms/TextInput.vue';
	import SecondarySwitcher from '@M/components/inter-ui/switchers/SecondarySwitcher.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const authStore = useAuthStore();
			const router = useRouter();
			const validationRules = ref({
				email: {
					max: 62
				}
			});

			const state = reactive({
				isLoading: true,
				isSubmitting: false,
				formErrors: {
					email: []
				}
			});

			const formData = ref({
				email: authStore.userData.email || '',
				email_privacy: false
			});

			onMounted(async () => {
				await colibriAPI().userSettings().getFrom('email/settings').then((response) => {
					let settings = response.data.data;

					validationRules.value = settings.validation_rules;
					formData.value.email = settings.email || '';
					formData.value.email_privacy = settings.privacy_settings.email_privacy;
				}).catch((error) => {
					if(error.response) {
						toastError(error.response.data.message);
					}
				});

				state.isLoading = false;
			});

			return {
				formData: formData,
				validationRules: validationRules,
				state: state,
				isSubmitDenied: computed(() => {
					return formData.value.email.length === 0;
				}),
				submitForm: async () => {
					if (state.isSubmitting === false) {
						state.isSubmitting = true;

						Object.keys(state.formErrors).forEach((key) => {
							state.formErrors[key] = [];
						});

						await colibriAPI().userSettings().with(formData.value).putTo('email/update').then((response) => {
							if (response.data.data.confirmation_required) {
								router.push({
									name: 'settings_email_confirm' 
								});
							}
							else{
								toastSuccess(__t('toast.forms.changes_saved'));
							}
						}).catch((error) => {
							if (error.response) {
								toastError(error.response.data.message);

								if(error.response.data.errors) {
									Object.keys(error.response.data.errors).forEach((key) => {
										state.formErrors[key] = error.response.data.errors[key];
									});
								}
							}
						});

						state.isSubmitting = false;
					}
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			TextInput: TextInput,
			SecondarySwitcher: SecondarySwitcher,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
