<template>
    <ContentModal v-on:close="$emit('close')">
        <div class="flex max-h-[calc(100vh-2rem)] flex-col overflow-hidden">
            <ModalHeader v-bind:modalTitle="$t('wallet.deposit_money')"></ModalHeader>
            <div v-if="state.isLoading" class="overflow-y-auto">
                <LoadingSkeleton></LoadingSkeleton>
            </div>
            <div v-else class="min-h-0 flex-1 overflow-y-auto">
                <form v-on:submit.prevent="submitForm">
                    <div class="block p-4">
                    <div class="mb-4">
                        <ModalTextInput 
                            v-bind:labelText="$t('wallet.deposit_amount')"
                            v-bind:placeholder="`0, 00 ${walletCurrency.symbol}`"
                            v-model="formData.amount"
                            v-bind:name="$t('wallet.deposit_amount')"
                            inputType="number"
                            v-bind:inputErrors="state.formErrors.amount"
                            v-on:clear="formData.amount = ''"
                        v-bind:hasFeedback="true">
                            <template v-slot:feedbackInfo>
                                {{ $t('wallet.deposit_amount_helper') }}
                                <span class="mt-1 block text-lab-tr">
                                    {{ $t('wallet.deposit_amount_limits', { min_amount: minDepositAmount, max_amount: maxDepositAmount }) }}
                                </span>
                            </template>
                        </ModalTextInput>
                    </div>
                    <div class="mb-2 px-1 text-cap-s font-semibold text-lab-sc">
                        {{ $t('labels.provider') }}
                    </div>
                    <div v-if="paymentProviders.length" class="mb-6 grid grid-cols-3 gap-3 min-[420px]:grid-cols-4 sm:grid-cols-6 md:grid-cols-8">
                        <ProviderCircleCard
                            v-for="provider in paymentProviders"
                            v-bind:isActive="formData.provider === provider.id"
                            v-bind:key="provider.id"
                            v-bind:providerName="provider.name"
                            v-on:click="selectProvider(provider.id)"
                        v-bind:logoUrl="provider.logo"></ProviderCircleCard>
                    </div>
                    <div v-else class="mb-6 rounded-xl border border-bord-pr bg-fill-qt p-4 text-center">
                        <div class="mx-auto mb-2 size-10 inline-flex-center rounded-xl bg-bg-pr text-lab-sc">
                            <SvgIcon name="credit-card-02" type="line" classes="size-5"></SvgIcon>
                        </div>
                        <p class="text-par-s leading-5 text-lab-sc">
                            {{ $t('wallet.no_payment_providers') }}
                        </p>
                    </div>
                    <p v-if="state.formErrors.provider.length" class="-mt-4 mb-6 px-1 text-par-s text-red-900">
                        {{ state.formErrors.provider[0] }}
                    </p>
                    <div class="block border-t border-bord-pr pt-4">
                        <div class="mb-2">
                            <PrimaryPillButton 
                                buttonType="submit" 
                                buttonSize="lm"
                                v-bind:isDisabled="! isValidForm"
                                v-bind:loading="state.isSubmitting"
                                class="w-full sm:w-auto"
                            v-bind:buttonText="$t('labels.continue')"></PrimaryPillButton>
                        </div>
                        <p class="text-par-s text-lab-sc" v-html="$t('wallet.tos_agree', { tos_link: $getRoute('terms_of_use') })"></p>
                    </div>
                    </div>
                </form>
            </div>
        </div>
    </ContentModal>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted } from 'vue';
    import { useWalletStore } from '@D/store/wallet/wallet.store.js';

    import ContentModal from '@D/components/general/modals/ContentModal.vue';
    import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import LoadingSkeleton from '@D/views/wallet/parts/LoadingSkeleton.vue';
    import ModalTextInput from '@D/components/forms/modal/ModalTextInput.vue';
    import ProviderCircleCard from '@D/components/general/payments/ProviderCircleCard.vue';

    export default defineComponent({
        emits: ['close'],
        setup: function(props, context) {
            const state = reactive({
                isLoading: true,
                isSubmitting: false,
                formErrors: {
                    amount: [],
                    provider: []
                }
            });

            const formData = reactive({
                amount: '',
                provider: ''
            });

            const walletStore = useWalletStore();
            const paymentProviders = computed(() => {
                return walletStore.paymentProviders;
            });
            const walletCurrency = computed(() => {
                return walletStore.walletData?.currency || {
                    symbol: ''
                };
            });
            const minDepositAmount = Number(embedder('config.wallet.deposit.min_amount', 0));
            const maxDepositAmount = Number(embedder('config.wallet.deposit.max_amount', 1000000));
            const amountValue = computed(() => {
                return Number(formData.amount || 0);
            });
            const isValidForm = computed(() => {
                return ! state.isLoading && amountValue.value >= minDepositAmount && amountValue.value <= maxDepositAmount && Boolean(formData.provider);
            });

            const resetFormErrors = function() {
                state.formErrors.amount = [];
                state.formErrors.provider = [];
            };

            onMounted(async () => {
                await walletStore.fetchPaymentProviders();

                if(paymentProviders.value.length && ! formData.provider) {
                    formData.provider = paymentProviders.value[0].id;
                }

                state.isLoading = false;
            });

            return {
                state: state,
                paymentProviders: paymentProviders,
                formData: formData,
                walletCurrency: walletCurrency,
                minDepositAmount: minDepositAmount,
                maxDepositAmount: maxDepositAmount,
                isValidForm: isValidForm,
                selectProvider: (providerId) => {
                    formData.provider = providerId;
                    resetFormErrors();
                },
                submitForm: async () => {
                    if(! state.isSubmitting && isValidForm.value) {
                        state.isSubmitting = true;
                        resetFormErrors();

                        await walletStore.createDepositPayment(formData).then(async (response) => {
                            if(response.data.data.is_hosted_checkout) {
                                window.location.href = response.data.data.checkout_url;

                                return false;
                            }

                            await Promise.allSettled([
                                walletStore.fetchWalletData(),
                                walletStore.fetchTransactions()
                            ]);

                            toastSuccess(__t('toast.wallet.deposit.success'));
                            context.emit('close');
                        }).catch((error) => {
                            const response = error.response?.data;

                            if(response?.errors?.amount) {
                                state.formErrors.amount = response.errors.amount;
                            }

                            if(response?.errors?.provider) {
                                state.formErrors.provider = response.errors.provider;
                            }

                            if(response?.message) {
                                toastError(response.message);
                            }
                        });

                        state.isSubmitting = false;
                    }
                }
            }
        },
        components: {
            ContentModal: ContentModal,
            PrimaryPillButton: PrimaryPillButton,
            ModalHeader: ModalHeader,
            LoadingSkeleton: LoadingSkeleton,
            ModalTextInput: ModalTextInput,
            ProviderCircleCard: ProviderCircleCard
        }
    });
</script>
