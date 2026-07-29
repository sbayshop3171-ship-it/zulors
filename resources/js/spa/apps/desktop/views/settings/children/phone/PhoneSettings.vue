<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.phone_settings')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.phone_number.page_desc') }}
            </h6>
        </div>
        <form v-on:submit.prevent="submitForm">
            <div class="mb-10">
                <TextInput
                    v-bind:textLength="validationRules.phone.max"
                    v-bind:inputErrors="state.formErrors.phone"
                    v-model="formData.phone"
                    v-bind:labelText="$t('settings.forms.phone_number.phone_number')"
                v-bind:placeholder="$t('settings.forms.phone_number.phone_number_placeholder')">

                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.phone_number.phone_number_helper') }}
                    </template>
                </TextInput>
            </div>
            <div class="mb-10">
                <SectionToggle iconName="shield-01" v-model="formData.phone_privacy" v-bind:titleText="$t('labels.privacy')">
                    <template v-slot:captionText>
                        {{ $t('settings.forms.phone_number.privacy_helper') }}
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
    import { defineComponent, onMounted, reactive, ref } from 'vue';
    import { useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import TextInput from '@D/components/forms/TextInput.vue';
    import SectionToggle from '@D/components/forms/SectionToggle.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';


    export default defineComponent({
        setup: function() {
            const validationRules = ref({});
            const router = useRouter();

            const state = reactive({
                isLoading: true,
                isSubmitting: false,
                formErrors: {
                    phone: []
                }
            });

            const formData = ref({
                phone: '',
                phone_privacy: true
            });

            onMounted(async () =>  {
                await colibriAPI().userSettings().getFrom('phone/settings').then((response) => {
                    let settings = response.data.data;

                    validationRules.value = settings.validation_rules;
                    formData.value.phone = settings.phone;
                    formData.value.phone_privacy = settings.privacy_settings.phone_privacy
                });

                state.isLoading = false;
            });
            
            return {
                state: state,
                formData: formData,
                validationRules: validationRules,
                submitForm: async () => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;

                        Object.keys(state.formErrors).forEach((key) => {
                            state.formErrors[key] = [];
                        });

                        await colibriAPI().userSettings().with(formData.value).putTo('phone/update').then((response) => {
                            if (response.data.data.confirmation_required) {
                                router.push({
                                    name: 'settings_phone_confirm' 
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
            PrimaryPillButton: PrimaryPillButton,
            TextInput: TextInput,
            PageTitle: PageTitle,
            SectionToggle: SectionToggle
        }
    });
</script>