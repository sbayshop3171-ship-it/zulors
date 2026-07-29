<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.push_notifications')"></PageTitle>
    </div>

    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.notif_settings.push_notifications_helper') }}
            </h6>
        </div>

        <div class="mb-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.notif_settings.messages') }}
            </h6>
        </div>
        <div class="mb-12">
            <SectionToggle
                iconName="message-square-02"
                v-model.lazy="formData.direct_messages"
                v-bind:captionText="$t('settings.forms.notif_settings.direct_messages_helper')"
            v-bind:titleText="$t('settings.forms.notif_settings.direct_messages')"></SectionToggle>
        </div>
        <div class="mb-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.notif_settings.feedback') }}
            </h6>
        </div>
        <div class="mb-12">
            <div class="mb-3">
                <SectionToggle
                    iconName="face-smile"
                    v-model.lazy="formData.reactions"
                    v-bind:captionText="$t('settings.forms.notif_settings.reactions_helper')"
                v-bind:titleText="$t('settings.forms.notif_settings.reactions')"></SectionToggle>
            </div>
            <SectionToggle
                iconName="message-square-02"
                v-model.lazy="formData.comments"
                v-bind:captionText="$t('settings.forms.notif_settings.comments_helper')"
            v-bind:titleText="$t('settings.forms.notif_settings.comments')"></SectionToggle>
        </div>
        <div class="mb-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.notif_settings.events') }}
            </h6>
        </div>
        <div class="mb-12">
            <div class="mb-3">
                <SectionToggle
                    iconName="share-06"
                    v-model.lazy="formData.shared_posts"
                    v-bind:captionText="$t('settings.forms.notif_settings.shared_posts_helper')"
                v-bind:titleText="$t('settings.forms.notif_settings.shared_posts')"></SectionToggle>
            </div>
            <div class="mb-3">
                <SectionToggle
                    iconName="at-sign"
                    v-model.lazy="formData.mentions"
                    v-bind:captionText="$t('settings.forms.notif_settings.mentions_helper')"
                v-bind:titleText="$t('settings.forms.notif_settings.mentions')"></SectionToggle>
            </div>
            <div class="mb-3">
                <SectionToggle
                    iconName="user-plus-01"
                    v-model.lazy="formData.followers"
                    v-bind:captionText="$t('settings.forms.notif_settings.followers_helper')"
                v-bind:titleText="$t('settings.forms.notif_settings.followers')"></SectionToggle>
            </div>
            <SectionToggle
                iconName="user-right-01"
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

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SectionToggle from '@D/components/forms/SectionToggle.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true,
                isSubmitting: false,
            });
            
            const formData = ref({});

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('notifications/push/settings').then((response) => {
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

                    await colibriAPI().userSettings().with(formData.value).putTo('notification/push/update').then((response) => {
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
            PageTitle: PageTitle,
            SectionToggle: SectionToggle
        }
    });
</script>