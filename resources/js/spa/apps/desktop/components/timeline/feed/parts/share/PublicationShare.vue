<template>
	<div class="popup-card">
		<PopupPanel>
			<div class="block w-80 leading-normal">
				<PanelHeader v-bind:title="$t('labels.share_post')"></PanelHeader>
				<ShareContent v-bind:mediaOptions="mediaOptions" v-bind:link="postLink"></ShareContent>
			</div>
		</PopupPanel>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import PopupPanel from '@D/components/inter-ui/popups/PopupPanel.vue';
	import PanelHeader from '@D/components/inter-ui/popups/parts/PanelHeader.vue';
	import ShareContent from '@D/components/share/ShareContent.vue';

	export default defineComponent({
		props: {
			postLink: {
				type: String,
				required: true
			}
		},
		setup: function() {
			return {
				mediaOptions: embedder('sharing.stories')
			}
		},
		components: {
			PopupPanel: PopupPanel,
			PanelHeader: PanelHeader,
			ShareContent: ShareContent
		}
	});
</script>