<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.birth_date_settings')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.personal_info.birth_date_helper', { app_name: $embedder('config.app.name') }) }}   
            </h6>
        </div>
    
        <form v-on:submit.prevent="submitForm">
            <div class="mb-6">
                <div class="flex gap-4">
                    <div class="w-4/12">
                        <SelectInput
                            v-model="formData.birth_date.month"
                            v-bind:labelText="$t('labels.month')"
                        v-bind:options="calendar.months">
                        </SelectInput>
                    </div>
                    <div class="w-4/12">
                        <SelectInput
                            v-model="formData.birth_date.day"
                            v-bind:labelText="$t('labels.day')"
                        v-bind:options="calendar.days">
                        </SelectInput>
                    </div>
                    <div class="w-4/12">
                        <SelectInput
                            v-model="formData.birth_date.year"
                            v-bind:labelText="$t('labels.year')"
                        v-bind:options="calendar.years">
                        </SelectInput>
                    </div>
                </div>
                
            </div>
            <div class="mb-10">
                <SectionToggle
                    iconName="shield-01"
                    v-model="formData.birthdate_privacy"
                    v-bind:captionText="$t('settings.forms.birth_date.privacy_helper')"
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
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SelectInput from '@D/components/forms/SelectInput.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import SectionToggle from '@D/components/forms/SectionToggle.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true,
                isSubmitting: false
            });
            const calendar = ref({});
            const formData = ref({});

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('personal/birthdate').then((response) => {
                    let settings = response.data.data;

                    calendar.value = settings.calendar;
                    formData.value.birth_date = settings.birth_date;
                    formData.value.birthdate_privacy = settings.privacy_settings.birthdate_privacy;
                    
                    state.isLoading = false;
                });
            });

            return {
                state: state,
                formData: formData,
                calendar: calendar,
                submitForm: async() => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;

                        await colibriAPI().userSettings().with(formData.value).putTo('personal/birthdate/update').then((response) => {
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
            SectionToggle: SectionToggle,
            PageTitle: PageTitle
        }
    });
</script>