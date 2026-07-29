<template>
	<div v-bind:class="{ 'pb-safe-bottom': $isStandalone() }">
		<div class="shrink-0">
			<Toolbar v-bind:title="$t('labels.new_story')" v-on:close="leaveEditor"></Toolbar>
		</div>
		
		<form v-on:submit.prevent="submitForm">
			<div class="pb-4 flex justify-center">
				<div class="w-6/12">
					<template v-if="storyMedia">
						<div class="bg-black h-full rounded-md overflow-hidden">
							<div class="h-full flex items-center">
								<img class="w-full object-cover" v-bind:src="storyMedia.source_url" alt="Image">
								<template v-if="isVideo">
									<div class="absolute bottom-4 right-4">
										<VideoDurationTime v-bind:videoDuration="storyMedia.duration"></VideoDurationTime>
									</div>
								</template>
							</div>
						</div>
					</template>
				</div>
			</div>
			<Border height="h-3" opacity="opacity-70"></Border>
			<div class="block">
				<div class="block">
					<textarea
						v-on:input="textInputHandler"
						v-model="storyData.content" 
						ref="storyTextInputField" 
						class="resize-none bg-transparent block min-h-24 w-full max-h-60 overflow-y-auto outline-hidden px-4 py-4 placeholder:text-par-m text-lab-pr text-par-m placeholder:text-lab-sc"
					v-bind:placeholder="$t('story.editor.add_caption')"></textarea>
				</div>
				<div class="flex items-center px-4 py-2">
					<div class="shrink-0">
						<span class="text-lab-sc text-cap-l">{{ storyData.content.length }}/{{ 1200 }}</span>
					</div>
				</div>
			</div>
			<Border></Border>
			<StoryPrivacyInfo></StoryPrivacyInfo>
			<div class="p-4">
				<PrimaryTextButton v-bind:buttonFluid="true" v-bind:disabled="! isFormValid" v-bind:loading="state.isSubmitting" v-bind:buttonText="$t('story.editor.publish_story')" type="submit"></PrimaryTextButton>
			</div>
		</form>
	</div>
</template>

<script>
	import { defineComponent, reactive, ref, computed, onMounted, defineAsyncComponent } from 'vue';
	import { useRouter } from 'vue-router';

	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { useStoriesEditorStore } from '@M/store/stories/editor.store.js';

	import PrimaryTextButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
	import StoryPrivacyInfo from '@M/views/editors/stories/parts/StoryPrivacyInfo.vue';

	import Toolbar from '@M/components/layout/Toolbar.vue';

	export default defineComponent({
		setup: function() {
			const storiesEditorStore = useStoriesEditorStore();
			const storyTextInputField = ref(null);
			const router = useRouter();
			const state = reactive({
				isSubmitting: false
			});

			const { autoResize } = useInputHandlers();
			const storyData = ref(storiesEditorStore.storyData);

			const deleteStoryMedia = () => {
				try {
					storiesEditorStore.deleteMedia();
				} catch (e) {
					toastError(e.message);
				}
			}

			onMounted(() => {
				if(! storiesEditorStore.storyMedia) {
					router.push({
						name: 'home_index'
					});
				}
			});

			return {
				state: state,
				storyMedia: computed(() => {
					return storiesEditorStore.storyMedia;
				}),
				isVideo: computed(() => {
					return storiesEditorStore.storyMedia.type === 'video';
				}),
				
				isFormValid: computed(() => {
					return storiesEditorStore.isFormValid;
				}),
				storyData: storyData,
				storyTextInputField: storyTextInputField,
				submitForm: async () => {
					try {
						state.isSubmitting = true;
						await storiesEditorStore.publishStory();
						state.isSubmitting = false;

						toastSuccess(__t('toast.story.story_published'));

						storiesEditorStore.resetEditor();

						router.push({
							name: 'home_index'
						});
					} catch (e) {
						state.isSubmitting = false;
						toastError(e.message);
					}
				},
				
				textInputHandler: () => {
					autoResize(storyTextInputField.value);
				},
				leaveEditor: () => {
					deleteStoryMedia();

					router.push({
						name: 'home_index'
					});
				}
			};
		},
		components: {
			PrimaryTextButton: PrimaryTextButton,
			StoryPrivacyInfo: StoryPrivacyInfo,
			VideoDurationTime: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/media/video/VideoDurationTime.vue');
            }),
			Toolbar: Toolbar
		}
	});
</script>