<template>
	<div class="story-viewport fixed inset-0 z-50 bg-black text-white">
		<form v-on:submit.prevent="submitForm" class="relative h-full w-full overflow-hidden">
			<div class="absolute left-0 right-0 top-0 z-30 flex items-center gap-2 px-3 pb-5 from-black/70 to-transparent bg-gradient-to-b" style="padding-top: calc(var(--mobile-safe-top, 0px) + 0.75rem);">
				<div class="shrink-0">
					<PrimaryIconButton
						v-on:click="leaveEditor"
						iconName="chevron-left"
						iconSize="7"
						iconAreaSize="11"
						buttonColor="text-white"
						hoverText="hover:text-white"
						hoverBg="hover:bg-white/10"
					></PrimaryIconButton>
				</div>
				<h4 class="min-w-0 flex-1 truncate text-center text-title-3 font-medium text-white">
					{{ $t('labels.new_story') }}
				</h4>
				<div class="shrink-0">
					<PrimaryIconButton
						v-if="storyMedia || isUploading || videoClipCandidate"
						v-on:click="deleteStoryMedia"
						iconName="x"
						iconSize="6"
						iconAreaSize="11"
						buttonColor="text-white"
						hoverText="hover:text-white"
						hoverBg="hover:bg-white/10"
					></PrimaryIconButton>
					<div v-else class="size-11"></div>
				</div>
			</div>

			<template v-if="videoClipCandidate">
				<div class="absolute inset-0">
					<div class="story-media-stage">
						<video
							v-if="showVideoClipBlurBackdrop"
							ref="videoClipBackdropPreview"
							v-bind:src="videoClipCandidate.objectUrl"
							class="story-media-backdrop"
							muted
							webkit-playsinline
							playsinline
							preload="metadata"
							aria-hidden="true"
							tabindex="-1"
						></video>
						<div v-if="showVideoClipBlurBackdrop" class="story-media-backdrop-shade"></div>
						<video
							ref="videoClipPreview"
							v-bind:src="videoClipCandidate.objectUrl"
							v-bind:class="videoClipFitClass"
							v-on:loadedmetadata="handleClipLoadedMetadata"
							v-on:loadeddata="syncClipBackdrop"
							v-on:play="syncClipBackdrop"
							v-on:pause="syncClipBackdrop"
							v-on:timeupdate="syncClipBackdrop"
							class="story-media-foreground"
							muted
							webkit-playsinline
							playsinline
							controls
						></video>
					</div>
				</div>

				<div class="absolute bottom-0 left-0 right-0 z-30 px-4 pt-16 from-black/80 via-black/60 to-transparent bg-gradient-to-t" style="padding-bottom: calc(var(--mobile-safe-bottom, 0px) + 1rem);">
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
						class="block w-full mt-3 accent-white"
					>
					<div class="flex items-center justify-end gap-4 mt-4">
						<button v-on:click="cancelVideoClip" type="button" class="text-par-s text-white/80">
							{{ $t('labels.cancel') }}
						</button>
						<button v-on:click="confirmVideoClip" type="button" class="h-10 px-5 rounded-full bg-white text-black text-par-s font-semibold">
							Next
						</button>
					</div>
				</div>
			</template>

			<template v-else-if="isUploading">
				<div class="absolute inset-0 flex items-center justify-center px-8">
					<div class="w-full max-w-xs rounded-2xl border border-white/15 bg-black/35 p-5 backdrop-blur-md">
						<div class="bg-white/20 h-1.5 rounded-full overflow-hidden">
							<div class="bg-white min-w-10 h-full" v-bind:style="{ width: uploadProgress + '%' }"></div>
						</div>
						<p class="text-center text-par-s text-white mt-4">
							{{ $t('labels.uploading') }} {{ uploadProgress }}%
						</p>
					</div>
				</div>
			</template>

			<template v-else-if="storyMedia">
				<div class="absolute inset-0">
					<div class="story-media-stage">
						<template v-if="isVideo">
							<video
								v-if="showStoryBlurBackdrop && storyVideoPreviewUrl"
								ref="storyMediaBackdropVideo"
								v-bind:src="storyVideoPreviewUrl"
								class="story-media-backdrop"
								muted
								webkit-playsinline
								playsinline
								preload="metadata"
								aria-hidden="true"
								tabindex="-1"
							></video>
							<div v-if="showStoryBlurBackdrop" class="story-media-backdrop-shade"></div>
							<video
								ref="storyMediaVideoPreview"
								v-bind:src="storyVideoPreviewUrl"
								v-bind:poster="storyVideoPosterUrl"
								v-bind:class="storyPreviewFitClass"
								v-on:loadedmetadata="handleStoryVideoLoadedMetadata"
								v-on:loadeddata="syncStoryPreviewBackdrop"
								v-on:play="syncStoryPreviewBackdrop"
								v-on:pause="syncStoryPreviewBackdrop"
								v-on:timeupdate="syncStoryPreviewBackdrop"
								class="story-media-foreground"
								webkit-playsinline
								playsinline
								preload="metadata"
								controls
							></video>
							<div v-if="storyVideoDuration" class="pointer-events-none absolute right-4 z-20" style="bottom: calc(var(--mobile-safe-bottom, 0px) + 15rem);">
								<VideoDurationTime v-bind:videoDuration="storyVideoDuration"></VideoDurationTime>
							</div>
						</template>
						<template v-else>
							<img
								v-if="showStoryBlurBackdrop"
								v-bind:src="storyMedia.source_url"
								class="story-media-backdrop"
								alt=""
								aria-hidden="true"
							>
							<div v-if="showStoryBlurBackdrop" class="story-media-backdrop-shade"></div>
							<img
								v-bind:src="storyMedia.source_url"
								v-bind:class="storyPreviewFitClass"
								v-on:load="handleStoryImageLoaded"
								class="story-media-foreground"
								alt="Image"
							>
						</template>
					</div>
				</div>

				<div class="absolute bottom-0 left-0 right-0 z-30 px-4 pt-16 from-black/80 via-black/55 to-transparent bg-gradient-to-t" style="padding-bottom: calc(var(--mobile-safe-bottom, 0px) + 1rem);">
					<textarea
						v-on:input="textInputHandler"
						v-model="storyData.content"
						ref="storyTextInputField"
						class="resize-none block min-h-12 w-full max-h-28 overflow-y-auto rounded-2xl border border-white/20 bg-black/30 px-4 py-3 outline-hidden backdrop-blur-md placeholder:text-par-m text-white text-par-m placeholder:text-white/65"
						v-bind:placeholder="$t('story.editor.add_caption')"
					></textarea>
					<div class="flex items-center px-1 pt-2">
						<span class="text-white/70 text-cap-l">{{ storyData.content.length }}/{{ 1200 }}</span>
					</div>
					<div v-if="isLocalPublication" class="[&_*]:!text-white/80">
						<PublicationAudience></PublicationAudience>
					</div>
					<StoryPrivacyInfo v-else></StoryPrivacyInfo>
					<div class="mt-3">
						<PrimaryTextButton
							v-bind:buttonFluid="true"
							v-bind:isDisabled="! isFormValid"
							v-bind:loading="state.isSubmitting"
							v-bind:buttonText="$t('story.editor.publish_story')"
							buttonRole="accent"
							buttonType="submit"
						></PrimaryTextButton>
					</div>
				</div>
			</template>
		</form>
	</div>
