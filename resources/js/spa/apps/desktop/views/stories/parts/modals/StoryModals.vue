<template>
	<StoryShareModal v-if="state.isShareModalOpen" v-on:cancel="handleStoryShareCancel"></StoryShareModal>
	<StoryContentModal v-if="state.isContentModalOpen" v-on:hide="handleHideContent"></StoryContentModal>
	<StoryViewsModal v-if="state.isViewsModalOpen" v-on:hide="handleHideViews"></StoryViewsModal>
</template>

<script>
	import { defineComponent, onMounted, reactive, onUnmounted } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import StoryViewsModal from '@D/views/stories/parts/modals/StoryViewsModal.vue';
	import StoryShareModal from '@D/views/stories/parts/modals/StoryShareModal.vue';
	import StoryContentModal from '@D/views/stories/parts/modals/StoryContentModal.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isShareModalOpen: false,
				isContentModalOpen: false,
				isViewsModalOpen: false
			});
			
            const handleStoryShare = () => {
				state.isShareModalOpen = true;
				colibriEventBus.emit('story:pause');
			}
			
            const handleShowContent = () => {
				state.isContentModalOpen = true;
				colibriEventBus.emit('story:pause');
			}

            const handleShowViews = () => {
				state.isViewsModalOpen = true;
				colibriEventBus.emit('story:pause');
			}

			onMounted(() => {
				colibriEventBus.on('story:share', handleStoryShare);
				colibriEventBus.on('story:show-content', handleShowContent);
				colibriEventBus.on('story:show-views', handleShowViews);
			});

			onUnmounted(() => {
				colibriEventBus.off('story:share', handleStoryShare);
				colibriEventBus.off('story:show-content', handleShowContent);
				colibriEventBus.off('story:show-views', handleShowViews);
			});

			return {
				state: state,
				handleStoryShareCancel: () => {
					colibriEventBus.emit('story:play');
					state.isShareModalOpen = false;
				},
				handleHideContent: () => {
					state.isContentModalOpen = false;
					colibriEventBus.emit('story:play');
				},
				handleHideViews: () => {
					state.isViewsModalOpen = false;
					colibriEventBus.emit('story:play');
				}
			};
		},
		components: {
            StoryViewsModal: StoryViewsModal,
            StoryShareModal: StoryShareModal,
			StoryContentModal: StoryContentModal
		}
	});
</script>