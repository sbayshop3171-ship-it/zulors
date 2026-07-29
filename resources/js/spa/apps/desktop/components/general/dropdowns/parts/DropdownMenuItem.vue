<template>
	<div class="w-full flex first:pt-2 last:pb-2 items-center overflow-hidden px-5 py-1.5 transition-colors duration-200 hover:bg-fill-qt"
		v-bind:class="[itemColor, (loading ? 'cursor-wait opacity-70' : 'cursor-pointer'), (disabled ? 'cursor-not-allowed opacity-70' : '')]">
		<span class="py-2 block text-par-m font-medium flex-1 truncate mr-2">
			{{ textLabel }}
		</span>
		<div v-if="loading" class="shrink-0 pr-3 leading-zero">
			<div class="colibri-primary-animation"></div>
		</div>
		<span v-else class="shrink-0 size-5">
			<SvgIcon v-bind:type="iconType" v-bind:name="iconName" classes="size-full"></SvgIcon>
		</span>
	</div>
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
			disabled: {
				type: Boolean,
				default: false
			}
		}
	});
</script>