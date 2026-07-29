<template>
	<template v-if="state.isLoading">
		<OverviewSkeleton></OverviewSkeleton>
	</template>
	<template v-else>
		<div class="block">
			<div class="rounded-3xl bg-fill-fv p-4 sm:bg-transparent sm:p-0">
				<div class="flex items-start justify-between gap-4 sm:items-end">
					<div class="min-w-0 flex-1">
						<h2 class="truncate text-4xl font-bold leading-none text-mint sm:text-5xl 2xl:text-7xl">
						{{ walletBalance() }}
					</h2>
						<span class="mt-2 inline-flex max-w-full items-center">
							<span class="truncate text-par-s text-lab-sc sm:text-par-n">
							{{ $t('wallet.current_balance') }}
						</span>
							<span v-if="state.isBalanceVisible" v-on:click="hideBalance" class="ml-1 size-5 shrink-0 cursor-pointer inline-flex-center rounded-full text-lab-tr sm:size-4">
							<SvgIcon name="eye-off" classes="size-full text-lab-tr"></SvgIcon>
						</span>
							<span v-else v-on:click="showBalance" class="ml-1 size-5 shrink-0 cursor-pointer inline-flex-center rounded-full text-lab-tr sm:size-4">
							<SvgIcon name="eye" classes="size-full text-lab-tr"></SvgIcon>
						</span>
					</span>

						<div class="mt-4 flex w-full max-w-full items-center gap-3 rounded-2xl border border-bord-pr bg-bg-pr px-3 py-3 sm:inline-flex sm:w-auto sm:rounded-xl sm:border-0 sm:bg-fill-qt sm:py-2">
							<span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-fill-qt text-lab-sc sm:block sm:size-6 sm:bg-transparent">
							<SvgIcon name="wallet-02" type="line" classes="size-full"></SvgIcon>
						</span>
							<div class="min-w-0 flex-1 sm:flex-none">
							<span class="block text-cap-s font-semibold text-lab-sc">
								{{ $t('wallet.wallet_address') }}
							</span>
								<strong class="block truncate text-par-m font-bold text-lab-pr2 sm:max-w-64 sm:text-par-n">
								{{ walletData.wallet_number }}
							</strong>
						</div>
						<PrimaryIconButton
							v-if="state.isWalletNumberCopied"
							iconName="check-circle"
							buttonColor="text-green-500"
							iconSize="icon-small"
						iconType="solid"></PrimaryIconButton>
						<PrimaryIconButton
							v-else
							v-on:click="copyWalletNumber"
							iconName="copy-06"
							iconSize="icon-small"
						iconType="line"></PrimaryIconButton>
					</div>
					</div>
					<div class="shrink-0">
					<PrimaryIconButton
						v-on:click="state.isWalletInfoModalOpen = true"
						iconName="info-circle"
						iconSize="icon-small"
					iconType="line"></PrimaryIconButton>
				</div>
			</div>
			</div>
			<div class="mt-4 grid grid-cols-1 gap-3 min-[380px]:grid-cols-2 sm:grid-cols-3 sm:gap-2">
				<div v-on:click="state.isDepositModalOpen = true" class="relative min-h-32 cursor-pointer rounded-3xl bg-fill-fv px-4 py-4 transition-all ease-linear hover:bg-fill-tr sm:h-40 sm:rounded-xl sm:bg-fill-qt sm:py-3">
					<h4 class="mb-1 block text-par-l font-bold text-lab-pr2 sm:text-par-n">
						{{ $t('labels.deposit') }}
					</h4>
					<p class="block pr-12 text-par-s leading-5 text-lab-sc sm:pr-0 sm:leading-tight" v-html="$t('wallet.add_money_to_wallet')"></p>

					<span class="absolute bottom-4 right-4 size-10 rounded-2xl bg-brand-900 inline-flex-center sm:rounded-xl">
						<SvgIcon name="plus" classes="size-icon-small text-white"></SvgIcon>
					</span>
				</div>
				<div v-on:click="state.isTransferModalOpen = true" class="relative min-h-32 cursor-pointer rounded-3xl bg-fill-fv px-4 py-4 transition-all ease-linear hover:bg-fill-tr sm:h-40 sm:rounded-xl sm:bg-fill-qt sm:py-3">
					<h4 class="mb-1 block text-par-l font-bold text-lab-pr2 sm:text-par-n">
						{{ $t('labels.transfer') }}
					</h4>
					<p class="inline-block pr-12 text-par-s leading-5 text-lab-sc sm:pr-0 sm:leading-tight" v-html="$t('wallet.send_to_another')"></p>
					<span class="absolute bottom-4 right-4 size-10 rounded-2xl bg-bg-pr inline-flex-center sm:rounded-xl sm:bg-white">
						<SvgIcon name="arrow-narrow-right" classes="size-icon-small text-brand-900"></SvgIcon>
					</span>
				</div>
				<a v-bind:href="$getRoute('business_wallet_cashouts')" class="relative min-h-32 cursor-pointer rounded-3xl bg-fill-fv px-4 py-4 transition-all ease-linear hover:bg-fill-tr min-[380px]:col-span-2 sm:col-span-1 sm:h-40 sm:rounded-xl sm:bg-fill-qt sm:py-3">
					<h4 class="mb-1 block text-par-l font-bold text-lab-pr2 sm:text-par-n">
						{{ $t('labels.withdrawal') }}
					</h4>
					<p class="inline-block pr-12 text-par-s leading-5 text-lab-sc sm:pr-0 sm:leading-tight" v-html="$t('wallet.request_withdrawal')"></p>
					<span class="absolute bottom-4 right-4 size-10 rounded-2xl bg-bg-pr inline-flex-center sm:rounded-xl sm:bg-white">
						<SvgIcon name="credit-card-up" classes="size-icon-small text-brand-900"></SvgIcon>
					</span>
				</a>
			</div>
			<div class="mt-4 rounded-3xl bg-fill-fv p-4 sm:mt-2 sm:rounded-none sm:bg-transparent sm:p-0">
				<p class="text-par-s leading-6 text-lab-sc sm:text-cap-s sm:leading-normal" v-html="$t('wallet.about_wallet_text', { wallet_name: $embedder('config.wallet.name'), about_link: $embedder('config.wallet.about_link') })"></p>
			</div>
		</div>
		<Teleport to="body">
			<PrimaryTransition>
				<DepositModal v-if="state.isDepositModalOpen" v-on:close="state.isDepositModalOpen = false"></DepositModal>
			</PrimaryTransition>
			<PrimaryTransition>
				<TransferModal
					v-if="state.isTransferModalOpen"
				v-on:close="state.isTransferModalOpen = false"></TransferModal>
			</PrimaryTransition>
			<PrimaryTransition>
				<WalletInfoModal v-if="state.isWalletInfoModalOpen" v-on:close="state.isWalletInfoModalOpen = false"></WalletInfoModal>
			</PrimaryTransition>
		</Teleport>
	</template>
