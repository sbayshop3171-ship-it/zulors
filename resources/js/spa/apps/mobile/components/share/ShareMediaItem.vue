<template>
	<div class="flex flex-col items-center gap-2 overflow-hidden">
		<div class="shrink-0">
			<div class="inline-flex-center size-16 rounded-full bg-fill-tr">
				<SvgIcon v-bind:name="iconName" v-bind:type="iconType" classes="size-6 text-lab-pr"></SvgIcon>
			</div>
		</div>
		<span class="text-lab-sc text-center font-normal text-par-s truncate w-full">
			{{ buttonText }}
		</span>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			iconName: {
				type: String,
				required: true
			},
			iconType: {
				type: String,
				default: 'social'
			},
			buttonText: {
				type: String,
				required: true
			}
		}
	});
</script>