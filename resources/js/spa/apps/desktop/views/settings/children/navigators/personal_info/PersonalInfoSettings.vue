<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.personal_info_settings')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.personal_info.page_desc') }}
            </h6>
        </div>
        
        <div class="mb-12">
            <div class="mb-3">
                <SectionLink
                    iconName="calendar-check-01"
                    v-bind:link="{ name: 'settings_birthdate' }"
                    v-bind:captionText="formData.birth_date ?? $t('settings.forms.personal_info.birth_date_helper', { app_name: $embedder('config.app.name')})"
                v-bind:titleText="$t('labels.birth_date')"></SectionLink>
            </div>
            <div class="mb-3">
                <SectionLink
                    iconName="globe-06"
                    v-bind:link="{ name: 'settings_country' }"
                    v-bind:captionText="formData.country ?? $t('settings.forms.personal_info.country_helper')"
                v-bind:titleText="$t('settings.forms.personal_info.country')"></SectionLink>
            </div>
    
            <SectionLink
                iconName="building-08"
                v-bind:link="{ name: 'settings_city' }"
                v-bind:captionText="formData.city ?? $t('settings.forms.personal_info.residence_city_helper')"
            v-bind:titleText="$t('settings.forms.personal_info.residence_city')"></SectionLink>
        </div>
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
    import SectionLink from '@D/components/forms/SectionLink.vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true
            });

            const formData = ref({});

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('personal/settings').then((response) => {
                    formData.value = response.data.data;

                    state.isLoading = false;
                });
            });

            return {
                state: state,
                formData: formData
            }
        },
        components: {
            SectionLink: SectionLink,
            PageTitle: PageTitle
        }
    });
</script>