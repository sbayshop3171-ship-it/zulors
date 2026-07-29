<template>
	<div class="w-full flex justify-center">
		<button
			v-bind:title="title"
			class="inline-flex-center cursor-pointer items-center leading-none rounded-xl size-12 hover:opacity-100"
		v-bind:class="[(hasBg ? 'bg-fill-qt' : 'bg-none hover:bg-fill-fv'), buttonColor]">
			<span class="size-icon-normal">
				<SvgIcon v-bind:name="iconName" v-bind:type="iconType" v-bind:classes="iconClasses"></SvgIcon>
			</span>
		</button>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			title: {
				type: String,
				default: ''
			},
			iconName: {
				type: String,
				default: 'x'
			},
			iconClasses: {
				type: String,
				default: ''
			},
			iconType: {
				type: String,
				default: 'solid'
			},
			buttonColor: {
                type: String,
                default: 'text-lab-pr' 
            },
			hasBg: {
				type: Boolean,
				default: true
			}
		}
	});
</script>