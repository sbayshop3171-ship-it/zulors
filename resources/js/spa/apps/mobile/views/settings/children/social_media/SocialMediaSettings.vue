<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.social_media_settings')"></Toolbar>

	<div v-if="! state.isLoading" class="pb-6">
		<div class="px-4 mb-6">
			<p class="text-par-m leading-5 text-lab-sc break-words" v-html="$t('settings.forms.social_media.page_desc', { app_name: $embedder('config.app.name') })"></p>
		</div>

		<form v-if="formData.links.length" v-on:submit.prevent="submitForm" class="px-4">
			<div class="mb-4">
				<h6 class="text-par-m text-lab-sc font-medium">
					{{ $t('settings.forms.social_media.social_media') }}
				</h6>
			</div>

			<div class="space-y-5">
				<TextInput
					v-for="socialLink in formData.links"
					v-model.trim="socialLink.url"
					v-bind:key="socialLink.platform"
					inputType="url"
					v-bind:labelText="socialLink.name"
					v-bind:placeholder="$t('settings.forms.social_media.not_specified')">
					<template v-slot:feedbackInfo>
						{{ $t('settings.forms.social_media.social_media_helper', { platform_name: socialLink.name }) }}
					</template>
				</TextInput>
			</div>

			<div class="sticky bottom-0 bg-bg-pr pt-4 pb-safe-bottom mt-6">
				<PrimaryPillButton
					v-bind:buttonFluid="true"
					v-bind:loading="state.isSubmitting"
					buttonRole="accent"
					buttonType="submit"
				v-bind:buttonText="$t('labels.save_changes')"></PrimaryPillButton>
			</div>
		</form>

		<div v-else class="px-4">
			<div class="rounded-2xl border border-bord-pr bg-fill-qt p-6 text-center">
				<div class="mx-auto mb-3 size-10 text-lab-sc">
					<SvgIcon name="whatsapp" type="social"></SvgIcon>
				</div>
				<p class="text-par-m text-lab-sc">
					{{ $t('empty_state.settings.social_media') }}
				</p>
			</div>
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
	import TextInput from '@M/components/forms/TextInput.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const formData = ref({
				links: []
			});

			const state = reactive({
				isLoading: true,
				isSubmitting: false
			});

			onMounted(async () => {
				await colibriAPI().userSettings().getFrom('social/settings').then((response) => {
					formData.value.links = response.data.data.links.map((linkItem) => {
						return {
							...linkItem,
							url: linkItem.url || ''
						};
					});
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
				submitForm: async () => {
					if (state.isSubmitting === false) {
						state.isSubmitting = true;

						await colibriAPI().userSettings().with({
							links: formData.value.links
						}).putTo('social/update').then(() => {
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
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