</template>

<script>
	import { defineComponent, reactive, ref, computed, onMounted, onUnmounted, defineAsyncComponent, nextTick, watch } from 'vue';
	import { useRouter } from 'vue-router';

	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import PublicationAudience from '@/kernel/vue/components/media/publications/PublicationAudience.vue';
	import { useStoriesEditorStore } from '@M/store/stories/editor.store.js';
	import { storyClipUploadOptions, formatStoryClipTime } from '@/kernel/services/media/story-video-clip.js';
	import { elementImageDimensions, elementVideoDimensions, shouldUseStoryBlurBackdrop, storyMediaObjectFitClass } from '@/kernel/services/media/story-media-presentation.js';

	import PrimaryTextButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import StoryPrivacyInfo from '@M/views/editors/stories/parts/StoryPrivacyInfo.vue';

	export default defineComponent({
		setup: function() {
			const storiesEditorStore = useStoriesEditorStore();
			const storyTextInputField = ref(null);
			const videoClipPreview = ref(null);
			const videoClipBackdropPreview = ref(null);
			const storyMediaVideoPreview = ref(null);
			const storyMediaBackdropVideo = ref(null);
			const router = useRouter();
			const state = reactive({
				isSubmitting: false
			});
			const clipLoadedDimensions = ref({});
			const previewLoadedDimensions = ref({});

			const { autoResize } = useInputHandlers();
			const storyData = ref(storiesEditorStore.storyData);
			const storyMedia = computed(() => {
				return storiesEditorStore.storyMedia;
			});
			const videoClipCandidate = computed(() => {
				return storiesEditorStore.videoClipCandidate;
			});
			const videoClipMedia = computed(() => {
				return {
					metadata: videoClipCandidate.value?.metadata || {}
				};
			});

			const deleteStoryMedia = () => {
				try {
					if(storiesEditorStore.storyMedia) {
						storiesEditorStore.deleteMedia();
					}
					else if(storiesEditorStore.isUploading) {
						storiesEditorStore.cancelPendingUpload();
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

				syncClipBackdrop();
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

			const syncClipBackdrop = () => {
				syncVideoBackdrop(videoClipPreview, videoClipBackdropPreview);
			};

			const syncStoryPreviewBackdrop = () => {
				syncVideoBackdrop(storyMediaVideoPreview, storyMediaBackdropVideo);
			};

			onMounted(() => {
				nextTick(syncClipPreview);
			});

			watch(() => {
				return [
					storiesEditorStore.storyMedia,
					storiesEditorStore.videoClipCandidate,
					storiesEditorStore.isUploading
				];
			}, ([storyMedia, videoClipCandidate, isUploading]) => {
				if(! storyMedia && ! videoClipCandidate && ! isUploading) {
					router.replace({
						name: 'home_index'
					});
				}
			}, {
				immediate: true
			});

			onUnmounted(() => {
				if(! storiesEditorStore.storyMedia && ! storiesEditorStore.isUploading) {
					storiesEditorStore.clearVideoClipCandidate();
				}
			});

			return {
				state: state,
				isLocalPublication: computed(() => Boolean(storiesEditorStore.publicationSelection)),
				videoClipPreview: videoClipPreview,
				videoClipBackdropPreview: videoClipBackdropPreview,
				storyMediaVideoPreview: storyMediaVideoPreview,
				storyMediaBackdropVideo: storyMediaBackdropVideo,
				storyMedia: storyMedia,
				videoClipCandidate: videoClipCandidate,
				isVideo: computed(() => {
					return storiesEditorStore.storyMedia?.type === 'video';
				}),
				isUploading: computed(() => {
					return storiesEditorStore.isUploading;
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
				videoClipFitClass: computed(() => {
					return storyMediaObjectFitClass(videoClipMedia.value, clipLoadedDimensions.value);
				}),
				showVideoClipBlurBackdrop: computed(() => {
					return shouldUseStoryBlurBackdrop(videoClipMedia.value, clipLoadedDimensions.value);
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
				storyTextInputField: storyTextInputField,
				formatClipTime: formatStoryClipTime,
				syncClipPreview: syncClipPreview,
				syncClipBackdrop: syncClipBackdrop,
				syncStoryPreviewBackdrop: syncStoryPreviewBackdrop,
				handleClipLoadedMetadata: (event) => {
					clipLoadedDimensions.value = elementVideoDimensions(event.target);
					syncClipPreview();
				},
				handleStoryVideoLoadedMetadata: (event) => {
					previewLoadedDimensions.value = elementVideoDimensions(event.target);
					syncStoryPreviewBackdrop();
				},
				handleStoryImageLoaded: (event) => {
					previewLoadedDimensions.value = elementImageDimensions(event.target);
				},
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
						await storiesEditorStore.uploadMedia(mediaFile, uploadOptions);
					}
					catch (e) {
						toastError(e.message);
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
			PublicationAudience,
			PrimaryTextButton: PrimaryTextButton,
			PrimaryIconButton: PrimaryIconButton,
			StoryPrivacyInfo: StoryPrivacyInfo,
			VideoDurationTime: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/media/video/VideoDurationTime.vue');
            })
		}
	});
</script>
