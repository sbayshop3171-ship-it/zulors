<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.account_actions')"></PageTitle>
    </div>
	<div v-if="! state.isLoading" class="block">
		<div class="mb-8">
            <h6 class="text-par-s text-red-900" v-html="$t('settings.forms.actions.page_desc')"></h6>
        </div>

		<form v-on:submit.prevent="submitForm">
			<div class="mb-6">
                <TextInput
                    v-model.trim="formData.password"
                    v-bind:textLength="120"
					v-bind:isPassword="true"
                    v-bind:inputErrors="state.formErrors.password"
                    v-bind:labelText="$t('settings.forms.actions.password')"
                v-bind:placeholder="$t('settings.forms.actions.password_placeholder')">
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.actions.password_helper') }}
                    </template>
                </TextInput>
            </div>
			<div class="mb-10">
                <TextInput
                    v-model.trim="formData.message"
                    v-bind:textLength="1200"
					v-bind:asText="true"
                    v-bind:inputErrors="state.formErrors.message"
                    v-bind:labelText="$t('settings.forms.actions.message')"
                v-bind:placeholder="$t('settings.forms.actions.message_placeholder')">
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.actions.message_helper') }}
                    </template>
                </TextInput>
            </div>

			<div class="block">
                <PrimaryPillButton
                    v-bind:loading="state.isSubmitting"
                buttonType="submit" buttonRole="danger" v-bind:buttonText="$t('settings.forms.actions.delete_account')"></PrimaryPillButton>
            </div>
		</form>
	</div>
	<div v-else class="block">
        <div class="flex-center h-96">
            <PrimarySpinAnimation></PrimarySpinAnimation>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, reactive, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
	import TextInput from '@D/components/forms/TextInput.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        setup: function() {
			const state = reactive({
				isLoading: true,
                isSubmitting: false,
				formErrors: {
					password: [],
					message: []
				}
			});

			const formData = ref({
				'password': '',
				'message': ''
			});

			onMounted(async () => {
				setTimeout(() => {
					state.isLoading = false;
				}, 500);
			});

            return {
				state: state,
				formData: formData,
				submitForm: () => {
					if(state.isSubmitting === false) {
						state.isSubmitting = true;

						Object.keys(state.formErrors).forEach((key) => {
							state.formErrors[key] = [];
						});

						colibriAPI().userSettings().with(formData.value).delete('account/delete').then((response) => {
							toastSuccess(response.data.message);

							setTimeout(() => {
								window.location.href = embedder('routes.user_auth_index');
							}, 3000);
						}).catch((error) => {
							state.isSubmitting = false;
							if(error.response) {
								toastError(error.response.data.message);
								
								if(error.response.data.errors) {
									Object.keys(error.response.data.errors).forEach((key) => {
										state.formErrors[key] = error.response.data.errors[key];
									});
								}
							}
						});
					}
				}
            };
        },
        components: {
            PageTitle: PageTitle,
			TextInput: TextInput,
			PrimaryPillButton: PrimaryPillButton
        }
    });
</script>