<template>
	<div class="flex max-w-content justify-center">
		<div class="popup-background-pr backdrop-blur-md border pl-5 pr-3 rounded-xl text-bg-pr shadow-2xl" v-bind:class="[toastStyle]">
			<div class="flex items-center leading-4">
				<div class="text-par-m pr-4 text-lab-pr flex-1 py-4">
					{{ toastData.text }}
				</div>
				<div class="text-lab-pr shrink-0">
					<PrimaryIconButton v-on:click="$emit('dismiss', toastData.id)"></PrimaryIconButton>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		props: {
			toastData: {
				type: Object,
				required: true
			}
		},
		emits: ['dismiss'],
		setup: function(props) {
			return {
				toastStyle: computed(() => {
					if(props.toastData.type === 'success') {
						return 'border-green-900';
					}
					
					else {
						return 'border-red-900';
					}
				})
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>