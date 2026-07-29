<template>
	<Backdrop>
		<div class="ml-navbar py-top-offset">
			<StoriesEditor></StoriesEditor>
		</div>
	</Backdrop>
</template>

<script>
	import { defineComponent } from 'vue';
	import ZulorsAttribution from '@D/components/attributions/ZulorsAttribution.vue';
    import Backdrop from '@D/components/general/modals/Backdrop.vue';
	import StoriesEditor from '@D/components/stories/editor/StoriesEditor.vue';

	export default defineComponent({
		components: {
			StoriesEditor: StoriesEditor,
			ZulorsAttribution: ZulorsAttribution,
			Backdrop: Backdrop
		}
	});
</script>