<template>
    <ContentModal v-on:close="$emit('close')">
        <ModalHeader v-bind:modalTitle="$t('wallet.wallet_info')"></ModalHeader>
        <div class="block p-4">
			<div class="mb-6">
				<InfoList v-bind:listTitle="$t('labels.information')">
					<InfoListItem 
						iconName="wallet-02"
						v-bind:labelText="$t('wallet.wallet_address')"
						v-bind:isCopyable="true"
					v-bind:contentText="walletData.wallet_number"></InfoListItem>
	
					<InfoListItem 
						iconName="currency-euro"
						v-bind:labelText="$t('labels.currency')"
					v-bind:contentText="`${walletData.currency.name} (${walletData.currency.symbol})`"></InfoListItem>
				</InfoList>
			</div>

			<p class="text-cap-s text-lab-sc" v-html="$t('wallet.about_wallet_text', { wallet_name: $embedder('config.wallet.name'), about_link: $embedder('config.wallet.about_link') })"></p>
        </div>
    </ContentModal>
</template>

<script>
    import { defineComponent, computed } from 'vue';
	import { useWalletStore } from '@D/store/wallet/wallet.store.js';

    import ContentModal from '@D/components/general/modals/ContentModal.vue';
    import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue';
	import InfoList from '@D/components/general/info/InfoList.vue';
	import InfoListItem from '@D/components/general/info/InfoListItem.vue';

    export default defineComponent({
        setup: function(props) {
			const walletStore = useWalletStore();

			const walletData = computed(() => {
				return walletStore.walletData;
			});

            return {
				walletData: walletData
            }
        },
        components: {
            ContentModal: ContentModal,
            ModalHeader: ModalHeader,
			InfoList: InfoList,
			InfoListItem: InfoListItem
        }
    });
</script>