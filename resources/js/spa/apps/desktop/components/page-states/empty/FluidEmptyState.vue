<template>
	<div class="block py-40">
		<div class="block">
			<div v-if="hasIcon" class="flex justify-center mb-2">
				<span class="size-8 text-lab-pr2">
					<SvgIcon v-bind:name="iconName" v-bind:type="iconType"></SvgIcon>
				</span>
			</div>
			<h4 class="text-lab-pr2 font-medium text-par-n text-center" v-html="text"></h4>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			hasIcon: {
				type: Boolean,
				default: true
			},
			iconName: {
				type: String,
				default: 'search-lg'
			},
			iconType: {
				type: String,
				default: 'solid'
			},
			text: {
				type: String,
				default: ''
			}
		}
	});
</script>