<template>
	<div class="block relative mb-4">
		<div class="h-32 skeleton-square mb-4"></div>
		<div class="relative z-10 border-4 border-bg-pr skeleton-circle rounded-full backdrop-blur-2xl size-large-avatar -mt-14 ml-4">
		</div>
		<div class="px-4 mt-2">
			<div class="w-10/12 h-4 skeleton mb-2"></div>
			<div class="h-2 skeleton w-6/12 mb-2"></div>
			<div class="h-2 skeleton w-4/12 mb-4"></div>

			<div class="h-7 skeleton"></div>
		</div>
	</div>
	<Border></Border>
	<TimelinePublicationSkeleton v-for="i in 3"></TimelinePublicationSkeleton>
</template>

<script>
	import { defineComponent } from 'vue';
	import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';

	export default defineComponent({
		setup: function() {
			return {};
		},
		components: {
			TimelinePublicationSkeleton: TimelinePublicationSkeleton
		}
	});
</script>