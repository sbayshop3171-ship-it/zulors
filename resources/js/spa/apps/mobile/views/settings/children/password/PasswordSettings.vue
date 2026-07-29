<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.password_settings')"></Toolbar>

	<div v-if="! state.isLoading" class="px-4 pb-6">
		<p class="mb-6 text-par-m leading-5 text-lab-sc break-words" v-html="$t('settings.forms.password_settings.page_desc')"></p>
		
		<form v-on:submit.prevent="submitForm" class="space-y-6">
			<TextInput
				v-model="formData.password"
				v-bind:inputErrors="state.formErrors.password"
				v-bind:isPassword="true"
				v-bind:labelText="$t('settings.forms.password_settings.current_password')"
			v-bind:placeholder="$t('settings.forms.password_settings.password_placeholder')">
				<template v-slot:feedbackInfo>
					{{ $t('settings.forms.password_settings.current_password_helper') }}
				</template>
			</TextInput>

			<TextInput
				v-model="formData.new_password"
				v-bind:textLength="validationRules.password.max"
				v-bind:isPassword="true"
				v-bind:inputErrors="state.formErrors.new_password"
				v-bind:labelText="$t('settings.forms.password_settings.new_password')"
			v-bind:placeholder="$t('settings.forms.password_settings.password_placeholder')">
				<template v-slot:feedbackInfo>
					{{ $t('settings.forms.password_settings.new_password_helper') }}
				</template>
			</TextInput>

			<button
				v-on:click="generatePassword"
				v-bind:disabled="state.isGenerating"
				type="button"
			class="inline-flex min-h-11 max-w-full items-center gap-2 rounded-full border border-bord-pr bg-fill-qt px-4 text-par-m font-semibold text-brand-900 disabled:opacity-60">
				<span class="size-5 shrink-0">
					<SvgIcon name="star-06" type="solid"></SvgIcon>
				</span>
				<span class="min-w-0 truncate">
					{{ $t('settings.forms.password_settings.generate_password') }}
				</span>
				<span v-if="state.isGenerating" class="inline-block colibri-primary-animation"></span>
			</button>

			<label class="flex min-h-20 items-center gap-3 rounded-2xl border border-bord-pr bg-bg-pr px-4 py-3">
				<span class="shrink-0 size-6 text-lab-pr">
					<SvgIcon name="hand" type="line"></SvgIcon>
				</span>

				<span class="min-w-0 flex-1">
					<span class="block text-par-m font-bold text-lab-pr2 break-words">
						{{ $t('labels.security') }}
					</span>
					<span class="block text-par-m leading-5 text-lab-sc break-words" v-html="$t('settings.forms.password_settings.security_helper')"></span>
				</span>

				<span class="shrink-0">
					<SecondarySwitcher v-model="formData.logout_other_devices"></SecondarySwitcher>
				</span>
			</label>

			<div class="sticky bottom-0 bg-bg-pr pt-2 pb-safe-bottom">
				<PrimaryPillButton
					v-bind:buttonFluid="true"
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
	import { defineComponent, ref, reactive, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import TextInput from '@M/components/forms/TextInput.vue';
	import SecondarySwitcher from '@M/components/inter-ui/switchers/SecondarySwitcher.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const validationRules = ref({
				password: {
					max: 62
				}
			});

			const formData = ref({
				password: '',
				new_password: '',
				logout_other_devices: false
			});

			const state = reactive({
				isLoading: true,
				isSubmitting: false,
				isGenerating: false,
				formErrors: {
					password: [],
					new_password: []
				}
			});

			onMounted(async () => {
				await colibriAPI().userSettings().getFrom('password/settings').then((response) => {
					let settings = response.data.data;

					validationRules.value = settings.validation_rules;
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
				validationRules: validationRules,
				generatePassword: async () => {
					if(state.isGenerating === false) {
						state.isGenerating = true;

						await colibriAPI().userSettings().getFrom('password/generate').then((response) => {
							formData.value.new_password = response.data.data.password;

							navigator.clipboard.writeText(formData.value.new_password).then(() => {
								toastSuccess(__t('settings.forms.password_settings.new_password_copied'));
							}).catch(() => {
								toastSuccess(__t('toast.forms.changes_saved'));
							});
						}).catch((error) => {
							if(error.response) {
								toastError(error.response.data.message);
							}
						});

						state.isGenerating = false;
					}
				},
				submitForm: async () => {
					if (state.isSubmitting === false) {
						state.isSubmitting = true;

						Object.keys(state.formErrors).forEach((key) => {
							state.formErrors[key] = [];
						});

						await colibriAPI().userSettings().with(formData.value).putTo('password/update').then(() => {
							toastSuccess(__t('toast.forms.changes_saved'));

							formData.value.password = '';
							formData.value.new_password = '';
						}).catch((error) => {
							if(error.response) {
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
