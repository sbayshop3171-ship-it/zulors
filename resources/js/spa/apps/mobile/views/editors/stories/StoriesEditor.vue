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
						v-if="storyMedia || isUploading"
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

			<template v-if="isUploading">
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

				<div class="absolute right-4 z-30 flex flex-col items-center gap-3" style="top: calc(var(--mobile-safe-top, 0px) + 4.75rem);">
					<button type="button" class="inline-flex size-11 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-md">
						<span class="text-par-l font-semibold leading-none">Aa</span>
					</button>
					<button type="button" class="inline-flex size-11 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-md">
						<SvgIcon name="face-smile" type="line" classes="size-6"></SvgIcon>
					</button>
					<button v-on:click="openMusicPicker" type="button" class="inline-flex size-11 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-md">
						<SvgIcon name="music-note-01" type="line" classes="size-6"></SvgIcon>
					</button>
					<button type="button" class="inline-flex size-11 items-center justify-center rounded-full bg-black/45 text-white shadow-lg backdrop-blur-md">
						<SvgIcon name="stars-01" type="line" classes="size-6"></SvgIcon>
					</button>
				</div>

				<div v-if="selectedStoryMusicTrack" class="absolute left-1/2 z-30 flex max-w-[72vw] -translate-x-1/2 items-center gap-2 rounded-xl bg-black/55 px-2 py-1.5 text-white shadow-lg backdrop-blur-md" style="top: calc(var(--mobile-safe-top, 0px) + 4.75rem);">
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

		<StoryMusicPicker
			v-bind:open="state.isMusicPickerOpen"
			v-bind:selectedTrack="selectedStoryMusicTrack"
			v-on:close="state.isMusicPickerOpen = false"
			v-on:select="selectStoryMusicTrack"
		></StoryMusicPicker>
	</div>
</template>

<script>
	import { defineComponent, reactive, ref, computed, defineAsyncComponent, watch } from 'vue';
	import { useRouter } from 'vue-router';

	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import PublicationAudience from '@/kernel/vue/components/media/publications/PublicationAudience.vue';
	import { useStoriesEditorStore } from '@M/store/stories/editor.store.js';
	import { elementImageDimensions, elementVideoDimensions, shouldUseStoryBlurBackdrop, storyMediaObjectFitClass } from '@/kernel/services/media/story-media-presentation.js';

	import PrimaryTextButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import StoryPrivacyInfo from '@M/views/editors/stories/parts/StoryPrivacyInfo.vue';
	import StoryMusicPicker from '@/kernel/vue/components/story/StoryMusicPicker.vue';

	export default defineComponent({
		setup: function() {
			const storiesEditorStore = useStoriesEditorStore();
			const storyTextInputField = ref(null);
			const storyMediaVideoPreview = ref(null);
			const storyMediaBackdropVideo = ref(null);
			const router = useRouter();
			const state = reactive({
				isSubmitting: false,
				isMusicPickerOpen: false
			});
			const previewLoadedDimensions = ref({});

			const { autoResize } = useInputHandlers();
			const storyData = ref(storiesEditorStore.storyData);
			const storyMedia = computed(() => {
				return storiesEditorStore.storyMedia;
			});
			const deleteStoryMedia = () => {
				try {
					if(storiesEditorStore.storyMedia) {
						storiesEditorStore.deleteMedia();
					}
					else if(storiesEditorStore.isUploading) {
						storiesEditorStore.cancelPendingUpload();
					}

				} catch (e) {
					toastError(e.message);
				}
			}

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

			watch(() => {
				return [
					storiesEditorStore.storyMedia,
					storiesEditorStore.isUploading
				];
			}, ([storyMedia, isUploading]) => {
				if(! storyMedia && ! isUploading) {
					router.replace({
						name: 'home_index'
					});
				}
			}, {
				immediate: true
			});

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
				syncStoryPreviewBackdrop: syncStoryPreviewBackdrop,
				handleStoryVideoLoadedMetadata: (event) => {
					previewLoadedDimensions.value = elementVideoDimensions(event.target);
					syncStoryPreviewBackdrop();
				},
				handleStoryImageLoaded: (event) => {
					previewLoadedDimensions.value = elementImageDimensions(event.target);
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
				openMusicPicker: () => {
					state.isMusicPickerOpen = true;
				},
				selectStoryMusicTrack: (trackData) => {
					storiesEditorStore.setSelectedMusicTrack(trackData);
					state.isMusicPickerOpen = false;
				},
				clearStoryMusicTrack: () => {
					storiesEditorStore.clearSelectedMusicTrack();
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
			StoryMusicPicker: StoryMusicPicker,
			VideoDurationTime: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/media/video/VideoDurationTime.vue');
            })
		}
	});
</script>
