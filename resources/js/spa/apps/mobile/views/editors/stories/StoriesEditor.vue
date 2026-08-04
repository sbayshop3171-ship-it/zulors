<template>
	<div v-bind:class="{ 'pb-safe-bottom': $isStandalone() }">
		<div class="shrink-0">
			<Toolbar v-bind:title="$t('labels.new_story')" v-on:close="leaveEditor"></Toolbar>
		</div>
		
		<form v-on:submit.prevent="submitForm">
			<div class="pb-4 flex justify-center">
				<div class="w-8/12">
					<template v-if="videoClipCandidate">
						<div class="bg-black rounded-md overflow-hidden">
							<video
								ref="videoClipPreview"
								v-bind:src="videoClipCandidate.objectUrl"
								v-on:loadedmetadata="syncClipPreview"
								class="block w-full aspect-[9/16] object-contain"
								muted
								playsinline
								controls
							></video>
							<div class="bg-black px-3 py-3">
								<div class="flex items-center text-white text-cap-l">
									<span>{{ formatClipTime(videoClipCandidate.clipStartSeconds) }} - {{ formatClipTime(videoClipCandidate.clipStartSeconds + videoClipCandidate.clipDurationSeconds) }}</span>
									<span class="ml-auto">{{ formatClipTime(videoClipCandidate.durationSeconds) }}</span>
								</div>
								<input
									v-model.number="videoClipCandidate.clipStartSeconds"
									v-on:input="syncClipPreview"
									type="range"
									min="0"
									v-bind:max="videoClipCandidate.maxStartSeconds"
									step="1"
									class="block w-full mt-3 accent-brand-900"
								>
								<div class="flex items-center justify-end gap-4 mt-3">
									<button v-on:click="cancelVideoClip" type="button" class="text-par-s text-white/80">
										{{ $t('labels.cancel') }}
									</button>
									<button v-on:click="confirmVideoClip" type="button" class="h-9 px-4 rounded-full bg-brand-900 text-white text-par-s font-medium">
										Next
									</button>
								</div>
							</div>
						</div>
					</template>
					<template v-else-if="state.isUploading">
						<div class="border border-dashed border-edge-pr rounded-md p-4">
							<div class="bg-fill-tr h-1 rounded-full overflow-hidden">
								<div class="bg-green-900 min-w-10 h-full" v-bind:style="{ width: uploadProgress + '%' }"></div>
							</div>
							<p class="text-center text-par-s text-brand-900 mt-3">
								{{ $t('labels.uploading') }} {{ uploadProgress }}%
							</p>
						</div>
					</template>
					<template v-else-if="storyMedia">
						<div class="bg-black h-full rounded-md overflow-hidden">
							<div class="h-full flex items-center relative">
								<video
									v-if="isVideo"
									v-bind:src="storyVideoPreviewUrl"
									v-bind:poster="storyVideoPosterUrl"
									class="block w-full aspect-[9/16] object-contain bg-black"
									webkit-playsinline
									playsinline
									preload="metadata"
									controls
								></video>
								<img v-else class="w-full object-cover" v-bind:src="storyMedia.source_url" alt="Image">
								<div v-if="isVideo && storyVideoDuration" class="pointer-events-none absolute bottom-4 right-4">
									<VideoDurationTime v-bind:videoDuration="storyVideoDuration"></VideoDurationTime>
								</div>
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
	import { defineComponent, reactive, ref, computed, onMounted, onUnmounted, defineAsyncComponent, nextTick } from 'vue';
	import { useRouter } from 'vue-router';

	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { useStoriesEditorStore } from '@M/store/stories/editor.store.js';
	import { storyClipUploadOptions, formatStoryClipTime } from '@/kernel/services/media/story-video-clip.js';

	import PrimaryTextButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
	import StoryPrivacyInfo from '@M/views/editors/stories/parts/StoryPrivacyInfo.vue';

	import Toolbar from '@M/components/layout/Toolbar.vue';

	export default defineComponent({
		setup: function() {
			const storiesEditorStore = useStoriesEditorStore();
			const storyTextInputField = ref(null);
			const videoClipPreview = ref(null);
			const router = useRouter();
			const state = reactive({
				isSubmitting: false,
				isUploading: false
			});

			const { autoResize } = useInputHandlers();
			const storyData = ref(storiesEditorStore.storyData);

			const deleteStoryMedia = () => {
				try {
					if(storiesEditorStore.storyMedia) {
						storiesEditorStore.deleteMedia();
					}

					storiesEditorStore.clearVideoClipCandidate();
				} catch (e) {
					toastError(e.message);
				}
			}

			const syncClipPreview = () => {
				if(videoClipPreview.value && storiesEditorStore.videoClipCandidate) {
					videoClipPreview.value.currentTime = Number(storiesEditorStore.videoClipCandidate.clipStartSeconds || 0);
				}
			};

			onMounted(() => {
				if(! storiesEditorStore.storyMedia && ! storiesEditorStore.videoClipCandidate) {
					router.push({
						name: 'home_index'
					});
				}

				nextTick(syncClipPreview);
			});

			onUnmounted(() => {
				if(! storiesEditorStore.storyMedia) {
					storiesEditorStore.clearVideoClipCandidate();
				}
			});

			return {
				state: state,
				videoClipPreview: videoClipPreview,
				storyMedia: computed(() => {
					return storiesEditorStore.storyMedia;
				}),
				videoClipCandidate: computed(() => {
					return storiesEditorStore.videoClipCandidate;
				}),
				isVideo: computed(() => {
					return storiesEditorStore.storyMedia?.type === 'video';
				}),
				storyVideoPreviewUrl: computed(() => {
					return storiesEditorStore.storyMedia?.preview_url || storiesEditorStore.storyMedia?.source_url || '';
				}),
				storyVideoPosterUrl: computed(() => {
					return storiesEditorStore.storyMedia?.thumbnail_url || '';
				}),
				storyVideoDuration: computed(() => {
					return storiesEditorStore.storyMedia?.duration || storiesEditorStore.storyMedia?.metadata?.duration || null;
				}),
				uploadProgress: computed(() => {
					return storiesEditorStore.uploadProgress;
				}),
				
				isFormValid: computed(() => {
					return storiesEditorStore.isFormValid;
				}),
				storyData: storyData,
				storyTextInputField: storyTextInputField,
				formatClipTime: formatStoryClipTime,
				syncClipPreview: syncClipPreview,
				cancelVideoClip: () => {
					storiesEditorStore.clearVideoClipCandidate();

					router.push({
						name: 'home_index'
					});
				},
				confirmVideoClip: async () => {
					const clipCandidate = storiesEditorStore.videoClipCandidate;

					if(! clipCandidate) {
						return;
					}

					try {
						const mediaFile = clipCandidate.file;
						const uploadOptions = storyClipUploadOptions(clipCandidate);

						storiesEditorStore.clearVideoClipCandidate();
						state.isUploading = true;
						await storiesEditorStore.uploadMedia(mediaFile, uploadOptions);
						state.isUploading = false;
					}
					catch (e) {
						state.isUploading = false;
						toastError(e.message);
					}
				},
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
