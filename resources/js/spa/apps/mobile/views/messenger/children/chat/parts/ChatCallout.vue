<template>
	<div class="flex gap-2.5 items-center px-4 py-3">
		<div v-bind:class="variantClassMaps[variant]" class="shrink-0 size-normal-avatar inline-flex-center rounded-full">
			<span class="size-icon-small">
				<SvgIcon v-bind:name="iconName" v-bind:type="iconType"></SvgIcon>
			</span>
		</div>
		<div class="flex-1">
			<h6 class="font-semibold text-par-m whitespace-nowrap truncate text-lab-pr2">
				{{  title }}
			</h6>
			<p class="text-lab-sc text-par-n">
				{{  description }}
			</p>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			variant: {
				type: String,
				default: 'info'
			},
			iconName: {
				type: String,
				default: 'info-circle'
			},
			iconType: {
				type: String,
				default: 'line'
			},
			title: {
				type: String,
				default: ''
			},
			description: {
				type: String,
				default: ''
			}
		},
		setup: function(props) {
			const variantClassMaps = {
				info: 'bg-fill-qt text-lab-pr',
				danger: 'bg-red-900 text-white'
			};

			return {
				variantClassMaps: variantClassMaps
			}
		}
	});
</script>