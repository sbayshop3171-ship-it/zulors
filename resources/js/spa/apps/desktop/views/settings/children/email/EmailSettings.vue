<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.email_settings')"></PageTitle>
    </div>
    
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.email_address.page_desc') }}
            </h6>
        </div>
        <form v-on:submit.prevent="submitForm">
            <div class="mb-10">
                <TextInput
                    v-bind:textLength="validationRules.email.max"
                    v-model="formData.email"
                    v-bind:inputErrors="state.formErrors.email"
                    v-bind:labelText="$t('settings.forms.email_address.email_address')"
                    v-bind:placeholder="$t('settings.forms.email_address.email_address_placeholder')">
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.email_address.email_address_helper') }}
                    </template>
                </TextInput>
            </div>
            <div class="mb-10">
                <SectionToggle
                    iconName="shield-01"
                    v-model="formData.email_privacy"
                    v-bind:captionText="$t('settings.forms.email_address.privacy_helper')"
                v-bind:titleText="$t('labels.privacy')"></SectionToggle>
            </div>
            <div class="block mb-16">
                <PrimaryPillButton v-bind:isDisabled="isSubmitDenied" v-bind:loading="state.isSubmitting" buttonType="submit" v-bind:buttonText="$t('labels.save_changes')"></PrimaryPillButton>
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
    import { defineComponent, reactive, ref, onMounted, computed, watch } from 'vue';

    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import TextInput from '@D/components/forms/TextInput.vue';
    import SectionToggle from '@D/components/forms/SectionToggle.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        setup: function() {
            const authStore = useAuthStore();
            const userData = ref(authStore.userData);
            const validationRules = ref({});
            const router = useRouter();
            const state = reactive({
                isLoading: true,
                isSubmitting: false,
                formErrors: {
                    email: []
                }
            });

            const formData = ref({
                email: userData.value.email,
                email_privacy: true
            });

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('email/settings').then((response) => {
                    let settings = response.data.data;

                    validationRules.value = settings.validation_rules;
                    formData.value.email = settings.email;
                    formData.value.email_privacy = settings.privacy_settings.email_privacy
                });

                state.isLoading = false;
            });

            return {
                formData: formData,
                validationRules: validationRules,
                state: state,
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
                },
                isSubmitDenied: computed(() => {
                    return false; //formData.value.email === userData.value.email;
                })
            }
        },
        components: {
            PrimaryPillButton: PrimaryPillButton,
            TextInput: TextInput,
            PageTitle: PageTitle,
            SectionToggle: SectionToggle
        }
    });
</script>