<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.account_credentials')"></PageTitle>
    </div>
    
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.account_credentials.page_desc') }}
            </h6>
        </div>
        
        <div class="mb-8">
            <div class="mb-3">
                <SectionLink
                    iconName="mail-04"
                    v-bind:captionText="formData.email"
                    v-bind:link="{ name: 'settings_email' }"
                v-bind:titleText="$t('settings.navigators.email')"></SectionLink>
            </div>
            <div class="mb-3">
                <SectionLink
                    iconName="phone-01"
                    v-bind:captionText="formData.phone ? formData.phone : $t('settings.navigators.phone_caption')"
                    v-bind:link="{ name: 'settings_phone' }"
                v-bind:titleText="$t('settings.navigators.phone')"></SectionLink>
            </div>
            <div class="block">
                <SectionLink
                    iconName="lock-03"
                    v-bind:captionText="$t('settings.navigators.password_caption')"
                    v-bind:link="{ name: 'settings_password' }"
                v-bind:titleText="$t('settings.navigators.password')"></SectionLink>
            </div>
        </div>
    
        <div class="mb-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.navigators.security_check') }}
            </h6>
        </div>
        <div class="mb-12">
            <div class="mb-3">
                <SectionLink
                    iconName="monitor-04"
                    v-bind:captionText="$t('settings.navigators.active_sessions_caption')"
                    v-bind:link="{ name: 'settings_sessions' }"
                v-bind:titleText="$t('settings.navigators.active_sessions')"></SectionLink>
            </div>
    
            <SectionToggle
                iconName="log-in-02"
                v-model="formData.login_notification"
                v-bind:captionText="$t('settings.navigators.login_notification_caption')"
            v-bind:titleText="$t('settings.navigators.login_notification')"/>
        </div>
    </div>
    <div v-else class="block">
        <div class="flex-center h-96">
            <PrimarySpinAnimation></PrimarySpinAnimation>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, reactive, onMounted, watch } from 'vue';
    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SectionLink from '@D/components/forms/SectionLink.vue';
    import SectionToggle from '@D/components/forms/SectionToggle.vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true
            });

            const formData = ref({});

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('account/credentials/settings').then((response) => {
                    let settings = response.data.data;
                    formData.value.email = settings.email;
                    formData.value.phone = settings.phone;
                    formData.value.login_notification = settings.security_settings.login_notification;
                    state.isLoading = false;
                });

                watch(formData, (newValue) => {
                    let loginNotification = newValue.login_notification;

                    colibriAPI().userSettings().with({
                        login_notification: loginNotification
                    }).putTo('notifications/login/update').then((response) => {
                        // TODO: show success message
                    });
                }, { deep: true });
            });

            return {
                state: state,
                formData: formData
            };
        },
        components: {
            SectionLink: SectionLink,
            PageTitle: PageTitle,
            SectionToggle: SectionToggle
        }
    });
</script>