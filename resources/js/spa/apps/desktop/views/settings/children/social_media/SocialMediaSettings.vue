<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.social_media_settings')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-s text-lab-sc" v-html="$t('settings.forms.social_media.page_desc', { app_name:  $embedder('config.app.name') })"></h6>
        </div>
        <template v-if="formData.links.length">
            <form v-on:submit.prevent="submitForm">
                <div class="block mb-12">
                    <div class="mb-4">
                        <h6 class="text-par-m text-lab-sc font-medium">
                            {{ $t('settings.forms.social_media.social_media') }}
                        </h6>
                    </div>
                    <div v-for="socialLink in formData.links" class="mb-6">
                        <TextInput v-model="socialLink.url" inputType="url" v-bind:labelText="socialLink.name" v-bind:placeholder="$t('settings.forms.social_media.not_specified')">
                            <template v-slot:feedbackInfo>
                                {{ $t('settings.forms.social_media.social_media_helper', { platform_name: socialLink.name }) }}
                            </template>
                        </TextInput>
                    </div>
                </div>
                <div class="block mb-16">
                    <PrimaryPillButton v-bind:loading="state.isSubmitting" buttonType="submit" v-bind:buttonText="$t('labels.save_changes')"></PrimaryPillButton>
                </div>
            </form>
        </template>
        <template v-else>
            <FluidEmptyState iconName="whatsapp" iconType="social" v-bind:text="$t('empty_state.settings.social_media')"></FluidEmptyState>
        </template>
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
    import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';

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
                    formData.value.links = response.data.data.links;
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
                        }).putTo('social/update').then((response) => {
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
            TextInput: TextInput,
            PageTitle: PageTitle,
            FluidEmptyState: FluidEmptyState
        }
    });
</script>