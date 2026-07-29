<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.email_confirmation')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-s text-lab-sc" v-html="$t('settings.forms.email_confirmation.page_desc', { new_email:  state.newEmail })"></h6>
        </div>
        <div class="mb-6">
            <CodeInput
                v-model="formData.code"
            v-bind:labelText="$t('settings.forms.email_confirmation.confirmation_code')">

                <template v-slot:feedbackInfo v-if="state.timeExpired">
                    <span v-html="$t('settings.forms.email_confirmation.confirmation_code_expired')"></span>
                </template>
                <template v-slot:feedbackInfo v-else>
                    <span v-html="$t('settings.forms.email_confirmation.confirmation_code_helper', { time: $filters.formatTime(state.expireSeconds) })"></span>
                </template>
            </CodeInput>
        </div>
        <div class="block">
            <template v-if="state.timeExpired">
                <PrimaryPillButton v-bind:loading="state.isSubmitting" v-on:click="resendCode" buttonType="button" buttonSize="lm" v-bind:buttonText="$t('settings.resend_code')"></PrimaryPillButton>
            </template>
            <template v-else>
                <PrimaryPillButton v-bind:loading="state.isSubmitting" v-on:click="confirmCode" v-bind:isDisabled="formData.code.length < 6" buttonType="button" buttonSize="lm" v-bind:buttonText="$t('settings.forms.email_confirmation.confirm_email')"></PrimaryPillButton>   
            </template>
        </div>
    </div>
    <div v-else class="block">
        <div class="flex-center h-96">
            <PrimarySpinAnimation></PrimarySpinAnimation>
        </div>
    </div>
</template>

<script>
    import { defineComponent, onMounted, reactive } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useRouter } from 'vue-router';
    import { useAuthStore } from '@D/store/auth/auth.store.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import CodeInput from '@D/components/forms/CodeInput.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        setup: function() {
            var timerInterval = null;
            const authStore = useAuthStore();
            const router = useRouter();
            const formData = reactive({
                code: ''
            });

            const state = reactive({
                timeExpired: false,
                expireSeconds: 0,
                isLoading: true,
                isSubmitting: false,
                newEmail: '',
                formErrors: {
                    code: []
                }
            });

            const startInterval = function() {
                if(timerInterval) {
                    clearInterval(timerInterval);
                }

                timerInterval = setInterval(() => { 
                    if (state.expireSeconds < 1) {
                        state.timeExpired = true;
                        clearInterval(timerInterval);
                    }
                    else{
                        state.expireSeconds -= 1;
                    }
                }, 1000);
            }

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('email/check').then((response) => {
                    state.expireSeconds = response.data.data.time_left;
                    state.newEmail = response.data.data.email;

                    state.isLoading = false;

                    startInterval();
                }).catch((error) => {
                    if (error.response) {
                        router.push({ name: 'settings_email' });
                    }
                });
            });

            return {
                state: state,
                formData: formData,
                resendCode: async () => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;

                        await colibriAPI().userSettings().sendTo('email/update/resend').then((response) => {

                            toastSuccess(__t('toast.confirmation_code_resent'));

                            state.timeExpired = false;
                            state.expireSeconds = response.data.data.time_left;
                            startInterval();

                        }).catch((error) => {
                            if(error.response) {
                                toastError(error.response.data.message);
                            }
                        });

                        state.isSubmitting = false;
                    }
                },
                confirmCode: async () => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;
                        
                        await colibriAPI().userSettings().with(formData).sendTo('email/update/confirm').then((response) => {
                            toastSuccess(__t('toast.email_updated'));
                            
                            authStore.setProperty('email', response.data.data.email);

                            router.push({
                                name: 'settings_email'
                            });
                        }).catch((error) => {
                            if(error.response) {
                                toastError(error.response.data.message);

                                state.isSubmitting = false;
                            }
                        });
                    }
                }
            }
        },
        components: {
            PrimaryPillButton: PrimaryPillButton,
            CodeInput: CodeInput,
            PageTitle: PageTitle
        }
    });
</script>