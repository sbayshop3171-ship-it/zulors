<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.account_privacy')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.account_privacy.page_desc') }}
            </h6>
        </div>
    
        <div class="mb-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.account_privacy.auditory') }}
            </h6>
        </div>
        <div class="mb-12">
            <SectionSelect
                iconName="user-plus-01"
                v-bind:captionText="$t('settings.forms.account_privacy.followers_helper')"
            v-bind:titleText="$t('settings.forms.account_privacy.followers')">
                <template v-slot:selectMenu>
                    <ListboxSelect
                        v-model.lazy="formData.followers"
                    v-bind:options="privacyOptions.followers"></ListboxSelect>
                </template>
            </SectionSelect>
        </div>
        <div class="mb-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.account_privacy.messages_and_stories') }}
            </h6>
        </div>
        <div class="mb-12">
            <div class="mb-3">
                <SectionSelect
                    iconName="message-chat-circle"
                    v-bind:captionText="$t('settings.forms.account_privacy.direct_messages_helper')"
                v-bind:titleText="$t('settings.forms.account_privacy.direct_messages')">
                    <template v-slot:selectMenu>
                        <ListboxSelect
                            v-model.lazy="formData.direct_messages"
                        v-bind:options="privacyOptions.direct_messages"></ListboxSelect>
                    </template>
                </SectionSelect>
            </div>
            <SectionSelect
                iconName="send-01"
                v-bind:captionText="$t('settings.forms.account_privacy.story_replies_helper')"
            v-bind:titleText="$t('settings.forms.account_privacy.story_replies')">
                <template v-slot:selectMenu>
                    <ListboxSelect
                        v-model.lazy="formData.story_replies"
                    v-bind:options="privacyOptions.story_replies"></ListboxSelect>
                </template>
            </SectionSelect>
        </div>
        <div class="mb-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.account_privacy.permissions') }}
            </h6>
        </div>
        <div class="mb-12">
            <div class="mb-3">
                <SectionSelect
                    iconName="users-01"
                    v-bind:captionText="$t('settings.forms.account_privacy.group_invites_helper')"
                v-bind:titleText="$t('settings.forms.account_privacy.group_invites')">
                    <template v-slot:selectMenu>
                        <ListboxSelect
                            v-model.lazy="formData.group_invites"
                        v-bind:options="privacyOptions.group_invites"></ListboxSelect>
                    </template>
                </SectionSelect>
            </div>
    
            <SectionSelect
                iconName="at-sign"
                v-bind:captionText="$t('settings.forms.account_privacy.mentions_helper')"
            v-bind:titleText="$t('settings.forms.account_privacy.mentions')">
                <template v-slot:selectMenu>
                    <ListboxSelect
                        v-model.lazy="formData.mentions"
                    v-bind:options="privacyOptions.mentions"></ListboxSelect>
                </template>
            </SectionSelect>
        </div>
        <div class="mb-4">
            <h6 class="text-par-m text-lab-sc font-medium">
                {{ $t('settings.forms.account_privacy.payment_transfers') }}
            </h6>
        </div>
        <div class="mb-12">
            <SectionSelect
                iconName="wallet-02"
                v-bind:captionText="$t('settings.forms.account_privacy.payments_helper')"
            v-bind:titleText="$t('settings.forms.account_privacy.payments')">
                <template v-slot:selectMenu>
                    <ListboxSelect
                        v-model.lazy="formData.payment_transfers"
                    v-bind:options="privacyOptions.payment_transfers"></ListboxSelect>
                </template>
            </SectionSelect>
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
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    
    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SectionSelect from '@D/components/forms/SectionSelect.vue';
    import ListboxSelect from '@D/components/inter-ui/ListboxSelect.vue';

    export default defineComponent({
        setup: function() {
            const formData = ref({});
            const state = reactive({
                isLoading: true,
                isSubmitting: false
            });

            const privacyOptions = ref({
                followers: [
                    {
                        label: __t('settings.forms.account_privacy.all_users'), 
                        value: 'all'
                    }, 
                    {
                        label: __t('settings.forms.account_privacy.only_approved'),
                        value: 'approved'
                    }
                ],
                direct_messages: [
                    {
                        label: __t('settings.forms.account_privacy.all_users'), 
                        value: 'all'
                    },
                    {
                        label: __t('settings.forms.account_privacy.nobody'),
                        value: 'nobody'
                    }
                ],
                story_replies: [
                    {
                        label: __t('settings.forms.account_privacy.all_users'),
                        value: 'all'
                    },
                    {
                        label: __t('settings.forms.account_privacy.nobody'),
                        value: 'nobody'
                    }
                ],
                group_invites: [
                    {
                        label: __t('settings.forms.account_privacy.all_users'),
                        value: 'all'
                    },
                    {
                        label: __t('settings.forms.account_privacy.nobody'),
                        value: 'nobody'
                    }
                ],
                mentions: [
                    {
                        label: __t('settings.forms.account_privacy.all_users'),
                        value: 'all'
                    },
                    {
                        label: __t('settings.forms.account_privacy.nobody'),
                        value: 'nobody'
                    }
                ],
                payment_transfers: [
                    {
                        label: __t('settings.forms.account_privacy.all_users'),
                        value: 'all'
                    },
                    {
                        label: __t('settings.forms.account_privacy.nobody'),
                        value: 'nobody'
                    }
                ]
            });

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('privacy/settings').then((response) => {
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

                    await colibriAPI().userSettings().with(formData.value).putTo('privacy/update').then((response) => {
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
                privacyOptions: privacyOptions,
                state: state,
                formData: formData
            }
        },
        components: {
            PageTitle: PageTitle,
            SectionSelect: SectionSelect,
            ListboxSelect: ListboxSelect
        }
    });
</script>