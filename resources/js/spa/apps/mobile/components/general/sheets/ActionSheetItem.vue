<template>
	<button v-bind:disabled="disabled || loading" class="w-full disabled:cursor-not-allowed disabled:opacity-70 flex items-center overflow-hidden px-5 py-2.5 leading-none relative after:absolute after:bottom-0 after:left-5 after:w-full after:h-px after:bg-bord-sc"
		v-bind:class="[notLast ? '' : 'last:after:hidden', itemColor, (loading ? 'cursor-wait opacity-70' : 'cursor-pointer')]">
		<span class="py-2 block text-par-m text-left font-semibold flex-1 truncate mr-2">
			{{ textLabel }}
		</span>
		<div v-if="loading" class="shrink-0 pr-3 leading-zero ml-auto">
			<div class="colibri-primary-animation"></div>
		</div>
		<span v-else class="shrink-0 size-icon-normal ml-auto">
			<SvgIcon v-bind:type="iconType" v-bind:name="iconName" classes="size-full"></SvgIcon>
		</span>
	</button>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			iconType: {
				type: String,
				default: 'line'
			},
			iconName: {
				type: String,
				default: ''
			},
			textLabel: {
				type: String,
				default: ''
			},
			itemColor: {
				type: String,
				default: 'text-lab-pr2'
			},
			loading: {
				type: Boolean,
				default: false
			},
			notLast: {
				type: Boolean,
				default: false
			},
			disabled: {
				type: Boolean,
				default: false
			}
		}
	});
</script>