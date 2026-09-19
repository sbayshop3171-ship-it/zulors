<template>
	<div class="w-sided-content">
		<form v-on:submit.prevent="submitForm">
			<div class="flex items-stretch overflow-hidden popup-background-tr border border-bord-pr rounded-sm">
				<div class="min-w-content w-content h-[762px] border-r border-r-bord-pr overflow-hidden relative">
					<div class="p-2 h-full bg-fill-fv">
						<template v-if="storyMedia">
							<div class="story-media-stage rounded-md">
								<div class="h-full relative">
									<video
										v-if="isVideo"
										v-bind:src="storyVideoPreviewUrl"
										v-bind:poster="storyVideoPosterUrl"
										v-bind:class="storyPreviewFitClass"
										v-on:loadedmetadata="handleStoryVideoLoadedMetadata"
										v-on:loadeddata="playStoryPreview"
										v-on:canplay="playStoryPreview"
										v-on:playing="handleStoryPreviewPlaying"
										v-on:pause="handleStoryPreviewPaused"
										v-on:timeupdate="handleStoryPreviewTimeUpdate"
										v-on:click="toggleStoryPreviewPlayback"
										ref="storyMediaVideoPreview"
										class="story-media-foreground cursor-pointer"
										autoplay
										loop
										muted
										webkit-playsinline
										playsinline
										preload="metadata"
										controlslist="nodownload noplaybackrate noremoteplayback"
										disablepictureinpicture
									></video>
									<video
										v-if="isVideo && showStoryBlurBackdrop && storyVideoPreviewUrl"
										ref="storyMediaBackdropVideo"
										v-bind:src="storyVideoPreviewUrl"
										class="story-media-backdrop"
										autoplay
										loop
										webkit-playsinline
										playsinline
										muted
										preload="metadata"
										aria-hidden="true"
										tabindex="-1"
									></video>
									<button
										v-if="isVideo && ! state.isStoryPreviewPlaying"
										v-on:click.stop="playStoryPreview"
										type="button"
										class="absolute left-1/2 top-1/2 z-30 inline-flex size-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white shadow-lg backdrop-blur-md"
										aria-label="Play video"
									>
										<SvgIcon name="play" type="solid" classes="ml-1 size-6"></SvgIcon>
									</button>
									<img
										v-if="! isVideo && showStoryBlurBackdrop"
										v-bind:src="storyMedia.source_url"
										class="story-media-backdrop"
										alt=""
										aria-hidden="true"
									>
									<div v-if="showStoryBlurBackdrop" class="story-media-backdrop-shade"></div>
									<img
										v-if="! isVideo"
										v-bind:class="storyPreviewFitClass"
										v-on:load="handleStoryImageLoaded"
										class="story-media-foreground"
										v-bind:src="storyMedia.source_url"
										alt="Image"
									>

									<div class="absolute top-4 left-4 z-20 size-8 bg-white rounded-full leading-none">
										<PrimaryIconButton v-on:click="deleteStoryMedia" iconName="x"></PrimaryIconButton>
									</div>
									<template v-if="isVideo && storyVideoDuration">
										<div class="absolute bottom-4 right-4 z-20">
											<VideoDurationTime v-bind:videoDuration="storyVideoDuration"></VideoDurationTime>
										</div>
									</template>

									<div class="absolute right-4 top-16 z-20 flex flex-col items-center gap-3">
										<button v-on:click="focusTextTool" type="button" class="inline-flex size-10 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-md">
											<span class="text-par-s font-semibold leading-none">Aa</span>
										</button>
										<button type="button" class="inline-flex size-10 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-md">
											<SvgIcon name="face-smile" type="line" classes="size-5"></SvgIcon>
										</button>
										<button v-on:click="openMusicPicker" type="button" class="inline-flex size-10 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-md">
											<SvgIcon name="music-note-01" type="line" classes="size-5"></SvgIcon>
										</button>
										<button v-on:click="toggleStoryVolume" type="button" class="inline-flex size-10 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-md">
											<SvgIcon v-bind:name="state.videoVolume > 0 ? 'volume-max' : 'volume-x'" type="line" classes="size-5"></SvgIcon>
										</button>
										<button type="button" class="inline-flex size-10 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-md">
											<SvgIcon name="stars-01" type="line" classes="size-5"></SvgIcon>
										</button>
									</div>

									<div v-if="selectedStoryMusicTrack" class="absolute left-1/2 top-16 z-20 flex max-w-[70%] -translate-x-1/2 items-center gap-2 rounded-xl bg-black/55 px-2 py-1.5 text-white shadow-lg backdrop-blur-md">
										<div class="size-8 shrink-0 overflow-hidden rounded-md bg-white/10">
											<img v-if="selectedStoryMusicTrack.cover_url" v-bind:src="selectedStoryMusicTrack.cover_url" class="size-full object-cover" alt="">
											<div v-else class="flex size-full items-center justify-center">
												<SvgIcon name="music-note-01" type="line" classes="size-4"></SvgIcon>
											</div>
										</div>
										<span class="min-w-0 truncate text-cap-l font-semibold">{{ selectedStoryMusicTrack.title }}</span>
										<button v-on:click="clearStoryMusicTrack" type="button" class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-white/15 text-white">
											<SvgIcon name="x" type="solid" classes="size-3.5"></SvgIcon>
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
							<PublicationAudience v-if="isLocalPublication" class="px-4" />
							<StoryPrivacyInfo v-else></StoryPrivacyInfo>
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
			<StoryMusicPicker
				v-bind:open="state.isMusicPickerOpen"
				v-bind:selectedTrack="selectedStoryMusicTrack"
				v-on:close="state.isMusicPickerOpen = false"
				v-on:select="selectStoryMusicTrack"
			></StoryMusicPicker>
		</form>
	</div>
