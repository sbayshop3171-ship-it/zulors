<template>
	<div class="cursor-pointer">
		<div class="flex flex-col items-center">
			<div class="relative mb-1 size-full aspect-square">
				<div
					class="size-full overflow-hidden rounded-full bg-fill-qt ring-2"
					v-bind:class="[isActive ? 'ring-green-900' : 'ring-fill-pr']"
				>
					<img class="size-full" v-bind:src="logoUrl" alt="Img">
				</div>
				<div
					v-if="isActive"
					class="absolute -right-1 -top-1 z-10 size-6 inline-flex-center rounded-full border-2 border-bg-pr bg-green-900 text-white shadow-sm"
				>
					<SvgIcon name="check-circle" type="solid" classes="size-4"></SvgIcon>
				</div>
			</div>
			<span class="block text-center text-par-s text-lab-pr2 truncate max-w-full">
				{{ providerName }}
			</span>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			logoUrl: {
				type: String,
				required: true
			},
			providerName: {
				type: String,
				required: true
			},
			isActive: {
				type: Boolean,
				default: false
			}
		}
	});
</script>
