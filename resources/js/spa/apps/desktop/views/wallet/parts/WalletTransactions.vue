<template>
	<div class="block">
		<h4 class="mb-3 px-1 text-par-l font-bold text-lab-pr2 sm:mb-2 sm:px-0 sm:font-medium">
			{{ $t('wallet.transactions_history') }}
		</h4>
		<div class="flex">
			<div class="w-full max-w-content">
				<template v-if="state.isLoading">
					<TransactionItemSkeleton v-for="i in 5" v-bind:key="i"></TransactionItemSkeleton>
				</template>
				<template v-else>
					<template v-if="isEmpty">
						<ContentContainer>
							<FluidEmptyState
								v-bind:text="$t('empty_state.wallet.transactions')"
							iconName="receipt"></FluidEmptyState>
						</ContentContainer>
					</template>
					<template v-else>
						<div v-if="transactions.today.length" class="mb-4 block last:mb-0 sm:mb-2">
							<div class="mb-2 rounded-xl bg-fill-qt px-4 py-2 text-par-s text-lab-sc sm:rounded-none sm:py-1">
								{{ $t('wallet.today') }}
							</div>
							 
							<TransactionItem
								v-for="transItem in transactions.today"
								v-bind:transactionData="transItem"
							v-on:click="showTransaction(transItem)"></TransactionItem>
						</div>
						<div v-if="transactions.thisWeek.length" class="mb-4 block last:mb-0 sm:mb-2">
							<div class="mb-2 rounded-xl bg-fill-qt px-4 py-2 text-par-s text-lab-sc sm:rounded-none sm:py-1">
								{{ $t('wallet.this_week') }}
							</div>
							 
							<TransactionItem
								v-for="transItem in transactions.thisWeek"
								v-bind:transactionData="transItem"
							v-on:click="showTransaction(transItem)"></TransactionItem>
						</div>
						<div v-if="transactions.thisMonth.length" class="mb-4 block last:mb-0 sm:mb-2">
							<div class="mb-2 rounded-xl bg-fill-qt px-4 py-2 text-par-s text-lab-sc sm:rounded-none sm:py-1">
								{{ $t('wallet.this_month') }}
							</div>
							<TransactionItem
								v-for="transItem in transactions.thisMonth"
								v-bind:transactionData="transItem"
							v-on:click="showTransaction(transItem)"></TransactionItem>
						</div>
						<div v-if="transactions.other.length" class="mb-4 block last:mb-0 sm:mb-2">
							<div class="mb-2 rounded-xl bg-fill-qt px-4 py-2 text-par-s text-lab-sc sm:rounded-none sm:py-1">
								{{ $t('wallet.other') }}
							</div>
							<TransactionItem
								v-for="transItem in transactions.other"
								v-bind:transactionData="transItem"
							v-on:click="showTransaction(transItem)"></TransactionItem>
						</div>
					</template>
				</template>
			</div>
		</div>
	</div>
	<Teleport to="body">
		<PrimaryTransition>
			<TransactionModal
				v-if="transactionDetails"
				v-on:close="transactionDetails = null"
			v-bind:transactionData="transactionDetails"></TransactionModal>
		</PrimaryTransition>
	</Teleport>
</template>


<script>
	import { defineComponent, defineAsyncComponent, reactive, ref, computed, onMounted } from 'vue';
	import { useWalletStore } from '@D/store/wallet/wallet.store.js';

	import ContentContainer from '@D/components/layout/ContentContainer.vue';
	import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';
	import TransactionItem from '@D/views/wallet/parts/transactions/TransactionItem.vue';
	import TransactionItemSkeleton from '@D/views/wallet/parts/transactions/TransactionItemSkeleton.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isTransactionModalOpen: false,
				isLoading: true
			});

			const walletStore = useWalletStore();
			const transactionDetails = ref(null);

			const transactions = computed(() => {
				return walletStore.transactions;
			});

			onMounted(async () => {
				await walletStore.fetchTransactions();
				state.isLoading = false;
			});

			return {
				state: state,
				transactions: transactions,
				transactionDetails: transactionDetails,
				showTransaction: function(transItem) {
					transactionDetails.value = transItem;
				},
				isEmpty: computed(() => {
					return transactions.value.today.length === 0 && transactions.value.thisWeek.length === 0 && transactions.value.thisMonth.length === 0 && transactions.value.other.length === 0;
				})
			}
		},
		components: {
			TransactionItem: TransactionItem,
			TransactionItemSkeleton: TransactionItemSkeleton,
			TransactionModal: defineAsyncComponent(() => {
                return import('@D/views/wallet/parts/modals/TransactionModal.vue');
            }),
			FluidEmptyState: FluidEmptyState,
			ContentContainer: ContentContainer
		}
	});
</script>
