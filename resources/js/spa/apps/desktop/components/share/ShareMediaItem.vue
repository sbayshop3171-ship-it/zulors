<template>
	<a v-bind:href="mediaItem.url + link" target="_blank" class="block px-4 py-2 hover:bg-fill-qt">
		<div class="flex items-center">
			<div class="shrink-0">
				<div class="inline-flex-center size-small-avatar rounded-full bg-fill-pr">
					<SvgIcon v-bind:name="mediaItem.icon" type="social" classes="size-icon-small text-lab-pr"></SvgIcon>
				</div>
			</div>
			<span class="text-lab-pr ml-2 font-medium text-par-n">
				{{  mediaItem.name }}
			</span>
			<span class="shrink-0 ml-auto">
				<SvgIcon name="chevron-right" type="solid" classes="size-4 text-lab-sc"></SvgIcon>
			</span>
		</div>
	</a>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			mediaItem: {
				type: Object,
				required: true
			},
			link: {
				type: String,
				required: true
			}
		}
	});
</script>