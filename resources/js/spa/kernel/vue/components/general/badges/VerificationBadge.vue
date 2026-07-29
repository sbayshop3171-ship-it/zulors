<template>
	<span v-bind:class="iconSize" class="inline-block text-brand-900 align-middle">
		<SvgIcon name="check-verified-02"></SvgIcon>
	</span>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			size: {
				type: String,
				default: 'sm'
			}
		},
		setup: function(props) {
			const sizeMap = {
				xs: 'size-3',
				sm: 'size-icon-x-small',
				md: 'size-icon-small',
				lg: 'size-icon-normal',
				xl: 'size-icon-medium'
			};
			
			return {
				iconSize: sizeMap[props.size]
			};
		}
	});
</script>