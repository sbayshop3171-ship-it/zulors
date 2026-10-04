<template>
    <div class="my-top-offset block px-4 sm:px-0">
        <div class="mb-6 sm:mb-10 lg:mb-12">
            <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('wallet.wallet_page')"></PageTitle>
        </div>

        <div class="w-full max-w-content 2xl:max-w-3xl">
            <button v-if="walletStore.walletData && walletStore.walletData.reward_progress" type="button" class="mb-4 block w-full rounded-2xl border border-brand-200 bg-brand-50 p-4 text-left text-lab-pr2" v-on:click="rewardModalOpen = true">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold">Monthly Reward</p>
                        <p class="text-par-s text-lab-sc">{{ walletStore.walletData.reward_progress.posts_completed }}/{{ walletStore.walletData.reward_progress.required_posts }} posts completed</p>
                    </div>
                    <button v-if="walletStore.walletData.reward_progress.status === 'ready'" type="button" class="rounded-xl bg-brand-900 px-3 py-2 text-par-s font-semibold text-white" v-on:click="claimReward">Claim ${{ walletStore.walletData.reward_progress.reward_amount }}</button>
                    <span class="text-par-s font-semibold">{{ walletStore.walletData.reward_progress.status === 'claimed' ? 'Claimed' : 'View tasks' }}</span>
                </div>
            </button>
            <div v-if="rewardModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" v-on:click.self="rewardModalOpen = false">
                <div class="w-full max-w-md rounded-3xl bg-bg-pr p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div><h3 class="text-xl font-bold text-lab-pr2">Monthly Reward</h3><p class="mt-1 text-par-s text-lab-sc">Complete these tasks to unlock your ${{ walletStore.walletData.reward_progress.reward_amount }} reward.</p></div>
                        <button type="button" class="text-2xl text-lab-sc" v-on:click="rewardModalOpen = false">&times;</button>
                    </div>
                    <div class="mt-5 space-y-3 text-par-s">
                        <div class="flex justify-between"><span>Account age</span><strong>{{ walletStore.walletData.reward_progress.account_age_days }}/{{ walletStore.walletData.reward_progress.account_age_required }} days</strong></div>
                        <div class="flex justify-between"><span>Valid posts this cycle</span><strong>{{ walletStore.walletData.reward_progress.posts_completed }}/{{ walletStore.walletData.reward_progress.required_posts }}</strong></div>
                    </div>
                    <button v-if="walletStore.walletData.reward_progress.status === 'ready'" type="button" class="mt-6 w-full rounded-xl bg-brand-900 px-4 py-3 font-semibold text-white" v-on:click="claimReward">Claim Reward</button>
                    <p v-else class="mt-6 text-center text-par-s font-semibold text-lab-sc">Status: {{ walletStore.walletData.reward_progress.status === 'claimed' ? 'Claimed' : 'Locked' }}</p>
                </div>
            </div>
            <div class="mb-6 sm:mb-8">
                <WalletOverview></WalletOverview>
            </div>
            <div class="block">
                <WalletTransactions></WalletTransactions>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, onMounted, watch } from 'vue';
    import { useRoute, useRouter } from 'vue-router';
    import { useWalletStore } from '@D/store/wallet/wallet.store.js';
    import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';
    
    import PageTitle from '@D/components/layout/PageTitle.vue';
    import WalletOverview from '@D/views/wallet/parts/WalletOverview.vue';
    import WalletTransactions from '@D/views/wallet/parts/WalletTransactions.vue';

    export default defineComponent({
        setup: function() {
            const route = useRoute();
            const router = useRouter();
            const walletStore = useWalletStore();
            const handledPaymentStatus = ref('');
            const rewardModalOpen = ref(false);

            const claimReward = async () => {
                try {
                    await walletStore.claimReward();
                    toastSuccess('Monthly reward claimed successfully.');
                }
                catch(error) {
                    toastError(error?.response?.data?.message || 'Reward is not ready to claim.');
                }
            };

            const refreshWallet = async () => {
                await Promise.allSettled([
                    walletStore.fetchWalletData(),
                    walletStore.fetchTransactions()
                ]);
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

                await refreshWallet();

                const nextQuery = Object.assign({}, route.query);
                delete nextQuery.payment;

                router.replace({
                    path: route.path,
                    query: nextQuery,
                    hash: route.hash
                });
            };

            useInstantRevalidation(refreshWallet, {
                routeKey: () => route.fullPath,
                minDelay: 1500
            });

            onMounted(handlePaymentReturn);

            watch(() => route.query.payment, handlePaymentReturn);

            return {
                walletStore,
                claimReward,
                rewardModalOpen
            };
        },
        components: {
            PageTitle: PageTitle,
            WalletOverview: WalletOverview,
            WalletTransactions: WalletTransactions
        }
    });
</script>
