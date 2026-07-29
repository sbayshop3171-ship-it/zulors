<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.city_settings')"></Toolbar>

	<div v-if="! state.isLoading" class="px-4 pb-6">
		<p class="mb-6 text-par-m leading-5 text-lab-sc break-words">
			{{ $t('settings.forms.city.page_desc') }}
		</p>

		<form v-on:submit.prevent="submitForm" class="space-y-6">
			<TextInput
				v-bind:textLength="62"
				v-model.trim="formData.city"
				v-bind:placeholder="$t('settings.forms.city.city_placeholder')"
			v-bind:labelText="$t('settings.forms.city.city')">
				<template v-slot:feedbackInfo>
					{{ $t('settings.forms.city.city_helper') }}
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
						{{ $t('settings.forms.city.privacy_helper') }}
					</span>
				</span>

				<span class="shrink-0">
					<SecondarySwitcher v-model="formData.city_privacy"></SecondarySwitcher>
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
	import TextInput from '@M/components/forms/TextInput.vue';
	import SecondarySwitcher from '@M/components/inter-ui/switchers/SecondarySwitcher.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isLoading: true,
				isSubmitting: false
			});

			const formData = ref({
				city: '',
				city_privacy: false
			});

			onMounted(async () => {
				await colibriAPI().userSettings().getFrom('personal/city').then((response) => {
					let settings = response.data.data;

					formData.value.city = settings.city || '';
					formData.value.city_privacy = settings.privacy_settings.city_privacy;
				}).catch((error) => {
					if(error.response) {
						toastError(error.response.data.message);
					}
				});

				state.isLoading = false;
			});

			return {
				formData: formData,
				state: state,
				submitForm: async () => {
					if (state.isSubmitting === false) {
						state.isSubmitting = true;

						await colibriAPI().userSettings().with(formData.value).putTo('personal/city/update').then(() => {
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
			TextInput: TextInput,
			SecondarySwitcher: SecondarySwitcher,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
