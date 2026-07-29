<template>
	<div class="px-4 border-b border-bord-pr">
		<div class="flex items-center leading-4">
			<div v-if="isSuccess" class="size-icon-x-small text-green-900">
				<SvgIcon name="check-circle" type="solid"></SvgIcon>
			</div>
			<div v-else class="size-icon-x-small text-red-900">
				<SvgIcon name="info-circle" type="line"></SvgIcon>
			</div>
			<div class="text-par-s px-2 flex-1 py-4"
				v-bind:class="isSuccess ? 'text-lab-pr' : 'text-red-900'">
				{{ toastData.text }}
			</div>
			<div class="text-lab-sc shrink-0 pl-2 py-1.5">
				<button v-on:click="$emit('dismiss', toastData.id)" class="smoothing size-6 outline-hidden cursor-pointer leading-zero opacity-80 hover:opacity-100">
					<SvgIcon name="x" type="solid" classes="size-full"></SvgIcon>
				</button>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	export default defineComponent({
		props: {
			toastData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			return {
				isSuccess: computed(() => {
					return props.toastData.type === 'success';
				})
			};
		},
		emits: ['dismiss']
	});
</script>