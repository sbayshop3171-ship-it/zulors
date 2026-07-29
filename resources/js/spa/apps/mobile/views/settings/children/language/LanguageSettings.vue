<template>
    <Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.language_settings')"></Toolbar>
    <SettingsDesc v-bind:text="$t('settings.forms.language.page_desc')"></SettingsDesc>
    <div class="mb-3">
        <Border height="h-2" opacity="opacity-50"></Border>
    </div>
    <div v-if="! state.isLoading" class="px-4">
        <form v-on:submit.prevent="submitForm">
            <div class="mb-10">
                <SelectInput
                    v-model="currentLanguage"
                    v-on:update:modelValue="submitForm"
                    v-bind:labelText="$t('settings.forms.language.display_language')"
                v-bind:options="languages">
    
                    <template v-slot:feedbackInfo>
                        {{ $t('settings.forms.language.display_language_helper') }}
                    </template>
                </SelectInput>
            </div>
        </form>
    </div>
</template>

<script>
    import { defineComponent, ref, reactive, onMounted } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import Toolbar from '@M/components/layout/Toolbar.vue';
    import SettingsDesc from '@M/views/settings/parts/SettingsDesc.vue';
    import SelectInput from '@M/components/forms/SelectInput.vue';

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
                            
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);

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
            SelectInput: SelectInput,
            Toolbar: Toolbar,
            SettingsDesc: SettingsDesc
        }
    });
</script>