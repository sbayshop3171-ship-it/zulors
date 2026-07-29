import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useWalletStore = defineStore('mobile_wallet_store', {
	state: function() {
		return {
			walletData: null,
			transactions: {
				today: [],
				thisWeek: [],
				thisMonth: [],
				other: []
			},
			paymentProviders: [],
			receiverHistory: []
		};
	},
	actions: {
		fetchWalletData: async function() {
			const response = await colibriAPI().wallet().getFrom('data');

			this.walletData = response.data.data;

			return response;
		},
		fetchPaymentProviders: async function() {
			const response = await colibriAPI().wallet().params({
				_: Date.now()
			}).getFrom('payment/providers');

			this.paymentProviders = response.data.data;

			return response;
		},
		createDepositPayment: async function(data) {
			return await colibriAPI().wallet().with(data).sendTo('deposit');
		},
		fetchTransactions: async function() {
			const response = await colibriAPI().wallet().getFrom('transactions');

			this.transactions.today = response.data.data.today;
			this.transactions.thisWeek = response.data.data.this_week;
			this.transactions.thisMonth = response.data.data.this_month;
			this.transactions.other = response.data.data.other;

			return response;
		},
		fetchReceivers: async function(walletNumber) {
			return await colibriAPI().wallet().params({
				wallet_number: walletNumber
			}).getFrom('receiver/find');
		},
		fetchReceiverHistory: async function(force = false) {
			if(this.receiverHistory.length && ! force) {
				return this.receiverHistory;
			}

			try {
				const response = await colibriAPI().wallet().getFrom('receiver/history');

				this.receiverHistory = response.data.data;

				return response;
			}
			catch (error) {
				this.receiverHistory = [];

				return [];
			}
		},
		makeTransfer: async function(data) {
			return await colibriAPI().wallet().with(data).sendTo('transfer');
		}
	}
});

export { useWalletStore };
