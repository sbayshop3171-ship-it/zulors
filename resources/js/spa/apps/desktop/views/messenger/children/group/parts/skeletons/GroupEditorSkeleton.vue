<template>
	<div class="w-full h-full">
		<div class="flex justify-center py-4">
			<div class="size-large-avatar skeleton-circle"></div>
		</div>
		<div class="px-4 mb-8">
			<div class="skeleton w-full h-10"></div>
		</div>
		<div class="px-4 mb-8">
			<div class="skeleton h-4 w-4/12 mb-2"></div>
			<div class="skeleton h-4 w-8/12"></div>
		</div>
		<div>
			<ParticipantSkeleton v-for="i in 3" v-bind:key="i"></ParticipantSkeleton>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import ParticipantSkeleton from '@D/views/messenger/children/group/parts/skeletons/ParticipantSkeleton.vue';

	export default defineComponent({
		components: {
			ParticipantSkeleton: ParticipantSkeleton
		}
	});
</script>