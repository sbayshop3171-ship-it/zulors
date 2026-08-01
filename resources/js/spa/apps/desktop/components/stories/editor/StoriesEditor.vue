<template>
	<div class="w-sided-content">
		<form v-on:submit.prevent="submitForm">
			<div class="flex items-stretch overflow-hidden popup-background-tr border border-bord-pr rounded-sm">
				<div class="min-w-content w-content h-[762px] border-r border-r-bord-pr overflow-hidden relative">
					<div class="p-2 h-full bg-fill-fv">
						<template v-if="storyMedia">
							<div class="bg-black h-full rounded-md overflow-hidden">
								<div class="h-full flex items-center relative">
									<img class="w-full object-cover" v-bind:src="storyMedia.source_url" alt="Image">

									<div class="absolute top-4 left-4 size-8 bg-white rounded-full leading-none">
										<PrimaryIconButton v-on:click="deleteStoryMedia" iconName="x"></PrimaryIconButton>
									</div>
									<template v-if="isVideo">
										<div class="absolute bottom-4 right-4">
											<VideoDurationTime v-bind:videoDuration="storyMedia.duration"></VideoDurationTime>
										</div>
									</template>
								</div>
							</div>
						</template>
						<template v-else-if="state.videoClipCandidate">
							<div class="bg-black h-full rounded-md overflow-hidden flex flex-col">
								<video
									ref="videoClipPreview"
									v-bind:src="state.videoClipCandidate.objectUrl"
									v-on:loadedmetadata="syncClipPreview"
									class="flex-1 min-h-0 w-full object-contain"
									muted
									playsinline
									controls
								></video>
								<div class="shrink-0 bg-black px-4 py-4">
									<div class="flex items-center text-white text-par-s">
										<span>{{ formatClipTime(state.videoClipCandidate.clipStartSeconds) }} - {{ formatClipTime(state.videoClipCandidate.clipStartSeconds + state.videoClipCandidate.clipDurationSeconds) }}</span>
										<span class="ml-auto">{{ formatClipTime(state.videoClipCandidate.durationSeconds) }}</span>
									</div>
									<input
										v-model.number="state.videoClipCandidate.clipStartSeconds"
										v-on:input="syncClipPreview"
										type="range"
										min="0"
										v-bind:max="state.videoClipCandidate.maxStartSeconds"
										step="1"
										class="block w-full mt-3 accent-brand-900"
									>
									<div class="flex items-center justify-end gap-3 mt-4">
										<button v-on:click="cancelVideoClip" type="button" class="text-par-s text-white/80 hover:text-white">
											{{ $t('labels.cancel') }}
										</button>
										<button v-on:click="confirmVideoClip" type="button" class="h-9 px-4 rounded-sm bg-brand-900 text-white text-par-s font-medium">
											Next
										</button>
									</div>
								</div>
							</div>
						</template>
						<template v-else-if="state.isUploading">
							<div class="shadow-xs popup-background-tr rounded-md p-2 h-full">
								<div class="flex flex-col justify-center h-full border border-dashed border-edge-pr rounded-md smoothing">
									<div class="flex justify-center bounce-up">
										<img class="size-24" v-bind:src="$asset('assets/icons/upload.png')" alt="Image">
									</div>
									<h5 class="text-par-n text-brand-900 text-center">
										{{ $t('labels.uploading') }} {{ uploadProgress }}%
									</h5>
								</div>
							</div>
						</template>
						<template v-else>
							<StoryDropper v-on:click="selectStoryMedia" v-on:upload="handleMediaUpload"></StoryDropper>
						</template>
					</div>
				</div>
				<div class="flex-1 h-[762px]">
					<div class="flex flex-col h-full">
						<div class="border-b border-b-bord-pr">
							<StoryEditorHeader></StoryEditorHeader>
						</div>
						<div class="flex-1">
							<div class="border-b border-b-bord-pr">
								<div class="block">
									<textarea
										v-on:input="textInputHandler"
										v-model="storyData.content" 
										ref="storyTextInputField" 
										class="resize-none bg-transparent block min-h-40 w-full max-h-60 overflow-y-auto outline-hidden px-4 py-4 placeholder:font-light placeholder:text-par-s text-lab-pr text-par-s placeholder:text-lab-sc"
									v-bind:placeholder="$t('story.editor.add_caption')"></textarea>
								</div>
								<div class="flex items-center px-4 py-2">
									<div class="shrink-0">
										<span class="text-lab-sc text-cap-l">{{ storyData.content.length }}/{{ 1200 }}</span>
									</div>
									<div class="shrink-0 ml-auto">
										<div class="relative">
											<button v-on:click.stop="state.isEmojisPickerOpen = true" type="button" v-bind:disabled="state.isSubmitting" class="outline-hidden size-icon-small text-lab-sc hover:text-brand-900 disabled:opacity-80 disabled:cursor-wait">
												<SvgIcon type="line" name="face-smile"></SvgIcon>
											</button>
											<template v-if="state.isEmojisPickerOpen">
												<div class="block absolute top-6 right-0 w-80 z-50">
													<EmojisPicker 
														v-on:pick="insertStoryEmoji"
													v-on:close="state.isEmojisPickerOpen = false"></EmojisPicker>
												</div>
											</template>
										</div>
									</div>
								</div>
								<MentionsPicker 
									v-on:select="selectMention" 
								classes="w-full border-t border-bord-pr"></MentionsPicker>
							</div>
							<StoryPrivacyInfo></StoryPrivacyInfo>
						</div>
						<div class="border-t border-t-bord-pr flex justify-center py-4">
							<PrimaryTextButton v-bind:disabled="! isFormValid" v-bind:loading="state.isSubmitting" v-bind:buttonText="$t('story.editor.publish_story')" type="submit"></PrimaryTextButton>
						</div>
					</div>
				</div>
			</div>
			<div class="hidden">
				<input v-on:change="handleMediaSelect" capture="environment" type="file" accept="image/*, video/*" ref="stroyMediaFileInput">
			</div>
		</form>
	</div>
