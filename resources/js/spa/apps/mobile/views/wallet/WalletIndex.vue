<template>
	<div class="block pb-24">
		<Toolbar v-on:close="$router.back()" v-bind:title="$t('wallet.wallet_page')">
			<PrimaryIconButton
				v-on:click="state.isWalletInfoOpen = true"
				iconName="info-circle"
				iconType="line"
				iconSize="icon-small"
				buttonColor="text-lab-pr"
				hoverBg="hover:bg-fill-tr"
				hoverText="hover:text-lab-pr2"
			></PrimaryIconButton>
		</Toolbar>

		<template v-if="state.isLoading">
			<OverviewSkeleton></OverviewSkeleton>
		</template>

		<template v-else>
			<div class="px-4 pb-4">
				<section class="rounded-3xl bg-fill-fv p-4">
					<div class="flex items-start justify-between gap-4">
						<div class="min-w-0 flex-1">
							<div class="flex items-center gap-2 text-par-s text-lab-sc">
								<span class="truncate">
									{{ $t('wallet.current_balance') }}
								</span>
								<PrimaryIconButton
									v-on:click="toggleBalanceVisibility"
									v-bind:iconName="state.isBalanceVisible ? 'eye' : 'eye-off'"
									iconType="line"
									iconSize="icon-small"
									buttonColor="text-lab-sc"
									hoverBg="hover:bg-fill-tr"
									hoverText="hover:text-lab-pr2"
								></PrimaryIconButton>
							</div>

							<div class="mt-2 flex items-end gap-2">
								<h2 class="text-4xl font-bold leading-none text-lab-pr2 sm:text-5xl">
									{{ walletBalanceLabel }}
								</h2>
							</div>
						</div>
					</div>

					<div class="mt-4 flex items-center gap-3 rounded-2xl border border-bord-pr bg-bg-pr px-3 py-3">
						<div class="size-11 shrink-0 inline-flex-center rounded-2xl bg-fill-qt text-lab-pr2">
							<SvgIcon name="wallet-02" type="line" classes="size-5"></SvgIcon>
						</div>

						<div class="min-w-0 flex-1">
							<span class="block text-cap-s font-semibold text-lab-sc">
								{{ $t('wallet.wallet_address') }}
							</span>
							<strong class="block truncate text-par-m font-semibold text-lab-pr2">
								{{ walletData.wallet_number }}
							</strong>
						</div>

						<PrimaryIconButton
							v-if="state.isWalletNumberCopied"
							iconName="check-circle"
							iconType="solid"
							iconSize="icon-small"
							buttonColor="text-green-500"
						></PrimaryIconButton>
						<PrimaryIconButton
							v-else
							v-on:click="copyWalletNumber"
							iconName="copy-06"
							iconType="line"
							iconSize="icon-small"
							buttonColor="text-lab-sc"
							hoverBg="hover:bg-fill-tr"
							hoverText="hover:text-lab-pr2"
						></PrimaryIconButton>
					</div>
				</section>

				<section class="mt-4 grid grid-cols-1 gap-3 min-[360px]:grid-cols-2">
					<button
						type="button"
						v-on:click="openDepositSheet"
						class="relative min-h-36 rounded-3xl bg-fill-fv p-4 text-left transition-colors active:bg-fill-sc"
					>
						<div class="flex items-start justify-between gap-3">
							<div class="min-w-0 flex-1">
								<h4 class="text-par-l font-semibold text-lab-pr2">
									{{ $t('wallet.deposit_money') }}
								</h4>
								<p class="mt-1 text-par-s leading-5 text-lab-sc">
									{{ $t('wallet.add_money_to_wallet') }}
								</p>
							</div>

							<span class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-900 text-white">
								<SvgIcon name="plus" type="solid" classes="size-5"></SvgIcon>
							</span>
						</div>
					</button>

					<button
						type="button"
						v-on:click="openTransferSheet"
						class="relative min-h-36 rounded-3xl bg-fill-fv p-4 text-left transition-colors active:bg-fill-sc"
					>
						<div class="flex items-start justify-between gap-3">
							<div class="min-w-0 flex-1">
								<h4 class="text-par-l font-semibold text-lab-pr2">
									{{ $t('wallet.transfer_money') }}
								</h4>
								<p class="mt-1 text-par-s leading-5 text-lab-sc">
									{{ $t('wallet.send_to_another') }}
								</p>
							</div>

							<span class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-bg-pr text-brand-900">
								<SvgIcon name="arrow-narrow-right" type="solid" classes="size-5"></SvgIcon>
							</span>
						</div>
					</button>

					<a
						v-bind:href="$getRoute('business_wallet_cashouts')"
						class="relative min-h-32 rounded-3xl bg-fill-fv p-4 text-left transition-colors active:bg-fill-sc min-[360px]:col-span-2"
					>
						<div class="flex items-start justify-between gap-3">
							<div class="min-w-0 flex-1">
								<h4 class="text-par-l font-semibold text-lab-pr2">
									{{ $t('labels.withdrawal') }}
								</h4>
								<p class="mt-1 text-par-s leading-5 text-lab-sc">
									{{ $t('wallet.request_withdrawal') }}
								</p>
							</div>

							<span class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-bg-pr text-brand-900">
								<SvgIcon name="credit-card-up" classes="size-5 text-brand-900"></SvgIcon>
							</span>
						</div>
					</a>
				</section>

				<section class="mt-4 rounded-3xl bg-fill-fv p-4">
					<p
						class="text-par-s leading-6 text-lab-sc"
						v-html="$t('wallet.about_wallet_text', {
							wallet_name: $embedder('config.wallet.name'),
							about_link: $embedder('config.wallet.about_link')
						})"
					></p>
				</section>

				<section class="mt-5">
					<div class="px-1">
						<h3 class="text-par-l font-semibold text-lab-pr2">
							{{ $t('wallet.transactions_history') }}
						</h3>
					</div>

					<div class="mt-3 space-y-4">
						<template v-if="hasTransactions">
							<div v-if="transactions.today.length" class="space-y-2">
								<div class="px-1 text-cap-s font-semibold text-lab-sc">
									{{ $t('wallet.today') }}
								</div>

								<WalletTransactionItem
									v-for="transaction in transactions.today"
									v-bind:key="transaction.id"
									v-bind:transaction="transaction"
									v-on:select="openTransactionSheet"
								></WalletTransactionItem>
							</div>

							<div v-if="transactions.thisWeek.length" class="space-y-2">
								<div class="px-1 text-cap-s font-semibold text-lab-sc">
									{{ $t('wallet.this_week') }}
								</div>

								<WalletTransactionItem
									v-for="transaction in transactions.thisWeek"
									v-bind:key="transaction.id"
									v-bind:transaction="transaction"
									v-on:select="openTransactionSheet"
								></WalletTransactionItem>
							</div>

							<div v-if="transactions.thisMonth.length" class="space-y-2">
								<div class="px-1 text-cap-s font-semibold text-lab-sc">
									{{ $t('wallet.this_month') }}
								</div>

								<WalletTransactionItem
									v-for="transaction in transactions.thisMonth"
									v-bind:key="transaction.id"
									v-bind:transaction="transaction"
									v-on:select="openTransactionSheet"
								></WalletTransactionItem>
							</div>

							<div v-if="transactions.other.length" class="space-y-2">
								<div class="px-1 text-cap-s font-semibold text-lab-sc">
									{{ $t('wallet.other') }}
								</div>

								<WalletTransactionItem
									v-for="transaction in transactions.other"
									v-bind:key="transaction.id"
									v-bind:transaction="transaction"
									v-on:select="openTransactionSheet"
								></WalletTransactionItem>
							</div>
						</template>

						<template v-else>
							<TimelineEmptyState v-bind:desc="$t('empty_state.wallet.transactions')"></TimelineEmptyState>
						</template>
					</div>
				</section>
			</div>
		</template>
	</div>

	<Teleport to="body">
		<DepositSheet
			v-if="state.isDepositSheetOpen"
			v-on:close="closeSheets"
		></DepositSheet>

		<TransferSheet
			v-if="state.isTransferSheetOpen"
			v-on:close="closeSheets"
		></TransferSheet>

		<TransactionSheet
			v-if="state.selectedTransaction"
			v-bind:transaction="state.selectedTransaction"
			v-on:close="state.selectedTransaction = null"
		></TransactionSheet>

		<Backdrop
			v-if="state.isWalletInfoOpen"
			class="bg-black/35"
			v-on:click="state.isWalletInfoOpen = false"
		>
			<div class="flex min-h-full items-center justify-center px-4 py-6">
				<section
					role="dialog"
					aria-modal="true"
					class="w-full max-w-[23rem] overflow-hidden rounded-[2rem] bg-bg-pr shadow-2xl"
					v-on:click.stop
				>
					<header class="flex items-start gap-3 border-b border-bord-pr px-4 py-4">
						<span class="inline-flex size-11 shrink-0 items-center justify-center rounded-2xl bg-fill-fv text-brand-900">
							<SvgIcon name="wallet-02" type="line" classes="size-5"></SvgIcon>
						</span>

						<div class="min-w-0 flex-1">
							<h3 class="text-par-l font-semibold leading-6 text-lab-pr2">
								{{ $t('wallet.wallet_info') }}
							</h3>
							<p class="mt-1 text-par-s leading-5 text-lab-sc">
								{{ $t('wallet.current_balance') }}
							</p>
						</div>

						<PrimaryIconButton
							v-on:click="state.isWalletInfoOpen = false"
							iconName="x"
							iconType="solid"
							iconSize="icon-small"
							buttonColor="text-lab-sc"
							hoverBg="hover:bg-fill-tr"
							hoverText="hover:text-lab-pr2"
						></PrimaryIconButton>
					</header>

					<div class="max-h-[calc(var(--app-viewport-height,100dvh)-13rem)] overflow-y-auto px-4 py-4">
						<div class="rounded-3xl bg-fill-fv p-4">
							<div class="flex items-start gap-3">
								<span class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-bg-pr text-lab-pr2">
									<SvgIcon name="wallet-02" type="line" classes="size-5"></SvgIcon>
								</span>

								<div class="min-w-0 flex-1">
									<span class="block text-cap-s font-semibold uppercase text-lab-sc">
										{{ $t('wallet.wallet_address') }}
									</span>
									<strong class="mt-1 block break-all text-par-m font-semibold leading-6 text-lab-pr2">
										{{ walletData.wallet_number }}
									</strong>
								</div>

								<PrimaryIconButton
									v-if="state.isWalletNumberCopied"
									iconName="check-circle"
									iconType="solid"
									iconSize="icon-small"
									buttonColor="text-green-500"
								></PrimaryIconButton>
								<PrimaryIconButton
									v-else
									v-on:click="copyWalletNumber"
									iconName="copy-06"
									iconType="line"
									iconSize="icon-small"
									buttonColor="text-lab-sc"
									hoverBg="hover:bg-bg-pr"
									hoverText="hover:text-lab-pr2"
								></PrimaryIconButton>
							</div>

							<div class="mt-4 flex items-start gap-3 rounded-2xl bg-bg-pr p-3">
								<span class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-fill-qt text-lab-pr2">
									<SvgIcon name="currency-euro" type="line" classes="size-5"></SvgIcon>
								</span>

								<div class="min-w-0 flex-1">
									<span class="block text-cap-s font-semibold uppercase text-lab-sc">
										{{ $t('labels.currency') }}
									</span>
									<strong class="mt-1 block break-words text-par-m font-semibold leading-6 text-lab-pr2">
										{{ walletCurrencyLabel }}
									</strong>
								</div>
							</div>
						</div>

						<div class="mt-3 rounded-3xl bg-fill-fv p-4">
							<p
								class="break-words text-par-s leading-6 text-lab-sc"
								v-html="$t('wallet.about_wallet_text', {
									wallet_name: $embedder('config.wallet.name'),
									about_link: $embedder('config.wallet.about_link')
								})"
							></p>
						</div>

						<div class="mt-3 grid gap-2">
							<div class="flex items-start gap-3 rounded-2xl bg-fill-fv p-3">
								<span class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-bg-pr text-lab-pr2">
									<SvgIcon name="mail-04" type="line" classes="size-5"></SvgIcon>
								</span>

								<p
									class="min-w-0 flex-1 break-words text-par-s leading-6 text-lab-sc"
									v-html="$t('wallet.support_team_email', { email: $embedder('contacts.support_email') })"
								></p>
							</div>

							<a
								v-bind:href="$getRoute('help_center')"
								target="_blank"
								rel="noopener noreferrer"
								class="flex items-center gap-3 rounded-2xl bg-fill-fv p-3 text-par-s font-semibold text-brand-900 active:bg-fill-sc"
							>
								<span class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl bg-bg-pr">
									<SvgIcon name="info-circle" type="line" classes="size-5"></SvgIcon>
								</span>
								<span class="min-w-0 flex-1 break-words">
									{{ $t('labels.help_center') }}
								</span>
							</a>
						</div>
					</div>

					<div class="border-t border-bord-pr p-4">
						<button
							type="button"
							class="w-full rounded-full bg-lab-pr2 px-5 py-4 text-center text-par-m font-semibold leading-none text-bg-pr active:opacity-80"
							v-on:click="state.isWalletInfoOpen = false"
						>
							{{ $t('labels.close') }}
						</button>
					</div>
				</section>
			</div>
		</Backdrop>
	</Teleport>
