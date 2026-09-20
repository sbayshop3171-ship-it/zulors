<template>
	<StoryCreativeEditor
		v-if="useCreativeEditor"
		v-on:fallback="activateLegacyFallback"
	></StoryCreativeEditor>
	<StoriesEditor v-else></StoriesEditor>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
	import { useStoriesEditorStore } from '@M/store/stories/editor.store.js';

	import StoriesEditor from '@M/views/editors/stories/StoriesEditor.vue';
	import StoryCreativeEditor from '@M/views/editors/stories/StoryCreativeEditor.vue';

	export default defineComponent({
		setup: function() {
			const storiesEditorStore = useStoriesEditorStore();
			const legacyFallbackActive = ref(false);

			const activateLegacyFallback = async (error) => {
				if(! storiesEditorStore.legacyEditorFallbackEnabled) {
					toastError(error?.message || 'Creative editor is unavailable.');
					return;
				}

				try {
					legacyFallbackActive.value = true;
					toastError('Creative editor is unavailable. Opening the standard story editor.');
					await storiesEditorStore.uploadCreativeDraftWithLegacy();
				} catch (fallbackError) {
					toastError(fallbackError.message);
					storiesEditorStore.resetEditor();
				}
			};

			return {
				useCreativeEditor: computed(() => {
					return ! legacyFallbackActive.value && storiesEditorStore.creativeEditorEnabled && storiesEditorStore.hasCreativeDraft;
				}),
				activateLegacyFallback: activateLegacyFallback
			};
		},
		components: {
			StoriesEditor: StoriesEditor,
			StoryCreativeEditor: StoryCreativeEditor
		}
	});
</script>
