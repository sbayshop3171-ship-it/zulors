<template>
    <ContentModal v-on:close="$emit('close')">
        <ModalHeader v-bind:modalTitle="$t('wallet.transaction_details')"></ModalHeader>
        <div class="block p-4">
            <div class="flex justify-center mb-4">
                <div class="size-16 rounded-full bg-fill-qt inline-flex-center shrink-0">
                    <SvgIcon v-bind:name="iconName" classes="size-6 text-brand-900"></SvgIcon>
                </div>
            </div>
            
            <h4 class="text-center text-par-l text-lab-pr font-medium leading-none">
                {{ transactionData.source.name }}
            </h4>
            <div class="text-center">
                <span class="text-brand-900 text-par-s">
                    {{ transactionData.type.label }}
                </span>
            </div>
            <div class="text-center mb-6">
                <span class="text-lg-title font-bold"
                v-bind:class="[transactionData.is_incoming ? 'text-mint' : 'text-red-900']">
                    {{ transactionData.is_incoming ? '+' : '-' }}{{ transactionData.amount.formatted }}
                </span>
            </div>
            <div class="block">
                <StripedTable title="Additional info">
                    <StripedTableRow v-bind:labelText="$t('wallet.transaction_date')" v-bind:valueText="transactionData.date.formatted"></StripedTableRow>
                    <StripedTableRow v-bind:labelText="$t('wallet.transaction_status')" v-bind:valueText="transactionData.status.label"></StripedTableRow>
                    <StripedTableRow v-bind:labelText="$t('wallet.commission')" v-bind:valueText="transactionData.commission.amount.formatted"></StripedTableRow>
                    <StripedTableRow v-bind:labelText="$t('wallet.transaction_tnxid')" v-bind:valueText="transactionData.tnx_id"></StripedTableRow>
                    <StripedTableRow v-bind:labelText="$t('wallet.transaction_currency')" v-bind:valueText="transactionData.currency.name"></StripedTableRow>
                    <StripedTableRow v-bind:labelText="$t('wallet.transaction_total')" v-bind:valueText="transactionData.total.formatted"></StripedTableRow>
                </StripedTable>
            </div>

            <div v-if="transactionData.message" class="block mt-6">
                <h6 class="text-center text-lab-sc text-par-m mb-1">
                    {{ $t('labels.message') }}
                </h6>
                <p class="text-par-m text-center text-lab-pr2">
                    {{ transactionData.message }}
                </p>
            </div>
            <div class="block mt-12 mb-2">
                <p class="text-par-s text-lab-sc">
                    {{ $t('wallet.transaction_helper_text') }}
                </p>
            </div>
            <div class="flex justify-between">
                <span class="text-lab-sc text-par-s" v-html="$t('wallet.support_team_email', { email: $embedder('contacts.support_email') })"></span>

                <a v-bind:href="$getRoute('help_center')" target="_blank" class="text-lab-sc text-par-s underline">
                    {{ $t('labels.help_center') }}
                </a>
            </div>
        </div>
    </ContentModal>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    import ContentModal from '@D/components/general/modals/ContentModal.vue';
    import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue';
    import StripedTable from '@D/components/table/striped/StripedTable.vue';
    import StripedTableRow from '@D/components/table/striped/StripedTableRow.vue';

    export default defineComponent({
        emits: ['close'],
        props: {
            transactionData: {
                type: Object,
                required: true
            }
        },
        setup: function(props) {
            return {
                iconName: computed(() => {
					if (props.transactionData.type.key === 'deposit') {
						return 'credit-card-up';
					}
					
					else if (props.transactionData.type.key === 'transfer') {
						if(props.transactionData.is_incoming) {
							return 'arrow-narrow-down-left';
						}

						return 'arrow-narrow-up-right';
					}

					return 'x';
				})
            }
        },
        components: {
            ContentModal: ContentModal,
            ModalHeader: ModalHeader,
            StripedTable: StripedTable,
            StripedTableRow: StripedTableRow
        }
    });
</script>