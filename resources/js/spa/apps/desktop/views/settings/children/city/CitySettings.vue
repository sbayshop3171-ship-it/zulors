<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.city_settings')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.city.page_desc') }}
            </h6>
        </div>
    
        <form v-on:submit.prevent="submitForm">
            <div class="mb-10">
                <TextInput
                    v-bind:textLength="62"
                    v-model="formData.city"
                    v-bind:placeholder="$t('settings.forms.city.city_placeholder')"
                v-bind:labelText="$t('settings.forms.city.city')">
    
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.city.city_helper') }}
                    </template>
                </TextInput>
            </div>
            <div class="mb-10">
                <SectionToggle
                    iconName="shield-01"
                    v-model="formData.city_privacy"
                    v-bind:captionText="$t('settings.forms.city.privacy_helper')"
                v-bind:titleText="$t('labels.privacy')"></SectionToggle>
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
    import { defineComponent, reactive, ref, onMounted } from 'vue';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import TextInput from '@D/components/forms/TextInput.vue';
    import SectionToggle from '@D/components/forms/SectionToggle.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true,
                isSubmitting: false
            });

            const formData = ref({
                city: "",
                city_privacy: false
            });

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('personal/city').then((response) => {
                    let settings = response.data.data;

                    formData.value.city = settings.city;
                    formData.value.city_privacy = settings.privacy_settings.city_privacy;

                    state.isLoading = false;
                });
            });

            return {
                formData: formData,
                state: state,
                submitForm: async () => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;

                        await colibriAPI().userSettings().with(formData.value).putTo('personal/city/update').then((response) => {
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
            PrimaryPillButton: PrimaryPillButton,
            TextInput: TextInput,
            PageTitle: PageTitle,
            SectionToggle: SectionToggle
        }
    });
</script>