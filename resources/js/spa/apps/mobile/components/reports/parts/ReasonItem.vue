<template>
	<div class="block px-6 smoothing hover:bg-fill-qt py-4 cursor-pointer border-b border-b-bord-pr last:border-b-0">
		<div class="flex items-center gap-x-4">
			<div class="flex-1">
				<h4 class="text-par-m font-semibold text-lab-pr">
					{{ reasonData.title }}
				</h4>
				<p class="text-par-n text-lab-sc">
					{{ reasonData.description }}
				</p>
			</div>
			<div class="shrink-0 size-icon-small">
				<SvgIcon v-if="isSelected" name="check-circle" classes="text-green-900"></SvgIcon>
				<SvgIcon v-else name="circle" type="line" classes="text-lab-sc"></SvgIcon>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			reasonData: {
				type: Object,
				required: true
			},
			isSelected: {
				type: Boolean,
				required: false,
				default: false
			}
		}
	});
</script>