</template>

<script>
	import { defineComponent, defineAsyncComponent, computed, reactive, onMounted, onBeforeUnmount, watch, ref } from 'vue';
	import { useRoute, useRouter } from 'vue-router';
	import { useWalletStore } from '@M/store/wallet/wallet.store.js';
	import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import Backdrop from '@M/components/general/modals/Backdrop.vue';
	import TimelineEmptyState from '@M/components/timeline/state/TimelineEmptyState.vue';
	import OverviewSkeleton from '@M/views/wallet/parts/skeletons/OverviewSkeleton.vue';
	import WalletTransactionItem from '@M/views/wallet/parts/transactions/WalletTransactionItem.vue';

	export default defineComponent({
		setup: function() {
			const route = useRoute();
			const router = useRouter();
			const walletStore = useWalletStore();
			const handledPaymentStatus = ref('');

			const state = reactive({
				isLoading: true,
				isDepositSheetOpen: false,
				isTransferSheetOpen: false,
				isWalletInfoOpen: false,
				isWalletNumberCopied: false,
				selectedTransaction: null,
				isBalanceVisible: true
			});

			const walletData = computed(() => {
				return walletStore.walletData || {
					balance: {
						formatted: '0.00'
					},
					wallet_number: '-',
					currency: {
						symbol: ''
					}
				};
			});

			const transactions = computed(() => {
				return walletStore.transactions || {
					today: [],
					thisWeek: [],
					thisMonth: [],
					other: []
				};
			});

			const hasTransactions = computed(() => {
				return transactions.value.today.length || transactions.value.thisWeek.length || transactions.value.thisMonth.length || transactions.value.other.length;
			});

			const walletBalanceLabel = computed(() => {
				const balanceText = walletData.value.balance.formatted || '0.00';

				if(state.isBalanceVisible) {
					return balanceText;
				}

				return '*'.repeat(Math.max(balanceText.length, 4));
			});

			const walletCurrencyLabel = computed(() => {
				const currency = walletData.value.currency || {};

				if(currency.name && currency.symbol) {
					return `${currency.name} (${currency.symbol})`;
				}

				return currency.name || currency.symbol || '-';
			});

			const loadWalletData = async () => {
				const results = await Promise.allSettled([
					walletStore.fetchWalletData(),
					walletStore.fetchTransactions()
				]);

				const failure = results.find((result) => result.status === 'rejected');

				if(failure?.reason) {
					toastError(failure.reason.response?.data?.message || failure.reason.message || 'Unable to load wallet data.');
				}
			};

			const handlePaymentReturn = async () => {
				const paymentStatus = String(route.query.payment || '').toLowerCase();

				if(! paymentStatus || handledPaymentStatus.value === paymentStatus) {
					return false;
				}

				handledPaymentStatus.value = paymentStatus;

				if(paymentStatus === 'success') {
					toastSuccess(__t('toast.wallet.deposit.success'));
				}
				else if(paymentStatus === 'pending') {
					toastSuccess(__t('toast.wallet.deposit.pending'));
				}
				else if(paymentStatus === 'cancelled') {
					toastError(__t('toast.wallet.deposit.cancelled'));
				}
				else if(paymentStatus === 'failed') {
					toastError(__t('toast.wallet.deposit.failed'));
				}

				await loadWalletData();

				const nextQuery = Object.assign({}, route.query);
				delete nextQuery.payment;

				router.replace({
					path: route.path,
					query: nextQuery,
					hash: route.hash
				});
			};

			useInstantRevalidation(loadWalletData, {
				routeKey: () => route.fullPath,
				minDelay: 1500
			});

			const clearSheets = () => {
				state.isDepositSheetOpen = false;
				state.isTransferSheetOpen = false;
				state.isWalletInfoOpen = false;
				state.selectedTransaction = null;
			};

			const openDepositSheet = () => {
				clearSheets();
				state.isDepositSheetOpen = true;
			};

			const openTransferSheet = () => {
				clearSheets();
				state.isTransferSheetOpen = true;
			};

			const openRouteAction = () => {
				const actionName = String(route.query.action || '').toLowerCase();

				if(actionName === 'deposit') {
					openDepositSheet();
				}
				else if(actionName === 'transfer') {
					openTransferSheet();
				}
			};

			onMounted(async () => {
				state.isBalanceVisible = ! localStorage.getItem('hide_wallet_balance');

				await loadWalletData();

				state.isLoading = false;
				handlePaymentReturn();
				openRouteAction();
			});

			watch(() => route.query.action, () => {
				if(! state.isLoading) {
					openRouteAction();
				}
			});

			watch(() => route.query.payment, () => {
				if(! state.isLoading) {
					handlePaymentReturn();
				}
			});

			onBeforeUnmount(() => {
				clearSheets();
			});

			return {
				state: state,
				walletData: walletData,
				transactions: transactions,
				hasTransactions: hasTransactions,
				walletBalanceLabel: walletBalanceLabel,
				walletCurrencyLabel: walletCurrencyLabel,
				copyWalletNumber: () => {
					navigator.clipboard.writeText(walletData.value.wallet_number).then(() => {
						state.isWalletNumberCopied = true;

						setTimeout(() => {
							state.isWalletNumberCopied = false;
						}, 2000);
					});
				},
				toggleBalanceVisibility: () => {
					state.isBalanceVisible = ! state.isBalanceVisible;

					if(state.isBalanceVisible) {
						localStorage.removeItem('hide_wallet_balance');
					}
					else {
						localStorage.setItem('hide_wallet_balance', '1');
					}
				},
				openDepositSheet: openDepositSheet,
				openTransferSheet: openTransferSheet,
				openTransactionSheet: (transaction) => {
					clearSheets();
					state.selectedTransaction = transaction;
				},
				closeSheets: () => {
					clearSheets();
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			PrimaryIconButton: PrimaryIconButton,
			Backdrop: Backdrop,
			TimelineEmptyState: TimelineEmptyState,
			OverviewSkeleton: OverviewSkeleton,
			WalletTransactionItem: WalletTransactionItem,
			DepositSheet: defineAsyncComponent(() => import('@M/views/wallet/parts/sheets/DepositSheet.vue')),
			TransferSheet: defineAsyncComponent(() => import('@M/views/wallet/parts/sheets/TransferSheet.vue')),
			TransactionSheet: defineAsyncComponent(() => import('@M/views/wallet/parts/sheets/TransactionSheet.vue'))
		}
	});
</script>
