<template>
	<div class="mb-2 flex cursor-pointer items-center gap-3 rounded-2xl bg-fill-fv px-4 py-4 last:mb-0 hover:bg-fill-tr sm:mb-0 sm:gap-4 sm:rounded-none sm:border-b sm:border-bord-pr sm:bg-transparent sm:px-0 sm:py-3 sm:last:border-none sm:hover:bg-fill-fv">
		<div class="size-11 shrink-0 rounded-2xl bg-fill-qt inline-flex-center sm:size-normal-avatar sm:rounded-full">
			<SvgIcon v-bind:name="iconName" classes="size-icon-normal text-brand-900"></SvgIcon>
		</div>
		<div class="flex min-w-0 flex-1 justify-between gap-3">
			<div class="min-w-0 flex-1">
				<span class="block truncate text-par-m font-semibold text-lab-pr2 sm:text-par-l sm:font-medium">
					{{ transactionData.source.name }}
				</span>
				<span class="block text-par-s text-brand-900">
					{{ transactionData.type.label }}
				</span>
				<span class="block truncate text-par-s text-lab-sc" v-if="hasCommission">
					{{ $t('wallet.commission') }} {{ transactionData.commission.amount.formatted }}
				</span>
			</div>
			<div class="shrink-0 text-right">
				<span class="block text-par-m font-medium" v-bind:class="[transactionData.is_incoming ? 'text-mint' : 'text-red-900']">
					{{ transactionData.is_incoming ? '+' : '-' }}{{ transactionData.amount.formatted }}
				</span>
				<span class="block text-par-s text-lab-sc">
					{{ transactionData.date.time_ago }}
				</span>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	export default defineComponent({
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
					
					else {
						if(props.transactionData.is_incoming) {
							return 'arrow-narrow-down-left';
						}

						return 'arrow-narrow-up-right';
					}
				}),
				hasCommission: computed(() => {
					return props.transactionData.commission.rate > 0;
				})
			}
		}
	});
</script>
