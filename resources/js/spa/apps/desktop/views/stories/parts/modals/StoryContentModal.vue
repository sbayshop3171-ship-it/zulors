<template>
	<ModalBackdrop>
        <ModalContent>
			<ModalHeader v-on:cancel="$emit('hide')" v-bind:title="$t('labels.description')" v-bind:buttonText="$t('labels.close')"></ModalHeader>
			<div class="p-3 max-h-80 overflow-y-auto pb-4">
				<p class="text-par-s text-lab-pr markdown-text" v-html="$mdInline(storyContent)"></p>
			</div>
		</ModalContent>
	</ModalBackdrop>
</template>

<script>
	import { defineComponent, inject, computed } from 'vue';
	import ModalBackdrop from '@D/views/stories/parts/modals/parts/ModalBackdrop.vue';
    import ModalContent from '@D/views/stories/parts/modals/parts/ModalContent.vue';
    import ModalHeader from '@D/views/stories/parts/modals/parts/ModalHeader.vue';

	export default defineComponent({
		emits: ['hide'],
		setup: function() {
			const playerState = inject('playerState');
			
			return {
				storyContent: computed(() => {
					return playerState.frameData.content;
				})
			};
		},
		components: {
			ModalBackdrop: ModalBackdrop,
			ModalContent: ModalContent,
			ModalHeader: ModalHeader
		}
	});
</script>