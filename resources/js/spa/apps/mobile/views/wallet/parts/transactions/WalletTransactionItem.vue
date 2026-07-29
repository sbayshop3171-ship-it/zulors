<template>
	<button
		type="button"
		v-on:click="$emit('select', transaction)"
		class="w-full rounded-2xl bg-fill-fv px-4 py-4 text-left transition-colors active:bg-fill-sc"
	>
		<div class="flex items-start gap-3">
			<div
				class="size-11 shrink-0 inline-flex-center rounded-2xl"
				v-bind:class="[transaction.is_incoming ? 'bg-green-500/10 text-green-600' : 'bg-brand-900/10 text-brand-900']"
			>
				<SvgIcon
					v-bind:name="transactionIcon"
					type="solid"
					classes="size-5"
				></SvgIcon>
			</div>

			<div class="min-w-0 flex-1">
				<div class="flex items-start gap-2">
					<h5 class="min-w-0 flex-1 truncate text-par-m font-semibold text-lab-pr2">
						{{ transactionTitle }}
					</h5>
					<span
						class="shrink-0 text-par-m font-semibold"
						v-bind:class="[transaction.is_incoming ? 'text-green-600' : 'text-lab-pr2']"
					>
						{{ signedAmount }}
					</span>
				</div>

				<div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-par-s text-lab-sc">
					<span class="truncate">
						{{ transactionSubtitle }}
					</span>
					<span class="shrink-0">•</span>
					<span class="shrink-0">
						{{ transaction.date.time_ago }}
					</span>
				</div>

				<div
					v-if="transaction.message"
					class="mt-1 truncate text-par-s text-lab-sc"
				>
					{{ transaction.message }}
				</div>
			</div>

			<SvgIcon
				name="arrow-narrow-right"
				type="solid"
				classes="size-5 shrink-0 text-lab-sc"
			></SvgIcon>
		</div>
	</button>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	export default defineComponent({
		emits: ['select'],
		props: {
			transaction: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			return {
				transactionIcon: computed(() => {
					if(props.transaction.type?.key === 'deposit') {
						return 'credit-card-up';
					}

					if(props.transaction.is_incoming) {
						return 'arrow-narrow-down-left';
					}

					return 'arrow-narrow-up-right';
				}),
				transactionTitle: computed(() => {
					return props.transaction.source?.name || props.transaction.type?.label || props.transaction.amount.formatted;
				}),
				transactionSubtitle: computed(() => {
					return props.transaction.message || props.transaction.type?.label || props.transaction.status?.label || '';
				}),
				signedAmount: computed(() => {
					const prefix = props.transaction.is_incoming ? '+' : '-';

					return `${prefix}${props.transaction.amount.formatted}`;
				})
			};
		}
	});
</script>
