<template>
	<ActionSheet v-on:close="$emit('close')">
		<div class="px-4 mb-4 text-center">
			<SheetTitle v-bind:title="$t('labels.share_post')"></SheetTitle>
		</div>
		<ShareContent v-on:close="$emit('close')" v-bind:mediaOptions="mediaOptions" v-bind:link="postLink"></ShareContent>
	</ActionSheet>
</template>

<script>
	import { defineAsyncComponent } from 'vue';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ShareContent from '@M/components/share/ShareContent.vue';
	import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';

	export default {
		props: {
			postLink: {
				type: String,
				required: true
			}
		},
		setup: function(props) {
			return {
				mediaOptions: embedder('sharing.stories')
			}
		},
		components: {
			ActionSheet: ActionSheet,
			ShareContent: ShareContent,
			SheetTitle: SheetTitle
		}
	}
</script>