</template>


<script>
	import { defineComponent, ref, defineAsyncComponent, reactive, onMounted, computed, watch } from 'vue';
	import { useRoute } from 'vue-router';
	import { useWalletStore } from '@D/store/wallet/wallet.store.js';

	import OverviewSkeleton from '@D/views/wallet/parts/skeletons/OverviewSkeleton.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isLoading: true,
				isDepositModalOpen: false,
				isTransferModalOpen: false,
				isBalanceVisible: true,
				isWalletInfoModalOpen: false,
				isWalletNumberCopied: false
			});

			const walletStore = useWalletStore();
			const route = useRoute();

            const walletData = computed(() => {
                return walletStore.walletData;
            });

			const openRouteAction = () => {
				const actionName = String(route.query.action || '').toLowerCase();

				if(actionName === 'deposit') {
					state.isTransferModalOpen = false;
					state.isDepositModalOpen = true;
				}
				else if(actionName === 'transfer') {
					state.isDepositModalOpen = false;
					state.isTransferModalOpen = true;
				}
			};

			onMounted(async () => {
				if(localStorage.getItem('hide_wallet_balance')) {
					state.isBalanceVisible = false;
				}
				else{
					state.isBalanceVisible = true;
				}

				await walletStore.fetchWalletData();

				state.isLoading = false;
				openRouteAction();
			});

			watch(() => route.query.action, () => {
				if(! state.isLoading) {
					openRouteAction();
				}
			});

			return {
				state: state,
				walletData: walletData,
                walletBalance: () => {
                    if(state.isBalanceVisible) {
						return walletData.value.balance.formatted;
                    }
                    else{
						return "*".repeat(walletData.value.balance.formatted.length);
                    }
                },
				showBalance: () => {
					localStorage.removeItem('hide_wallet_balance');
					state.isBalanceVisible = true;
				},
                hideBalance: () => {
                    localStorage.setItem('hide_wallet_balance', 1);
                    state.isBalanceVisible = false;
                },
				copyWalletNumber: () => {
					navigator.clipboard.writeText(walletData.value.wallet_number).then(() => {
						state.isWalletNumberCopied = true;

						setTimeout(() => {
							state.isWalletNumberCopied = false;
						}, 2000);
					});
				}
			}
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			OverviewSkeleton: OverviewSkeleton,
			DepositModal: defineAsyncComponent(() => {
                return import('@D/views/wallet/parts/modals/DepositModal.vue');
            }),
            TransferModal: defineAsyncComponent(() => {
                return import('@D/views/wallet/parts/modals/TransferModal.vue');
            }),
			WalletInfoModal: defineAsyncComponent(() => {
                return import('@D/views/wallet/parts/modals/WalletInfoModal.vue');
            })
		}
	});
</script>
