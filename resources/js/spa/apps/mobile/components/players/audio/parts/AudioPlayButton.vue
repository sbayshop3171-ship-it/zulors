<template>
	<button type="button" v-bind="$attrs" class="size-small-avatar bg-brand-900 rounded-full flex items-center justify-center"
		v-bind:class="[disabled ? 'cursor-wait opacity-50' : 'cursor-pointer']">
		<span class="size-4 text-white" v-bind:class="[iconName == 'play' ? 'translate-x-px' : '']">
			<SvgIcon v-bind:name="iconName"></SvgIcon>
		</span>
	</button>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			disabled: {
				type: Boolean,
				default: false
			},
			iconName: {
				type: String,
				default: 'play'
			}
		}
	})
</script>
