<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.birth_date_settings')"></Toolbar>

	<div v-if="! state.isLoading" class="px-4 pb-6">
		<p class="mb-6 text-par-m leading-5 text-lab-sc break-words">
			{{ $t('settings.forms.personal_info.birth_date_helper', { app_name: $embedder('config.app.name') }) }}
		</p>

		<form v-on:submit.prevent="submitForm" class="space-y-6">
			<div class="grid grid-cols-3 gap-3">
				<div class="min-w-0">
					<SelectInput
						v-model="formData.birth_date.month"
						v-bind:labelText="$t('labels.month')"
					v-bind:options="calendar.months"></SelectInput>
				</div>

				<div class="min-w-0">
					<SelectInput
						v-model="formData.birth_date.day"
						v-bind:labelText="$t('labels.day')"
					v-bind:options="calendar.days"></SelectInput>
				</div>

				<div class="min-w-0">
					<SelectInput
						v-model="formData.birth_date.year"
						v-bind:labelText="$t('labels.year')"
					v-bind:options="calendar.years"></SelectInput>
				</div>
			</div>

			<label class="flex min-h-20 items-center gap-3 rounded-2xl border border-bord-pr bg-bg-pr px-4 py-3">
				<span class="shrink-0 size-6 text-lab-pr">
					<SvgIcon name="shield-01" type="line"></SvgIcon>
				</span>

				<span class="min-w-0 flex-1">
					<span class="block text-par-m font-bold text-lab-pr2 break-words">
						{{ $t('labels.privacy') }}
					</span>
					<span class="block text-par-m leading-5 text-lab-sc break-words">
						{{ $t('settings.forms.birth_date.privacy_helper') }}
					</span>
				</span>

				<span class="shrink-0">
					<SecondarySwitcher v-model="formData.birthdate_privacy"></SecondarySwitcher>
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
	import { defineComponent, reactive, ref, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import SelectInput from '@M/components/forms/SelectInput.vue';
	import SecondarySwitcher from '@M/components/inter-ui/switchers/SecondarySwitcher.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isLoading: true,
				isSubmitting: false
			});

			const calendar = ref({
				months: [],
				days: [],
				years: []
			});

			const formData = ref({
				birth_date: {
					month: '',
					day: '',
					year: ''
				},
				birthdate_privacy: false
			});

			onMounted(async () => {
				await colibriAPI().userSettings().getFrom('personal/birthdate').then((response) => {
					let settings = response.data.data;

					calendar.value = settings.calendar;
					formData.value.birth_date = settings.birth_date;
					formData.value.birthdate_privacy = settings.privacy_settings.birthdate_privacy;
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
				calendar: calendar,
				submitForm: async() => {
					if (state.isSubmitting === false) {
						state.isSubmitting = true;

						await colibriAPI().userSettings().with(formData.value).putTo('personal/birthdate/update').then(() => {
							toastSuccess(__t('toast.forms.changes_saved'));
						}).catch((error) => {
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
			SelectInput: SelectInput,
			SecondarySwitcher: SecondarySwitcher,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
