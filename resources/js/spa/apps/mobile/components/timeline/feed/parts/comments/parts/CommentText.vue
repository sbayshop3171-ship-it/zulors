<template>
	<div class="block">
		<div ref="contentElement" class="text-par-m text-lab-pr break-words" v-bind:class="{ 'line-clamp-5': !expanded }" v-html="$mdInline(commentContent)"></div>
		<button v-if="hasOverflow" class="text-par-s text-lab-sc font-semibold mt-1" v-on:click="expanded = !expanded">{{ expanded ? 'See less' : 'See more' }}</button>
	</div>
</template>

<script>
	import { defineComponent, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';

	export default defineComponent({
		props: {
			commentContent: { type: String, required: true }
		},
		setup(props) {
			const expanded = ref(false);
			const hasOverflow = ref(false);
			const contentElement = ref(null);
			const measure = () => nextTick(() => {
				const element = contentElement.value;
				if (element && !expanded.value) hasOverflow.value = element.scrollHeight > element.clientHeight + 2;
			});
			const onResize = () => measure();
			watch(() => props.commentContent, () => { expanded.value = false; measure(); });
			watch(expanded, value => { if (!value) measure(); });
			onMounted(() => { measure(); window.addEventListener('resize', onResize); });
			onBeforeUnmount(() => window.removeEventListener('resize', onResize));
			return { expanded, hasOverflow, contentElement };
		}
	});
</script>
