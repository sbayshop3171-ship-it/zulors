<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.your_account')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc" v-html="$t('settings.forms.account_settings.page_desc')"></h6>
        </div>
        <form v-on:submit.prevent="submitForm">
            <div class="mb-6">
                <TextInput
                    v-model.trim="formData.username"
                    v-bind:textLength="validationRules.username.max"
                    v-bind:inputErrors="state.formErrors.username"
                    v-bind:labelText="$t('settings.forms.account_settings.username')"
                    v-bind:placeholder="$t('settings.forms.account_settings.username_placeholder')">
                    
                    <template v-slot:feedbackIcon>
                        <SvgIcon name="info-circle" classes="size-5 text-lab-tr"></SvgIcon>
                    </template>
                    <template v-slot:feedbackInfo>
                        <span class="block mb-2 text-brand-900 font-semibold"> 
                            {{ $baseUrl('@' + formData.username) }}
                        </span>
                        {{ $t('settings.forms.account_settings.username_helper') }}
                    </template>
                </TextInput>
            </div>
            <div class="mb-6">
                <TextInput
                    v-model.trim="formData.caption"
                    v-bind:textLength="validationRules.caption.max"
                    v-bind:inputErrors="state.formErrors.caption"
                    v-bind:labelText="$t('settings.forms.account_settings.caption')"
                    v-bind:placeholder="$t('settings.forms.account_settings.caption_placeholder')">
                    
                    <template v-slot:feedbackInfo>
                        <span v-html="$t('settings.forms.account_settings.caption_helper')"></span>
                    </template>
                </TextInput>
            </div>
            <div class="mb-6">
                <TextInput
                    v-model.trim="formData.first_name"
                    v-bind:textLength="validationRules.first_name.max"
                    v-bind:inputErrors="state.formErrors.first_name"
                    v-bind:labelText="$t('settings.forms.account_settings.name')"
                    v-bind:placeholder="$t('settings.forms.account_settings.name_placeholder')">
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.account_settings.name_helper', { days: 10 }) }}
                    </template>
                </TextInput>
            </div>
            <div class="mb-6">
                <TextInput
                    v-model.trim="formData.last_name"
                    v-bind:textLength="validationRules.last_name.max"
                    v-bind:inputErrors="state.formErrors.last_name"
                    v-bind:labelText="$t('settings.forms.account_settings.surname')"
                    v-bind:placeholder="$t('settings.forms.account_settings.surname_placeholder')">
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.account_settings.surname_helper') }}
                    </template>
                </TextInput>
            </div>
            <div class="mb-6">
                <TextInput
                    v-model.trim="formData.bio"
                    v-bind:textLength="validationRules.bio.max"
                    v-bind:asText="true"
                    v-bind:inputErrors="state.formErrors.bio"
                    v-bind:labelText="$t('settings.forms.account_settings.bio')"
                    v-bind:placeholder="$t('settings.forms.account_settings.bio_placeholder')">
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.account_settings.bio_helper') }}
                    </template>
                </TextInput>
            </div>
            <div class="mb-6">
                <TextInput
                    v-model.trim="formData.website"
                    v-bind:textLength="validationRules.website.max"
                    v-bind:inputErrors="state.formErrors.website"
                    v-bind:labelText="$t('settings.forms.account_settings.website')"
                    inputType="text"
                    v-bind:placeholder="$t('settings.forms.account_settings.website_placeholder')">
                
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.account_settings.website_helper') }}
                    </template>
                </TextInput>
            </div>
            <div class="mb-10">
                <RadioInput
                    v-model="formData.gender"
                    v-bind:options="userGenderOptions"
                    v-bind:inputErrors="state.formErrors.gender"
                v-bind:labelText="$t('settings.forms.account_settings.gender')">
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.account_settings.gender_helper') }}
                    </template>
                </RadioInput>
            </div>
    
            <div class="block mb-16">
                <PrimaryPillButton
                    v-bind:loading="state.isSubmitting"
                buttonType="submit" v-bind:buttonText="$t('labels.save_changes')"></PrimaryPillButton>
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
    import { useAuthStore } from '@D/store/auth/auth.store.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import TextInput from '@D/components/forms/TextInput.vue';
    import RadioInput from '@D/components/forms/RadioInput.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    
    export default defineComponent({
        setup: function() {
            const validationRules = ref({});
            const authStore = useAuthStore();
            const state = reactive({
                isLoading: true,
                isSubmitting: false,
                formErrors: {
                    username: [],
                    caption: [],
                    first_name: [],
                    last_name: [],
                    bio: [],
                    website: [],
                    gender: []
                }
            });

            const formData = ref({});

            const userGenderOptions = ref([
                {value: 'male', 'label': __t('settings.forms.account_settings.gender_male'), isChecked: (formData.value.gender == 'male')},
                {value: 'female', 'label': __t('settings.forms.account_settings.gender_female'), isChecked: (formData.value.gender == 'female')},
                {value: 'not-specified', 'label': __t('settings.forms.account_settings.gender_not_specified'), isChecked: (formData.value.gender == 'not-specified')}
            ]);

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('account/settings').then((response) => {
                    let settings = response.data.data;

                    formData.value = settings.user_data;
                    validationRules.value = settings.validation_rules;
                });

                state.isLoading = false;
            });

            return {
                state: state,
                validationRules: validationRules,
                formData: formData,
                userGenderOptions: userGenderOptions,
                submitForm: async () => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;

                        Object.keys(state.formErrors).forEach((key) => {
                            state.formErrors[key] = [];
                        });

                        await colibriAPI().userSettings().with(formData.value).putTo('account/update').then((response) => {
                            authStore.setProperty('first_name', formData.value.first_name);
                            authStore.setProperty('last_name', formData.value.last_name);
                            authStore.setProperty('username', formData.value.username);

                            toastSuccess(__t('toast.forms.changes_saved'));
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
            RadioInput: RadioInput,
            PrimaryPillButton: PrimaryPillButton,
            TextInput: TextInput,
            PageTitle: PageTitle
        }
    });
</script>