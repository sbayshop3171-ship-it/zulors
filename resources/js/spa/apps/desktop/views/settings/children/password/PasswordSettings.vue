<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.password_settings')"></PageTitle>
    </div>

    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-s text-lab-sc" v-html="$t('settings.forms.password_settings.page_desc')"></h6>
        </div>
        
        <form v-on:submit.prevent="submitForm">
            <div class="mb-10">
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
            </div>
            <div class="mb-10">
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
            </div>
            <div class="mb-10 leading-none">
                <PrimaryTextButton v-bind:loading="state.isGenerating" v-on:click="generatePassword" iconPosition="left" buttonType="button" v-bind:buttonText="$t('settings.forms.password_settings.generate_password')">
                    <template v-slot:buttonIcon>
                        <SvgIcon name="star-06" type="solid" classes="size-full text-brand-900"></SvgIcon>
                    </template>
                </PrimaryTextButton>
            </div>
            <div class="mb-10">
                <SectionToggle iconName="hand" v-model="formData.logout_other_devices" v-bind:titleText="$t('labels.security')">
                    <template v-slot:captionText>
                        <span v-html="$t('settings.forms.password_settings.security_helper')"></span>
                    </template>
                </SectionToggle>
            </div>
    
            <div class="block mb-16">
                <PrimaryPillButton v-bind:loading="state.isSubmitting" buttonType="submit" v-bind:buttonText="$t('labels.save_changes')"></PrimaryPillButton>
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
    import SectionToggle  from '@D/components/forms/SectionToggle.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';

    export default defineComponent({
        setup: function() {
            const validationRules = ref({});
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
                    
                    state.isLoading = false;
                });
            });

            return {
                state: state,
                formData: formData,
                validationRules: validationRules,
                generatePassword: async () => {
                    state.isGenerating = true;

                    await colibriAPI().userSettings().getFrom('password/generate').then((response) => {
                        formData.value.new_password = response.data.data.password;
                        state.isGenerating = false;

                        navigator.clipboard.writeText(formData.value.new_password).then(() => {
                            toastSuccess(__t('settings.forms.password_settings.new_password_copied'));
                        }).catch((err) => {
                            console.error('Failed to copy text: ', err);
                        });
                    });
                },
                submitForm: async () => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;

                        Object.keys(state.formErrors).forEach((key) => {
                            state.formErrors[key] = [];
                        });

                        await colibriAPI().userSettings().with(formData.value).putTo('password/update').then((response) => {
                            toastSuccess(__t('toast.forms.changes_saved'));

                            formData.value.password = '';
                            formData.value.new_password = '';
                        }).catch((error) => {
                            if(error.response) {
                                toastSuccess(error.response.data.message);

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
            }
        },
        components: {
            PrimaryPillButton: PrimaryPillButton,
            TextInput: TextInput,
            PageTitle: PageTitle,
            SectionToggle: SectionToggle,
            PrimaryTextButton: PrimaryTextButton
        }
    });
</script>