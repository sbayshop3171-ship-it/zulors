<template>
	<div class="popup-card rich-menu-card">
		<PopupPanel>
			<div class="bg-bg-pr max-h-80 overflow-y-auto">
				<slot></slot>
			</div>
		</PopupPanel>
	</div>
</template>

<script>
import PopupPanel from '@D/components/inter-ui/popups/PopupPanel.vue';

	export default {
		components: {
			PopupPanel: PopupPanel,
		},
	};
</script>
