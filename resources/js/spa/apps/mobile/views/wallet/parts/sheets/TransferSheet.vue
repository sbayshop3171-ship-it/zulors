<template>
	<ActionSheet v-on:close="$emit('close')" v-bind:isMuted="true">
		<div class="flex h-full flex-col">
			<div class="px-4 pb-3">
				<div class="flex items-start gap-3">
					<div class="size-10 shrink-0"></div>
					<div class="min-w-0 flex-1 text-center">
						<SheetTitle v-bind:title="$t('wallet.transfer_money')"></SheetTitle>
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
						<template v-if="state.step === 'receiver'">
							<TextInput
								v-model="state.form.walletNumber"
								v-bind:labelText="$t('wallet.receiver')"
								v-bind:placeholder="$t('wallet.receiver_placeholder')"
								v-bind:inputErrors="state.formErrors.wallet_number"
								v-bind:hasFeedback="true"
							>
								<template v-slot:feedbackInfo>
									{{ $t('wallet.receiver_helper') }}
								</template>
							</TextInput>

							<div class="mt-4 flex items-center justify-between gap-3">
								<h5 class="text-cap-s font-semibold text-lab-sc">
									{{ state.form.walletNumber.trim() ? $t('labels.more_suggestions') : $t('chat.search_recent') }}
								</h5>
								<span v-if="state.isSearchingRemote" class="text-par-s text-lab-sc">
									{{ $t('labels.loading') }}
								</span>
							</div>

							<div class="mt-3 space-y-2">
								<div v-if="state.isLoadingHistory" class="space-y-2">
									<div v-for="i in 4" v-bind:key="i" class="flex items-center gap-3 rounded-2xl bg-fill-fv p-3">
										<div class="skeleton size-10 shrink-0 rounded-full"></div>
										<div class="min-w-0 flex-1">
											<div class="skeleton mb-2 h-4 w-7/12"></div>
											<div class="skeleton h-3 w-5/12"></div>
										</div>
										<div class="skeleton h-4 w-12 shrink-0"></div>
									</div>
								</div>

								<template v-else>
									<template v-if="visibleReceivers.length">
										<div class="space-y-2">
											<button
												v-for="receiver in visibleReceivers"
												v-bind:key="receiver.wallet_number"
												type="button"
												v-on:click="selectReceiver(receiver)"
												class="flex w-full items-center gap-3 rounded-2xl border px-3 py-3 text-left transition-colors active:bg-fill-sc"
												v-bind:class="[isSelectedReceiver(receiver) ? 'border-brand-900 bg-brand-900/5' : 'border-transparent bg-fill-fv']"
											>
												<AvatarSmall
													v-bind:avatarSrc="receiver.relations.user.avatar_url"
													v-bind:unreadIndicator="false"
												></AvatarSmall>

												<div class="min-w-0 flex-1">
													<p class="truncate text-par-m font-semibold text-lab-pr2">
														{{ receiver.relations.user.name }}
													</p>
													<p class="break-all text-par-s leading-4 text-lab-sc">
														@{{ receiver.wallet_number }}
													</p>
												</div>

												<SvgIcon name="arrow-narrow-right" type="solid" classes="size-5 shrink-0 text-lab-sc"></SvgIcon>
											</button>
										</div>
									</template>

									<template v-if="! visibleReceivers.length && ! state.isSearchingRemote">
										<TimelineEmptyState v-bind:desc="$t('empty_state.no_results')"></TimelineEmptyState>
									</template>
								</template>
							</div>
						</template>

						<template v-else>
							<div class="rounded-2xl border border-bord-pr bg-bg-pr p-3">
								<button
									type="button"
									v-on:click="backToReceiverStep"
									class="flex w-full items-center gap-3 text-left"
								>
									<div class="shrink-0">
										<AvatarSmall
											v-bind:avatarSrc="state.selectedReceiver.relations.user.avatar_url"
											v-bind:unreadIndicator="false"
										></AvatarSmall>
									</div>

									<div class="min-w-0 flex-1">
										<p class="truncate text-par-m font-semibold text-lab-pr2">
											{{ state.selectedReceiver.relations.user.name }}
										</p>
										<p class="break-all text-par-s leading-4 text-lab-sc">
											@{{ state.selectedReceiver.wallet_number }}
										</p>
									</div>

									<SvgIcon name="arrow-narrow-right" type="solid" classes="size-5 shrink-0 text-lab-sc"></SvgIcon>
								</button>

								<button
									type="button"
									v-on:click="backToReceiverStep"
									class="mt-3 flex min-h-10 w-full items-center justify-center rounded-full bg-fill-qt px-4 py-2 text-center text-par-s font-semibold text-lab-pr2 active:bg-fill-sc"
								>
									{{ $t('wallet.reselect_receiver') }}
								</button>
							</div>

							<div class="mt-4">
								<TextInput
									v-model="state.form.amount"
									v-bind:labelText="$t('wallet.transfer_amount')"
									v-bind:placeholder="`0.00 ${walletCurrency.symbol || ''}`.trim()"
									inputType="number"
									v-bind:inputErrors="state.formErrors.amount"
									v-bind:hasFeedback="true"
								>
									<template v-slot:feedbackInfo>
										{{ state.form.amount ? $t('wallet.transfer_commission_amount', { commission_amount: commissionDisplay }) : $t('wallet.transfer_commission_helper') }}
									</template>
								</TextInput>
							</div>

							<div class="mt-4">
								<TextInput
									v-model="state.form.message"
									v-bind:labelText="$t('wallet.transfer_message')"
									v-bind:placeholder="$t('wallet.transfer_message')"
									v-bind:asText="true"
									v-bind:textLength="140"
									v-bind:inputErrors="state.formErrors.message"
									v-bind:hasFeedback="true"
								>
									<template v-slot:feedbackInfo>
										{{ $t('wallet.transfer_message') }}
									</template>
								</TextInput>
							</div>

							<div class="mt-4 rounded-2xl bg-fill-qt p-4">
								<div class="flex items-center justify-between gap-4 text-par-s text-lab-sc">
									<span class="min-w-0 truncate">{{ $t('wallet.transfer_amount') }}</span>
									<strong class="shrink-0 text-lab-pr2">{{ amountDisplay }}</strong>
								</div>
								<div class="mt-2 flex items-center justify-between gap-4 text-par-s text-lab-sc">
									<span class="min-w-0 truncate">{{ $t('wallet.commission') }}</span>
									<strong class="shrink-0 text-lab-pr2">{{ commissionDisplay }}</strong>
								</div>
								<div class="mt-2 flex items-center justify-between gap-4 text-par-s text-lab-sc">
									<span class="min-w-0 truncate">{{ $t('wallet.transaction_total') }}</span>
									<strong class="shrink-0 text-lab-pr2">{{ netAmountDisplay }}</strong>
								</div>
							</div>
						</template>

					</div>
				</ActionSheetGroup>
			</div>

			<div class="shrink-0 border-t border-bord-pr bg-bg-sc px-4 pb-4 pt-3">
				<p class="mb-3 px-2 text-center text-par-s leading-5 text-lab-sc" v-html="$t('wallet.tos_agree', { tos_link: $getRoute('terms_of_use') })"></p>

				<template v-if="state.step === 'receiver'">
					<PrimaryPillButton
						v-on:click="moveToAmountStep"
						v-bind:isDisabled="! state.selectedReceiver"
						v-bind:buttonFluid="true"
						buttonSize="lg"
						buttonRole="accent"
						v-bind:buttonText="$t('labels.continue')"
					></PrimaryPillButton>
				</template>
				<template v-else>
					<PrimaryPillButton
						v-on:click="submitTransfer"
						v-bind:loading="state.isSubmitting"
						v-bind:isDisabled="! isValidForm"
						v-bind:buttonFluid="true"
						buttonSize="lg"
						buttonRole="accent"
						v-bind:buttonText="$t('wallet.make_transfer')"
					></PrimaryPillButton>
				</template>
			</div>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
	import { useWalletStore } from '@M/store/wallet/wallet.store.js';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
	import TextInput from '@M/components/forms/TextInput.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import AvatarSmall from '@M/components/general/avatars/AvatarSmall.vue';
	import TimelineEmptyState from '@M/components/timeline/state/TimelineEmptyState.vue';

	export default defineComponent({
		emits: ['close'],
		setup: function(props, context) {
			const walletStore = useWalletStore();
			let searchTimer = null;

			const state = reactive({
				step: 'receiver',
				isLoadingHistory: true,
				isSearchingRemote: false,
				isSubmitting: false,
				selectedReceiver: null,
				remoteReceiver: null,
				formErrors: {
					wallet_number: [],
					amount: [],
					message: []
				},
				form: {
					walletNumber: '',
					amount: '',
					message: ''
				}
			});

			const walletCurrency = computed(() => {
				return walletStore.walletData?.currency || {
					symbol: ''
				};
			});

			const transferCommissionRate = Number(config('wallet.transfer.commission') || 0);

			const walletHistory = computed(() => {
				return walletStore.receiverHistory || [];
			});

			const recentReceivers = computed(() => {
				if(! state.form.walletNumber.trim()) {
					return walletHistory.value.slice(0, 5);
				}

				return walletHistory.value.filter((receiver) => {
					const term = state.form.walletNumber.trim().toLowerCase();

					return receiver.wallet_number.toLowerCase().includes(term) || receiver.relations.user.name.toLowerCase().includes(term);
				}).slice(0, 5);
			});

			const suggestionReceivers = computed(() => {
				const deduped = [];
				const seen = new Set();

				const pushReceiver = (receiver) => {
					if(! receiver?.wallet_number || seen.has(receiver.wallet_number)) {
						return false;
					}

					seen.add(receiver.wallet_number);
					deduped.push(receiver);
				};

				if(state.remoteReceiver) {
					pushReceiver(state.remoteReceiver);
				}

				walletHistory.value.forEach((receiver) => {
					if(! state.form.walletNumber.trim()) {
						return false;
					}

					const term = state.form.walletNumber.trim().toLowerCase();

					if(receiver.wallet_number.toLowerCase().includes(term) || receiver.relations.user.name.toLowerCase().includes(term)) {
						pushReceiver(receiver);
					}
				});

				return deduped.slice(0, 8);
			});

			const visibleReceivers = computed(() => {
				if(state.form.walletNumber.trim()) {
					return suggestionReceivers.value;
				}

				return recentReceivers.value;
			});

			const selectedAmount = computed(() => Number(state.form.amount || 0));
			const commissionAmount = computed(() => selectedAmount.value * transferCommissionRate / 100);

			const amountDisplay = computed(() => {
				return `${selectedAmount.value.toFixed(2)}${walletCurrency.value.symbol ? ` ${walletCurrency.value.symbol}` : ''}`.trim();
			});

			const commissionDisplay = computed(() => {
				return `${commissionAmount.value.toFixed(2)}${walletCurrency.value.symbol ? ` ${walletCurrency.value.symbol}` : ''}`.trim();
			});

			const netAmountDisplay = computed(() => {
				const netAmount = Math.max(selectedAmount.value - commissionAmount.value, 0);

				return `${netAmount.toFixed(2)}${walletCurrency.value.symbol ? ` ${walletCurrency.value.symbol}` : ''}`.trim();
			});

			const resetErrors = () => {
				state.formErrors.wallet_number = [];
				state.formErrors.amount = [];
				state.formErrors.message = [];
			};

			const clearSearchTimer = () => {
				if(searchTimer) {
					clearTimeout(searchTimer);
					searchTimer = null;
				}
			};

			const scheduleRemoteSearch = (walletNumber) => {
				clearSearchTimer();

				const query = walletNumber.trim();

				state.remoteReceiver = null;

				if(query.length < 3) {
					state.isSearchingRemote = false;

					return false;
				}

				searchTimer = setTimeout(async () => {
					state.isSearchingRemote = true;

					try {
						const response = await walletStore.fetchReceivers(query);

						state.remoteReceiver = response.data.data;

						if(state.remoteReceiver?.wallet_number?.toLowerCase() === query.toLowerCase()) {
							state.selectedReceiver = state.remoteReceiver;
						}
					}
					catch (error) {
						state.remoteReceiver = null;
					}
					finally {
						state.isSearchingRemote = false;
					}
				}, 300);
			};

			watch(() => state.form.walletNumber, (value) => {
				resetErrors();

				const query = value.trim();

				if(state.selectedReceiver && query !== state.selectedReceiver.wallet_number) {
					state.selectedReceiver = null;
					state.step = 'receiver';
				}

				if(state.selectedReceiver && query === state.selectedReceiver.wallet_number) {
					clearSearchTimer();
					state.remoteReceiver = state.selectedReceiver;
					state.isSearchingRemote = false;

					return false;
				}

				const localReceiver = walletHistory.value.find((receiver) => {
					return receiver.wallet_number.toLowerCase() === query.toLowerCase();
				});

				if(localReceiver) {
					clearSearchTimer();
					state.selectedReceiver = localReceiver;
					state.remoteReceiver = localReceiver;
					state.isSearchingRemote = false;

					return false;
				}

				scheduleRemoteSearch(value);
			});

			onMounted(async () => {
				await walletStore.fetchReceiverHistory();

				state.isLoadingHistory = false;
			});

			onBeforeUnmount(() => {
				clearSearchTimer();
			});

			return {
				state: state,
				walletCurrency: walletCurrency,
				recentReceivers: recentReceivers,
				suggestionReceivers: suggestionReceivers,
				visibleReceivers: visibleReceivers,
				amountDisplay: amountDisplay,
				commissionDisplay: commissionDisplay,
				netAmountDisplay: netAmountDisplay,
				isValidForm: computed(() => {
					return !! state.selectedReceiver && selectedAmount.value > 0 && state.form.message.length <= 140;
				}),
				isSelectedReceiver: (receiver) => {
					return receiver.wallet_number === state.selectedReceiver?.wallet_number;
				},
				selectReceiver: (receiver) => {
					clearSearchTimer();
					state.selectedReceiver = receiver;
					state.form.walletNumber = receiver.wallet_number;
					state.remoteReceiver = receiver;
					state.step = 'amount';
				},
				moveToAmountStep: () => {
					if(state.selectedReceiver) {
						state.step = 'amount';
					}
				},
				backToReceiverStep: () => {
					state.step = 'receiver';
				},
				submitTransfer: async () => {
					if(state.isSubmitting || ! state.selectedReceiver) {
						return false;
					}

					state.isSubmitting = true;
					resetErrors();

					try {
						await walletStore.makeTransfer({
							amount: state.form.amount,
							wallet_number: state.selectedReceiver.wallet_number,
							message: state.form.message
						});

						toastSuccess(__t('toast.wallet.transfer.success'));

						await Promise.allSettled([
							walletStore.fetchWalletData(),
							walletStore.fetchTransactions(),
							walletStore.fetchReceiverHistory(true)
						]);

						context.emit('close');
					}
					catch (error) {
						const response = error.response?.data;

						if(response?.errors?.wallet_number) {
							state.formErrors.wallet_number = response.errors.wallet_number;
						}

						if(response?.errors?.amount) {
							state.formErrors.amount = response.errors.amount;
						}

						if(response?.errors?.message) {
							state.formErrors.message = response.errors.message;
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
			ActionSheetGroup: ActionSheetGroup,
			SheetTitle: SheetTitle,
			TextInput: TextInput,
			PrimaryPillButton: PrimaryPillButton,
			PrimaryIconButton: PrimaryIconButton,
			AvatarSmall: AvatarSmall,
			TimelineEmptyState: TimelineEmptyState
		}
	});
</script>
