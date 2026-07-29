<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.country_settings')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.country.page_desc') }}
            </h6>
        </div>
        <form v-on:submit.prevent="submitForm">
            <div class="mb-10">
                <SelectInput
                    v-bind:isSearchable="true"
                    v-model="formData.country"
                    v-bind:labelText="$t('settings.forms.personal_info.country')"
                v-bind:options="countries">
    
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.country.country_helper') }}
                    </template>
                </SelectInput>
            </div>
            <div class="mb-10">
                <SectionToggle
                    iconName="shield-01"
                    v-model="formData.country_privacy"
                    v-bind:captionText="$t('settings.forms.country.privacy_helper')"
                v-bind:titleText="$t('labels.privacy')"/>
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
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SelectInput from '@D/components/forms/SelectInput.vue';
    import SectionToggle from '@D/components/forms/SectionToggle.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true,
                isSubmitting: false
            });

            const countries = ref([]);

            const formData = ref({
                country: null,
                country_privacy: null
            });

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('personal/country').then((response) => {
                    let settings = response.data.data;

                    countries.value = settings.countries;
                    formData.value.country = settings.country;
                    formData.value.country_privacy = settings.privacy_settings.country_privacy;

                    state.isLoading = false;
                });
            });

            return {
                countries: countries,
                state: state,
                formData: formData,
                submitForm: async () => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;

                        await colibriAPI().userSettings().with(formData.value).putTo('personal/country/update').then((response) => {
                            toastSuccess(__t('toast.forms.changes_saved'));
                        }).catch((error) => {
                            if(error.response) {
                                toastError(error.response.data.message);
                            }
                        });

                        state.isSubmitting = false;
                    }
                }
            }
        },
        components: {
            PrimaryPillButton: PrimaryPillButton,
            SelectInput: SelectInput,
            PageTitle: PageTitle,
            SectionToggle: SectionToggle
        }
    });
</script>