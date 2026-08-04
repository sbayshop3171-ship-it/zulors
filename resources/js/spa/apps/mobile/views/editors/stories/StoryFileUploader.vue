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
	import { defineComponent, ref, computed, onMounted, onUnmounted } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { getStoryVideoClipCandidate, storyClipUploadOptions } from '@/kernel/services/media/story-video-clip.js';

	import { useStoriesEditorStore } from '@M/store/stories/editor.store.js';

	export default defineComponent({
		setup: function() {
			const router = useRouter();
			const storiesEditorStore = useStoriesEditorStore();
			const stroyMediaFileInput = ref(null);

			const handleMediaUpload = async (file) => {
				if(! file) {
					return;
				}

				try {
					const clipCandidate = await getStoryVideoClipCandidate(file);

					if(clipCandidate?.requiresTrim) {
						storiesEditorStore.setVideoClipCandidate(clipCandidate);
						router.push({ name: 'story_editor' });

						return;
					}

					router.push({ name: 'story_editor' });
					await storiesEditorStore.uploadMedia(file, storyClipUploadOptions(clipCandidate));
				} catch (e) {
					toastError(e.message);

					if(router.currentRoute.value.name === 'story_editor' && ! storiesEditorStore.storyMedia && ! storiesEditorStore.videoClipCandidate) {
						router.replace({
							name: 'home_index'
						});
					}
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
					await handleMediaUpload(event.target.files[0]);
					event.target.value = '';
				},
			};
		}
	});
</script>
