<template>
    <div class="my-top-offset block px-4 sm:px-0">
        <div class="mb-6 sm:mb-10 lg:mb-12">
            <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('wallet.wallet_page')"></PageTitle>
        </div>

        <div class="w-full max-w-content 2xl:max-w-3xl">
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
        },
        components: {
            PageTitle: PageTitle,
            WalletOverview: WalletOverview,
            WalletTransactions: WalletTransactions
        }
    });
</script>
