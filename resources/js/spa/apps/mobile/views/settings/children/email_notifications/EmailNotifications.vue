<template>
    <Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.email_notifications')"></Toolbar>
    <SettingsDesc v-bind:text="$t('settings.forms.notif_settings.email_notifications_helper')"></SettingsDesc>
    <div class="mb-3">
        <Border height="h-2" opacity="opacity-50"></Border>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-4 px-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.notif_settings.messages') }}
            </h6>
        </div>
        <div class="mb-4">
            <SectionToggle
                v-model.lazy="formData.direct_messages"
                v-bind:captionText="$t('settings.forms.notif_settings.direct_messages_helper')"
            v-bind:titleText="$t('settings.forms.notif_settings.direct_messages')"></SectionToggle>
        </div>
        <div class="mb-3">
            <Border height="h-2" opacity="opacity-50"></Border>
        </div>
        <div class="mb-4 px-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.notif_settings.feedback') }}
            </h6>
        </div>
        <div class="mb-4">
            <div class="mb-3">
                <SectionToggle
                    v-model.lazy="formData.reactions"
                    v-bind:captionText="$t('settings.forms.notif_settings.reactions_helper')"
                v-bind:titleText="$t('settings.forms.notif_settings.reactions')"></SectionToggle>
            </div>
            <SectionToggle
                v-model.lazy="formData.comments"
                v-bind:captionText="$t('settings.forms.notif_settings.comments_helper')"
            v-bind:titleText="$t('settings.forms.notif_settings.comments')"></SectionToggle>
        </div>
        <div class="mb-3">
            <Border height="h-2" opacity="opacity-50"></Border>
        </div>
        <div class="mb-4 px-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.notif_settings.events') }}
            </h6>
        </div>
        <div class="mb-4">
            <div class="mb-3">
                <SectionToggle
                    v-model.lazy="formData.shared_posts"
                    v-bind:captionText="$t('settings.forms.notif_settings.shared_posts_helper')"
                v-bind:titleText="$t('settings.forms.notif_settings.shared_posts')"></SectionToggle>
            </div>
            <div class="mb-3">
                <SectionToggle
                    v-model.lazy="formData.mentions"
                    v-bind:captionText="$t('settings.forms.notif_settings.mentions_helper')"
                v-bind:titleText="$t('settings.forms.notif_settings.mentions')"></SectionToggle>
            </div>
            <div class="mb-3">
                <SectionToggle
                    v-model.lazy="formData.followers"
                    v-bind:captionText="$t('settings.forms.notif_settings.followers_helper')"
                v-bind:titleText="$t('settings.forms.notif_settings.followers')"></SectionToggle>
            </div>
            <SectionToggle
                v-model.lazy="formData.follow_request"
                v-bind:captionText="$t('settings.forms.notif_settings.follow_request_helper')"
            v-bind:titleText="$t('settings.forms.notif_settings.follow_request')"/>
        </div>
    </div>
    <div v-else class="block">
        <div class="flex-center h-96">
            <PrimarySpinAnimation></PrimarySpinAnimation>
        </div>
    </div>
</template>

<script>
    import { defineComponent, reactive, ref, onMounted, watch } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import Toolbar from '@M/components/layout/Toolbar.vue';
    import SettingsDesc from '@M/views/settings/parts/SettingsDesc.vue';
    import SectionToggle from '@M/components/forms/SectionToggle.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true,
                isSubmitting: false,
            });

            const formData = ref({});

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('notifications/email/settings').then((response) => {
                    formData.value = response.data.data;
                });

                state.isLoading = false;

                watch(formData, (newValue) => {
                    submitForm();
                }, { deep: true });
            });

            const submitForm = async () => {
                if (state.isSubmitting === false) {
                    state.isSubmitting = true;

                    await colibriAPI().userSettings().with(formData.value).putTo('notification/email/update').then((response) => {
                        toastSuccess(__t('toast.forms.changes_saved'));
                    }).catch((error) => {
                        if(error.response) {
                            toastError(error.response.data.message);
                        }
                    });

                    state.isSubmitting = false;
                }
            }

            return {
                state: state,
                formData: formData
            }
        },
        components: {
            Toolbar: Toolbar,
            SectionToggle: SectionToggle,
            SettingsDesc: SettingsDesc
        }
    });
</script>