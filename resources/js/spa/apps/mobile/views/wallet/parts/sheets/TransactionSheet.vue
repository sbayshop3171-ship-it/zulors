<template>
	<ActionSheet v-on:close="$emit('close')" v-bind:isMuted="true">
		<div class="flex h-full flex-col">
			<div class="px-4 pb-3 text-center">
				<SheetTitle v-bind:title="$t('wallet.transaction_details')"></SheetTitle>
			</div>

			<div class="flex-1 overflow-y-auto px-4 pb-4">
				<ActionSheetGroup>
					<div class="p-4">
						<div class="flex justify-center">
							<div
								class="size-16 inline-flex-center rounded-full"
								v-bind:class="[transaction.is_incoming ? 'bg-green-500/10 text-green-600' : 'bg-brand-900/10 text-brand-900']"
							>
								<SvgIcon v-bind:name="iconName" type="solid" classes="size-6"></SvgIcon>
							</div>
						</div>

						<h4 class="mt-4 text-center text-par-l font-semibold text-lab-pr2">
							{{ sourceName }}
						</h4>
						<p class="text-center text-par-s text-lab-sc">
							{{ transaction.type.label }}
						</p>

						<div class="mt-3 text-center">
							<span
								class="text-2xl font-bold"
								v-bind:class="[transaction.is_incoming ? 'text-green-600' : 'text-red-900']"
							>
								{{ signedAmount }}
							</span>
						</div>
					</div>
				</ActionSheetGroup>

				<div class="mt-4">
					<InfoList v-bind:listTitle="$t('wallet.additional_info')">
						<InfoListItem
							iconName="calendar-check-01"
							v-bind:labelText="$t('wallet.transaction_date')"
							v-bind:contentText="transaction.date.formatted"
						></InfoListItem>
						<InfoListItem
							iconName="check-circle"
							iconType="solid"
							v-bind:labelText="$t('wallet.transaction_status')"
							v-bind:contentText="transaction.status.label"
						></InfoListItem>
						<InfoListItem
							iconName="receipt"
							v-bind:labelText="$t('wallet.commission')"
							v-bind:contentText="transaction.commission.amount.formatted"
						></InfoListItem>
						<InfoListItem
							iconName="hash-02"
							iconType="solid"
							v-bind:labelText="$t('wallet.transaction_tnxid')"
							v-bind:contentText="transaction.tnx_id"
							v-bind:isCopyable="true"
						></InfoListItem>
						<InfoListItem
							iconName="currency-euro"
							v-bind:labelText="$t('wallet.transaction_currency')"
							v-bind:contentText="transaction.currency.name"
						></InfoListItem>
						<InfoListItem
							iconName="wallet-02"
							v-bind:labelText="$t('wallet.transaction_total')"
							v-bind:contentText="transaction.total.formatted"
						></InfoListItem>
					</InfoList>
				</div>

				<div v-if="transaction.message" class="mt-4 rounded-2xl bg-fill-fv p-4">
					<h5 class="text-cap-s font-semibold text-lab-sc">
						{{ $t('labels.message') }}
					</h5>
					<p class="mt-2 text-par-m leading-6 text-lab-pr2">
						{{ transaction.message }}
					</p>
				</div>

				<div class="mt-4 rounded-2xl bg-fill-fv p-4">
					<p class="text-par-s leading-6 text-lab-sc">
						{{ $t('wallet.transaction_helper_text') }}
					</p>
					<p class="mt-3 text-par-s leading-6 text-lab-sc" v-html="$t('wallet.support_team_email', { email: $embedder('contacts.support_email') })"></p>
				</div>
			</div>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
	import InfoList from '@M/components/general/info/InfoList.vue';
	import InfoListItem from '@M/components/general/info/InfoListItem.vue';

	export default defineComponent({
		emits: ['close'],
		props: {
			transaction: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			return {
				iconName: computed(() => {
					if(props.transaction.type?.key === 'deposit') {
						return 'credit-card-up';
					}

					if(props.transaction.is_incoming) {
						return 'arrow-narrow-down-left';
					}

					return 'arrow-narrow-up-right';
				}),
				sourceName: computed(() => {
					return props.transaction.source?.name || props.transaction.type?.label || props.transaction.amount?.formatted || '';
				}),
				signedAmount: computed(() => {
					return `${props.transaction.is_incoming ? '+' : '-'}${props.transaction.amount.formatted}`;
				})
			};
		},
		components: {
			ActionSheet: ActionSheet,
			ActionSheetGroup: ActionSheetGroup,
			SheetTitle: SheetTitle,
			InfoList: InfoList,
			InfoListItem: InfoListItem
		}
	});
</script>
