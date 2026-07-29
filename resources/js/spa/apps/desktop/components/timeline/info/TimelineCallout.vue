<template>
	<div class="px-4 py-4 flex gap-2.5">
		<div class="shrink-0">
			<div class="size-small-avatar flex-center bg-fill-tr rounded-full">
				<span class="size-icon-small text-brand-900">
					<SvgIcon v-bind:name="iconName" v-bind:type="iconType"></SvgIcon>
				</span>
			</div>
		</div>
		<div class="flex-1">
			<h5 class="text-lab-pr2 font-bold text-title-3">
				{{ title }}
			</h5>
			<p class="text-par-m text-lab-sc max-w-10/12">
				{{ desc }}
			</p>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			iconName: {
				type: String,
				default: 'x',
			},
			iconType: {
				type: String,
				default: 'line',
			},
			title: {
				type: String,
				default: ''
			},
			desc: {
				type: String,
				default: ''
			}
		}
	});
</script>