</template>

<script>
	import { defineComponent, reactive, ref, computed, defineAsyncComponent, nextTick, onUnmounted } from 'vue';
	
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { useStoriesEditorStore } from '@D/store/stories/editor.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { getStoryVideoClipCandidate, storyClipUploadOptions, formatStoryClipTime } from '@/kernel/services/media/story-video-clip.js';

	import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import StoryPrivacyInfo from '@D/components/stories/editor/parts/StoryPrivacyInfo.vue';
	import StoryEditorHeader from '@D/components/stories/editor/parts/StoryEditorHeader.vue';
	import StoryDropper from '@D/components/stories/editor/parts/StoryDropper.vue';
	import MentionsPicker from '@D/components/mentions/MentionsPicker.vue';

	export default defineComponent({
		setup: function() {
			const storiesEditorStore = useStoriesEditorStore();
			const stroyMediaFileInput = ref(null);
			const storyTextInputField = ref(null);
			const videoClipPreview = ref(null);
			const state = reactive({
				isEmojisPickerOpen: false,
				isSubmitting: false,
				isUploading: false,
				videoClipCandidate: null
			});

			const { autoResize, insertSymbolAtCaret, matchMention, completeText } = useInputHandlers();
			const storyData = ref(storiesEditorStore.storyData);

			const clearVideoClipCandidate = () => {
				if(state.videoClipCandidate?.objectUrl) {
					URL.revokeObjectURL(state.videoClipCandidate.objectUrl);
				}

				state.videoClipCandidate = null;
			};

			const syncClipPreview = () => {
				if(videoClipPreview.value && state.videoClipCandidate) {
					videoClipPreview.value.currentTime = Number(state.videoClipCandidate.clipStartSeconds || 0);
				}
			};

			const uploadSelectedMedia = async (file, options = {}) => {
				try {
					state.isUploading = true;
					await storiesEditorStore.uploadMedia(file, options);
					state.isUploading = false;
				} catch (e) {
					state.isUploading = false;

					toastError(e.message);
				}
			};

			const handleMediaUpload = async (file) => {
				if(! file) {
					return;
				}

				const clipCandidate = await getStoryVideoClipCandidate(file);

				if(clipCandidate?.requiresTrim) {
					clearVideoClipCandidate();
					state.videoClipCandidate = clipCandidate;

					nextTick(syncClipPreview);

					return;
				}

				await uploadSelectedMedia(file, storyClipUploadOptions(clipCandidate));
			};

			onUnmounted(() => {
				clearVideoClipCandidate();
			});

			return {
				state: state,
				videoClipPreview: videoClipPreview,
				storyMedia: computed(() => {
					return storiesEditorStore.storyMedia;
				}),
				isVideo: computed(() => {
					return storiesEditorStore.storyMedia.type === 'video';
				}),
				uploadProgress: computed(() => {
					return storiesEditorStore.uploadProgress;
				}),
				isFormValid: computed(() => {
					return storiesEditorStore.isFormValid;
				}),
				storyData: storyData,
				stroyMediaFileInput: stroyMediaFileInput,
				storyTextInputField: storyTextInputField,
				formatClipTime: formatStoryClipTime,
				syncClipPreview: syncClipPreview,
				cancelVideoClip: () => {
					clearVideoClipCandidate();
				},
				confirmVideoClip: async () => {
					const clipCandidate = state.videoClipCandidate;

					if(clipCandidate) {
						const mediaFile = clipCandidate.file;
						const uploadOptions = storyClipUploadOptions(clipCandidate);

						clearVideoClipCandidate();
						await uploadSelectedMedia(mediaFile, uploadOptions);
					}
				},
				insertStoryEmoji: (emojiSymbol) => {
					storyData.value.content = insertSymbolAtCaret(storyTextInputField.value, emojiSymbol);
                    storyTextInputField.value.focus();
				},
				submitForm: async () => {
					try {
						state.isSubmitting = true;
						await storiesEditorStore.publishStory();
						state.isSubmitting = false;

						toastSuccess(__t('toast.story.story_published'));

						storiesEditorStore.resetEditor();
						clearVideoClipCandidate();
						storiesEditorStore.closeEditor();
					} catch (e) {
						state.isSubmitting = false;
						toastError(e.message);
					}
				},
				deleteStoryMedia: async () => {
					try {
						await storiesEditorStore.deleteMedia();
					} catch (e) {
						toastError(e.message);
					}
				},
				selectStoryMedia: () => {
					stroyMediaFileInput.value.click();
				},
				handleMediaUpload: handleMediaUpload,
				handleMediaSelect: async (event) => {
					await handleMediaUpload(event.target.files[0]);
					event.target.value = '';
				},
				textInputHandler: () => {
					autoResize(storyTextInputField.value);

					const mentionMatch = matchMention(storyTextInputField.value);

					if(mentionMatch) {
						colibriEventBus.emit('editor:mention-input', mentionMatch.username);
					}
				},
				selectMention: (username) => {
					let mentionMatch = matchMention(storyTextInputField.value);

					if(mentionMatch) {
						storyData.value.content = completeText(storyTextInputField.value, {
							completable: `@${username}`,
							start: mentionMatch.start,
							end: mentionMatch.end
						});

						storyTextInputField.value.focus();
					}
                }
			};
		},
		components: {
			PrimaryTextButton: PrimaryTextButton,
			PrimaryIconButton: PrimaryIconButton,
			EmojisPicker: defineAsyncComponent(() => {
                return import('@D/components/emojis/EmojisPicker.vue');
            }),
			StoryPrivacyInfo: StoryPrivacyInfo,
			StoryEditorHeader: StoryEditorHeader,
			StoryDropper: StoryDropper,
			VideoDurationTime: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/media/video/VideoDurationTime.vue');
            }),
			MentionsPicker: MentionsPicker
		}
	});
</script>
