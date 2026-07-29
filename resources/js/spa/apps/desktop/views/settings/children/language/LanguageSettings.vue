<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.language_settings')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-s text-lab-sc" v-html="$t('settings.forms.language.page_desc')"></h6>
        </div>
    
        <form v-on:submit.prevent="submitForm">
            <div class="mb-10">
                <SelectInput
                    v-model="currentLanguage"
                    v-bind:labelText="$t('settings.forms.language.display_language')"
                v-bind:options="languages">
    
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.language.display_language_helper') }}
                    </template>
                </SelectInput>
            </div>
            <div class="block mb-16">
                <PrimaryPillButton v-bind:loading="state.isSubmitting" buttonType="submit" v-bind:buttonText="$t('labels.save_changes')"></PrimaryPillButton>
            </div>
        </form>
    </div>
</template>

<script>
    import { defineComponent, ref, reactive, onMounted } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SelectInput from '@D/components/forms/SelectInput.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
  

    export default defineComponent({
        setup: function() {
            const languages = ref([]);
            const currentLanguage = ref(null);

            const state = reactive({
                isLoading: true,
                isSubmitting: false
            });

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('languages').then((response) => {
                    languages.value = response.data.data.map((item) => {
                        return {
                            label: item.name,
                            value: item.code,
                            current: (item.current == true) ? true : false
                        };
                    });

                    let curr = languages.value.find((item) => {
                        return item.current == true;
                    });

                    if(curr) {
                        currentLanguage.value = curr.value;
                    }
                    else {
                        currentLanguage.value = languages.value[0].value;
                    }
                });

                state.isLoading = false;
            });

            return {
                state: state,
                languages: languages,
                currentLanguage: currentLanguage,
                submitForm: async () => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;
                        
                        await colibriAPI().userSettings().with({
                            language: currentLanguage.value
                        }).putTo('languages/switch').then((response) => {
                            toastSuccess(__t('toast.forms.changes_saved'));
                            
                            window.location.reload();

                        }).catch((error) => {
                            if(error.response) {
                                toastError(error.response.data.message);
                            }

                            state.isSubmitting = true;
                        });
                    }
                }
            }
        },
        components: {
            PrimaryPillButton: PrimaryPillButton,
            SelectInput: SelectInput,
            PageTitle: PageTitle
        }
    });
</script>