</template>

<script>
	import { defineComponent, reactive, ref, computed, defineAsyncComponent, onBeforeUnmount } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { durationObjectToSeconds, durationSecondsToObject } from '@/kernel/helpers/media/audio/index.js';
	import PublicationAudience from '@/kernel/vue/components/media/publications/PublicationAudience.vue';
	import { useStoriesEditorStore } from '@D/store/stories/editor.store.js';
	import { publicationManager } from '@/kernel/services/media/publications/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { getStoryVideoClipCandidate, storyClipUploadOptions, STORY_VIDEO_CLIP_SECONDS } from '@/kernel/services/media/story-video-clip.js';
	import { elementImageDimensions, elementVideoDimensions, shouldUseStoryBlurBackdrop, storyMediaObjectFitClass } from '@/kernel/services/media/story-media-presentation.js';

	import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import StoryPrivacyInfo from '@D/components/stories/editor/parts/StoryPrivacyInfo.vue';
	import StoryEditorHeader from '@D/components/stories/editor/parts/StoryEditorHeader.vue';
	import StoryDropper from '@D/components/stories/editor/parts/StoryDropper.vue';
	import MentionsPicker from '@D/components/mentions/MentionsPicker.vue';
	import StoryMusicPicker from '@/kernel/vue/components/story/StoryMusicPicker.vue';

	export default defineComponent({
		setup: function() {
			const storiesEditorStore = useStoriesEditorStore();
			const stroyMediaFileInput = ref(null);
			const storyTextInputField = ref(null);
			const storyMediaVideoPreview = ref(null);
			const storyMediaBackdropVideo = ref(null);
			const state = reactive({
				isEmojisPickerOpen: false,
				isMusicPickerOpen: false,
				isStoryPreviewPlaying: false,
				isSubmitting: false,
				isUploading: false,
				videoVolume: 0
			});
			const previewLoadedDimensions = ref({});
			let storyMusicAudio = null;

			const { autoResize, insertSymbolAtCaret, matchMention, completeText } = useInputHandlers();
			const storyData = ref(storiesEditorStore.storyData);
			const storyMedia = computed(() => {
				return storiesEditorStore.storyMedia;
			});
			const stopMusicPreview = () => {
				if(storyMusicAudio) {
					storyMusicAudio.pause();
					storyMusicAudio.src = '';
					storyMusicAudio = null;
				}
			};
			const previewSelectedMusic = async (trackData) => {
				stopMusicPreview();
				if(! trackData?.id) return;
				try {
					const response = await colibriAPI().storyMusic().getFrom(`tracks/${trackData.id}/play-url`);
					const playUrl = response.data.data.play_url;
					if(! playUrl) return;
					storyMusicAudio = new Audio(playUrl);
					storyMusicAudio.loop = true;
					storyMusicAudio.volume = 0.85;
					storyMusicAudio.play().catch(() => {});
				} catch (error) {
					toastError(error.response?.data?.message || 'Unable to preview this track.');
				}
			};
			const focusTextTool = () => {
				storyTextInputField.value?.focus();
			};

			const playVideo = (videoElement) => {
				const playPromise = videoElement?.play?.();

				if(playPromise?.catch) {
					playPromise.catch(() => {});
				}
			};

			const syncVideoBackdrop = (foregroundRef, backdropRef) => {
				const foregroundVideo = foregroundRef.value;
				const backdropVideo = backdropRef.value;

				if(! foregroundVideo || ! backdropVideo) {
					return;
				}

				try {
					if(backdropVideo.readyState > 0 && Number.isFinite(foregroundVideo.currentTime) && Math.abs(backdropVideo.currentTime - foregroundVideo.currentTime) > 0.2) {
						backdropVideo.currentTime = foregroundVideo.currentTime;
					}

					if(foregroundVideo.paused || foregroundVideo.ended) {
						backdropVideo.pause();
					}
					else {
						playVideo(backdropVideo);
					}
				} catch (error) {}
			};

			const syncStoryPreviewBackdrop = () => {
				syncVideoBackdrop(storyMediaVideoPreview, storyMediaBackdropVideo);
			};

			const playStoryPreview = () => {
				const videoElement = storyMediaVideoPreview.value;

				if(! videoElement) {
					return;
				}

				videoElement.muted = state.videoVolume === 0;
				videoElement.volume = state.videoVolume;
				videoElement.loop = true;

				const playPromise = videoElement.play?.();

				if(playPromise?.then) {
					playPromise.then(() => {
						state.isStoryPreviewPlaying = true;
						syncStoryPreviewBackdrop();
					}).catch(() => {
						state.isStoryPreviewPlaying = false;
					});
				}
				else {
					state.isStoryPreviewPlaying = ! videoElement.paused;
					syncStoryPreviewBackdrop();
				}
			};

			const handleStoryPreviewPlaying = () => {
				state.isStoryPreviewPlaying = true;
				syncStoryPreviewBackdrop();
			};

			const handleStoryPreviewPaused = () => {
				state.isStoryPreviewPlaying = false;
				syncStoryPreviewBackdrop();
			};

			const handleStoryPreviewTimeUpdate = () => {
				const videoElement = storyMediaVideoPreview.value;

				if(videoElement && videoElement.currentTime >= STORY_VIDEO_CLIP_SECONDS) {
					videoElement.currentTime = 0;
				}

				syncStoryPreviewBackdrop();
			};

			const toggleStoryPreviewPlayback = () => {
				const videoElement = storyMediaVideoPreview.value;

				if(! videoElement) {
					return;
				}

				if(videoElement.paused || videoElement.ended) {
					playStoryPreview();
				}
				else {
					videoElement.pause();
					state.isStoryPreviewPlaying = false;
					syncStoryPreviewBackdrop();
				}
			};

			const storyVideoDisplayDuration = () => {
				const mediaItem = storiesEditorStore.storyMedia || {};
				const rawDuration = mediaItem.duration || mediaItem.metadata?.duration || null;
				const durationSeconds = durationObjectToSeconds(rawDuration) || Number(mediaItem.duration_seconds || mediaItem.metadata?.duration_seconds || 0);

				if(durationSeconds > 0) {
					return durationSecondsToObject(Math.min(durationSeconds, STORY_VIDEO_CLIP_SECONDS));
				}

				return rawDuration;
			};
			onBeforeUnmount(stopMusicPreview);

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

				await uploadSelectedMedia(file, storyClipUploadOptions(clipCandidate));
			};

			return {
				state: state,
				isLocalPublication: computed(() => Boolean(storiesEditorStore.publicationSelection)),
				storyMediaVideoPreview: storyMediaVideoPreview,
				storyMediaBackdropVideo: storyMediaBackdropVideo,
				storyMedia: storyMedia,
				selectedStoryMusicTrack: computed(() => {
					return storiesEditorStore.selectedMusicTrack;
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
					return storyVideoDisplayDuration();
				}),
				uploadProgress: computed(() => {
					return storiesEditorStore.uploadProgress;
				}),
				storyPreviewFitClass: computed(() => {
					return storyMediaObjectFitClass(storyMedia.value || {}, previewLoadedDimensions.value);
				}),
				showStoryBlurBackdrop: computed(() => {
					return shouldUseStoryBlurBackdrop(storyMedia.value || {}, previewLoadedDimensions.value);
				}),
				isFormValid: computed(() => {
					return storiesEditorStore.isFormValid;
				}),
				storyData: storyData,
				stroyMediaFileInput: stroyMediaFileInput,
				storyTextInputField: storyTextInputField,
				syncStoryPreviewBackdrop: syncStoryPreviewBackdrop,
				playStoryPreview: playStoryPreview,
				toggleStoryPreviewPlayback: toggleStoryPreviewPlayback,
				handleStoryPreviewPlaying: handleStoryPreviewPlaying,
				handleStoryPreviewPaused: handleStoryPreviewPaused,
				handleStoryPreviewTimeUpdate: handleStoryPreviewTimeUpdate,
				handleStoryVideoLoadedMetadata: (event) => {
					previewLoadedDimensions.value = elementVideoDimensions(event.target);
					syncStoryPreviewBackdrop();
					playStoryPreview();
				},
				handleStoryImageLoaded: (event) => {
					previewLoadedDimensions.value = elementImageDimensions(event.target);
				},
				insertStoryEmoji: (emojiSymbol) => {
					storyData.value.content = insertSymbolAtCaret(storyTextInputField.value, emojiSymbol);
                    storyTextInputField.value.focus();
				},
				focusTextTool: focusTextTool,
				toggleStoryVolume: () => {
					state.videoVolume = state.videoVolume > 0 ? 0 : 1;
					if(storyMediaVideoPreview.value) {
						storyMediaVideoPreview.value.muted = state.videoVolume === 0;
						storyMediaVideoPreview.value.volume = state.videoVolume;
					}
				},
				submitForm: async () => {
					if (state.isSubmitting) return;
					try {
						state.isSubmitting = true;
						const result = await storiesEditorStore.publishStory();
						state.isSubmitting = false;

						toastSuccess(result?.queued ? 'Story upload started' : __t('toast.story.story_published'));

						storiesEditorStore.resetEditor();
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
				openMusicPicker: () => {
					state.isMusicPickerOpen = true;
				},
				selectStoryMusicTrack: (trackData) => {
					storiesEditorStore.setSelectedMusicTrack(trackData);
					previewSelectedMusic(trackData);
					state.isMusicPickerOpen = false;
				},
				clearStoryMusicTrack: () => {
					storiesEditorStore.clearSelectedMusicTrack();
					stopMusicPreview();
				},
				selectStoryMedia: async () => {
					try {
						const files = await publicationManager.pick('story', 'media');
						if (files === null) stroyMediaFileInput.value.click();
						else if (files[0]) await handleMediaUpload(files[0]);
					} catch (error) { toastError(error.message); }
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
			PublicationAudience,
			PrimaryTextButton: PrimaryTextButton,
			PrimaryIconButton: PrimaryIconButton,
			EmojisPicker: defineAsyncComponent(() => {
                return import('@D/components/emojis/EmojisPicker.vue');
            }),
			StoryPrivacyInfo: StoryPrivacyInfo,
			StoryEditorHeader: StoryEditorHeader,
			StoryDropper: StoryDropper,
			StoryMusicPicker: StoryMusicPicker,
			VideoDurationTime: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/media/video/VideoDurationTime.vue');
            }),
			MentionsPicker: MentionsPicker
		}
	});
</script>
