<template>
	<div v-if="uploadProgress">
		<div class="flex">
			<div class="flex-1 bg-fill-tr h-1">
				<div class="bg-green-900 min-w-10 h-full" v-bind:style="{ width: uploadProgress + '%' }"></div>
			</div>
		</div>
	</div>
	<div class="hidden">
		<input v-on:change="handleMediaSelect" type="file" accept="image/*, video/*" ref="stroyMediaFileInput">
	</div>
</template>

<script>
	import { defineComponent, ref, computed, reactive, onMounted, onUnmounted } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import { useStoriesEditorStore } from '@M/store/stories/editor.store.js';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isUploading: false
			});

			const router = useRouter();
			const storiesEditorStore = useStoriesEditorStore();
			const stroyMediaFileInput = ref(null);

			const handleMediaUpload = async (file) => {
				try {
					state.isUploading = true;
					await storiesEditorStore.uploadMedia(file);

					router.push({ name: 'story_editor' });
					state.isUploading = false;
				} catch (e) {
					state.isUploading = false;

					toastError(e.message);
				}
			}

			const selectStoryMedia = () => {
				stroyMediaFileInput.value.click();
			}

			onMounted(() => {
				colibriEventBus.on('story:create', selectStoryMedia);
			});

			onUnmounted(() => {
				colibriEventBus.off('story:create', selectStoryMedia);
			});

			return {
				stroyMediaFileInput: stroyMediaFileInput,
				
				uploadProgress: computed(() => {
					return storiesEditorStore.uploadProgress;
				}),
				handleMediaUpload: handleMediaUpload,
				handleMediaSelect: async (event) => {
					handleMediaUpload(event.target.files[0]);
				},
			};
		}
	});
</script>