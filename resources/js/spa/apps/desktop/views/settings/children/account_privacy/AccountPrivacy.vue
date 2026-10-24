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
        <section class="mb-8 rounded-2xl border border-bord-pr bg-bg-pr p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-par-m font-bold text-lab-pr2">Personalized ads</h3>
                    <p class="mt-1 max-w-2xl text-par-s leading-5 text-lab-sc">Allow activity from posts and searches to help select relevant ads. Turning this off clears your saved ad topics.</p>
                </div>
                <input v-model="adPersonalization" type="checkbox" class="mt-1 size-5 accent-current" v-on:change="saveAdPersonalization">
            </div>
            <p v-if="adInterests.length" class="mt-3 text-par-s text-lab-sc">Ad topics: {{ adInterests.map(item => item.topic).join(', ') }}</p>
            <button v-if="adInterests.length" type="button" class="mt-3 text-par-s font-semibold text-lab-pr2 underline" v-on:click="resetAdInterests">Reset ad topics</button>
        </section>
    
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
            const adPersonalization = ref(false);
            const adInterests = ref([]);
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
                await colibriAPI().userSettings().getFrom('ad-personalization').then(response => {
                    adPersonalization.value = Boolean(response.data.data.personalized_ads);
                }).catch(() => {});
                await loadAdInterests();

                state.isLoading = false;

                watch(formData, (newValue) => {
                    submitForm();
                }, { deep: true });
            });

            const loadAdInterests = async () => {
                await colibriAPI().userSettings().getFrom('ad-interests').then(response => {
                    adInterests.value = response.data.data || [];
                }).catch(() => { adInterests.value = []; });
            };
            const saveAdPersonalization = async () => {
                await colibriAPI().userSettings().with({ personalized_ads: adPersonalization.value }).patchTo('ad-personalization').then(response => {
                    adPersonalization.value = Boolean(response.data.data.personalized_ads);
                    if(!adPersonalization.value) adInterests.value = [];
                }).catch(error => { adPersonalization.value = !adPersonalization.value; toastError(error.response?.data?.message || 'Could not update ad settings.'); });
            };
            const resetAdInterests = async () => {
                await colibriAPI().userSettings().delete('ad-interests').then(() => { adInterests.value = []; }).catch(() => toastError('Could not reset ad topics.'));
            };

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
                formData: formData,
                adPersonalization,
                adInterests,
                saveAdPersonalization,
                resetAdInterests
            }
        },
        components: {
            PageTitle: PageTitle,
            SectionSelect: SectionSelect,
            ListboxSelect: ListboxSelect
        }
    });
</script>
