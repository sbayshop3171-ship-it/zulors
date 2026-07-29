<template>
	<button class="inline-flex items-center text-lab-sc hover:text-brand-900">
		<span class="size-icon-x-small">
			<SvgIcon name="translate-01" type="line" classes="size-full"></SvgIcon>
		</span>
		<span class="text-cap-s ml-0.5 leading-none">
			{{ buttonText }}
		</span>
	</button>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			buttonText: {
                type: String,
                default: 'Label'
            },
		}
	});
</script>