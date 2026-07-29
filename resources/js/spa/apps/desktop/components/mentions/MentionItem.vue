<template>
	<div class="block px-3 py-2.5 cursor-pointer border-b border-bord-pr last:border-none hover:bg-fill-qt">
		<div class="flex items-center justify-between cursor-pointer">
			<div class="shrink-0 size-6 rounded-full overflow-hidden">
				<img class="w-full" v-bind:src="userData.avatar_url" alt="Avatar">
			</div>
			<div class="flex-1 text-lab-pr text-par-s pl-2 truncate leading-normal">
				<span class="font-medium">
					{{ userData.name }}
				</span>
				<span class="text-lab-sc ml-1">
					@{{ userData.username }}
				</span>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			userData: {
				type: Object,
				required: true
			}
		}
	});
</script>