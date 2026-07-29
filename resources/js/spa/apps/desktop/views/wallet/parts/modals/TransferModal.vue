<template>
    <ContentModal v-on:close="$emit('close')">
        <div class="flex max-h-[calc(100vh-2rem)] flex-col overflow-hidden">
            <ModalHeader v-bind:modalTitle="$t('wallet.transfer_money')"></ModalHeader>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <MakeTransfer
                    v-if="receiverData"
                    v-on:cancel="cancelTransfer"
                    v-on:close="$emit('close')"
                v-bind:receiverData="receiverData"></MakeTransfer>
                <SelectReceiver v-else v-on:select="selectReceiver"></SelectReceiver>
            </div>
            <div class="shrink-0 border-t border-bord-pr p-4">
                <p class="text-par-s text-lab-sc" v-html="$t('wallet.tos_agree', { tos_link: $getRoute('terms_of_use') })"></p>
            </div>
        </div>
    </ContentModal>
</template>

<script>
    import { defineComponent, ref, computed } from 'vue';

    import ContentModal from '@D/components/general/modals/ContentModal.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue';
    import SelectReceiver from '@D/views/wallet/parts/modals/transfer/SelectReceiver.vue';
    import MakeTransfer from '@D/views/wallet/parts/modals/transfer/MakeTransfer.vue';

    export default defineComponent({
        emits: ['close'],
        setup: function(props, context) {
            const receiverData = ref(null);

            return {
                selectReceiver: (receiverItem) => {
                    receiverData.value = receiverItem;
                },
                cancelTransfer: () => {
                    receiverData.value = null;
                },
                receiverData: receiverData
            }
        },
        components: {
            ContentModal: ContentModal,
            ModalHeader: ModalHeader,
            SelectReceiver: SelectReceiver,
            MakeTransfer: MakeTransfer
        }
    });
</script>
