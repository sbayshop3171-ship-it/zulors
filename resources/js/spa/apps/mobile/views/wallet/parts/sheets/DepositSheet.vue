<template>
	<ActionSheet v-on:close="$emit('close')" v-bind:isMuted="true">
		<div class="flex h-full flex-col">
			<div class="px-4 pb-3">
				<div class="flex items-start gap-3">
					<div class="size-10 shrink-0"></div>
					<div class="min-w-0 flex-1 text-center">
						<SheetTitle v-bind:title="$t('wallet.deposit_money')"></SheetTitle>
					</div>
					<PrimaryIconButton
						v-on:click="$emit('close')"
						iconName="x"
						iconType="solid"
						iconAreaSize="10"
						iconSize="5"
						buttonColor="text-lab-sc"
						class="shrink-0 bg-bg-pr"
					></PrimaryIconButton>
				</div>
			</div>

			<div class="flex-1 overflow-y-auto px-4 pb-4">
				<ActionSheetGroup>
					<div class="p-4">
						<TextInput
							v-model="form.amount"
							v-bind:labelText="$t('wallet.deposit_amount')"
							v-bind:placeholder="`0.00 ${walletCurrency.symbol || ''}`.trim()"
							inputType="number"
							v-bind:inputErrors="state.formErrors.amount"
							v-bind:hasFeedback="false"
						></TextInput>

						<div class="mt-2 flex items-start justify-between gap-3 px-1">
							<p class="min-w-0 flex-1 text-par-s leading-5 text-lab-sc">
								{{ $t('wallet.deposit_amount_helper') }}
							</p>
						</div>
						<p class="mt-1 px-1 text-par-s text-lab-tr">
							{{ $t('wallet.deposit_amount_limits', { min_amount: minDepositAmount, max_amount: maxDepositAmount }) }}
						</p>

						<div class="mt-5">
							<div class="mb-2 px-1 text-cap-s font-semibold text-lab-sc">
								{{ $t('labels.provider') }}
							</div>

							<div v-if="state.isLoadingProviders" class="grid gap-3">
								<div class="skeleton h-16 rounded-2xl"></div>
								<div class="skeleton h-16 rounded-2xl"></div>
							</div>

							<div
								v-else-if="! paymentProviders.length"
								class="rounded-2xl border border-bord-sc bg-fill-fv p-4 text-center"
							>
								<div class="mx-auto mb-2 size-10 inline-flex-center rounded-2xl bg-bg-pr text-lab-sc">
									<SvgIcon name="credit-card-02" type="line" classes="size-5"></SvgIcon>
								</div>
								<p class="text-par-s leading-5 text-lab-sc">
									{{ $t('wallet.no_payment_providers') }}
								</p>
							</div>

							<div v-else-if="paymentProviders.length" class="grid gap-2">
								<button
									v-for="provider in paymentProviders"
									v-bind:key="provider.id"
									type="button"
									v-on:click="selectProvider(provider.id)"
									class="flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition-colors"
									v-bind:class="[form.provider === provider.id ? 'border-brand-900 bg-brand-900/5' : 'border-bord-sc bg-fill-fv active:bg-fill-sc']"
								>
									<span class="relative shrink-0">
										<img
											v-bind:src="provider.logo"
											v-bind:alt="provider.name"
											class="size-10 rounded-xl bg-bg-pr object-contain p-1"
										>
										<span
											v-if="form.provider === provider.id"
											class="absolute -right-1 -top-1 z-10 size-5 inline-flex-center rounded-full border-2 border-bg-pr bg-green-900 text-white shadow-sm"
										>
											<SvgIcon name="check-circle" type="solid" classes="size-3.5"></SvgIcon>
										</span>
									</span>
									<div class="min-w-0 flex-1">
										<span class="block truncate text-par-m font-semibold text-lab-pr2">
											{{ provider.name }}
										</span>
									</div>
									<span
										class="size-8 shrink-0 inline-flex-center rounded-full border"
										v-bind:class="[form.provider === provider.id ? 'border-green-900 bg-green-900/10 text-green-900' : 'border-bord-sc bg-bg-pr text-lab-sc']"
									>
										<SvgIcon
											v-if="form.provider === provider.id"
											name="check-circle"
											type="solid"
											classes="size-5"
										></SvgIcon>
										<SvgIcon
											v-else
											name="circle"
											type="line"
											classes="size-5"
										></SvgIcon>
									</span>
								</button>
							</div>

							<p
								v-if="state.formErrors.provider.length"
								class="mt-2 px-1 text-par-s text-red-900"
							>
								{{ state.formErrors.provider[0] }}
							</p>
						</div>

						<div class="mt-5 rounded-2xl bg-fill-fv p-4">
							<p class="text-par-s text-lab-sc" v-html="$t('wallet.tos_agree', { tos_link: $getRoute('terms_of_use') })"></p>
						</div>
					</div>
				</ActionSheetGroup>
			</div>

			<div class="shrink-0 border-t border-bord-pr bg-bg-sc px-4 pb-4 pt-3">
				<PrimaryPillButton
					v-on:click="submitDeposit"
					v-bind:loading="state.isSubmitting"
					v-bind:isDisabled="! isValidForm"
					v-bind:buttonFluid="true"
					buttonSize="lg"
					buttonRole="accent"
					v-bind:buttonText="$t('labels.continue')"
				></PrimaryPillButton>
			</div>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, reactive, computed, onMounted } from 'vue';
	import { useWalletStore } from '@M/store/wallet/wallet.store.js';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import TextInput from '@M/components/forms/TextInput.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		emits: ['close'],
		setup: function(props, context) {
			const walletStore = useWalletStore();

			const state = reactive({
				isLoadingProviders: true,
				isSubmitting: false,
				formErrors: {
					amount: [],
					provider: []
				}
			});

			const form = reactive({
				amount: '',
				provider: ''
			});

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
				return Number(form.amount || 0);
			});
			const isValidForm = computed(() => {
				return ! state.isLoadingProviders && amountValue.value >= minDepositAmount && amountValue.value <= maxDepositAmount && Boolean(form.provider);
			});

			const resetFormErrors = function() {
				state.formErrors.amount = [];
				state.formErrors.provider = [];
			};

			onMounted(async () => {
				try {
					await walletStore.fetchPaymentProviders();

					if(paymentProviders.value.length && ! form.provider) {
						form.provider = paymentProviders.value[0].id;
					}
				}
				catch (error) {
					toastError(error.response?.data?.message || error.message || 'Unable to load payment providers.');
				}
				finally {
					state.isLoadingProviders = false;
				}
			});

			return {
				state: state,
				form: form,
				paymentProviders: paymentProviders,
				walletCurrency: walletCurrency,
				minDepositAmount: minDepositAmount,
				maxDepositAmount: maxDepositAmount,
				isValidForm: isValidForm,
				selectProvider: (providerId) => {
					form.provider = providerId;
					resetFormErrors();
				},
				submitDeposit: async () => {
					if(state.isSubmitting || ! isValidForm.value) {
						return false;
					}

					state.isSubmitting = true;
					resetFormErrors();

					try {
						const response = await walletStore.createDepositPayment({
							amount: form.amount,
							provider: form.provider
						});

						if(response.data.data?.is_hosted_checkout && response.data.data?.checkout_url) {
							window.location.href = response.data.data.checkout_url;

							return false;
						}

						await Promise.allSettled([
							walletStore.fetchWalletData(),
							walletStore.fetchTransactions()
						]);

						toastSuccess(__t('toast.wallet.deposit.success'));
						context.emit('close');
					}
					catch (error) {
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
					}
					finally {
						state.isSubmitting = false;
					}
				}
			};
		},
		components: {
			ActionSheet: ActionSheet,
			SheetTitle: SheetTitle,
			ActionSheetGroup: ActionSheetGroup,
			TextInput: TextInput,
			PrimaryPillButton: PrimaryPillButton,
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>
