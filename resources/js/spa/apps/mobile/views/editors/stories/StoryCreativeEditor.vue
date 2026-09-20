<template>
	<div class="story-creative-editor fixed inset-0 z-[60] bg-black text-white">
		<div class="story-creative-editor__header">
			<button
				type="button"
				class="story-creative-editor__icon-button"
				aria-label="Close story editor"
				v-on:click="leaveEditor"
			>
				<SvgIcon name="chevron-left" type="line" classes="size-5"></SvgIcon>
			</button>

			<div class="min-w-0 flex-1 text-center">
				<p class="truncate text-par-m font-semibold text-white">Edit story</p>
				<p class="truncate text-cap-l text-white/60">{{ mediaName }}</p>
			</div>

			<button
				type="button"
				class="story-creative-editor__save-button"
				v-bind:disabled="isBusy || ! editorReady"
				v-on:click="saveAndNext"
			>
				<span v-if="isBusy">{{ busyLabel }}</span>
				<span v-else>Done</span>
			</button>
		</div>

		<div class="story-creative-editor__canvas">
			<CreativeEditor
				v-if="editorConfig.license"
				v-bind:config="editorConfig"
				v-bind:init="initEditor"
				width="100%"
				height="100%"
				v-on:error="handleEditorError"
				v-on:loading-state-change="handleLoadingStateChange"
			></CreativeEditor>

			<div v-if="! editorReady && ! editorError" class="story-creative-editor__loading">
				<div class="size-10 animate-spin rounded-full border-2 border-white/20 border-t-white"></div>
				<p class="mt-4 text-par-s text-white/80">{{ loadingLabel }}</p>
			</div>

			<div v-if="editorError" class="story-creative-editor__error">
				<p class="text-par-m font-semibold text-white">Creative editor could not start</p>
				<p class="mt-2 max-w-xs text-center text-par-s text-white/70">{{ editorError }}</p>
				<button
					v-if="legacyFallbackEnabled"
					type="button"
					class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-5 text-par-s font-semibold text-black"
					v-on:click="useLegacyEditor"
				>
					Use standard editor
				</button>
			</div>
		</div>

		<div class="story-creative-editor__footer">
			<button
				type="button"
				class="story-creative-editor__tool-button"
				v-bind:disabled="! editorReady || isBusy"
				v-on:click="openTextTools"
			>
				<span class="font-semibold">Aa</span>
				<span>Text</span>
			</button>
				<button
					type="button"
					class="story-creative-editor__tool-button"
					v-bind:disabled="! editorReady || isBusy"
					v-on:click="openMusicPicker"
				>
				<SvgIcon name="music-note-01" type="line" classes="size-4"></SvgIcon>
				<span class="truncate">{{ selectedMusicTrack?.title || 'Music' }}</span>
			</button>
			<span class="text-cap-l text-white/55">Edits stay on this device until Next</span>
		</div>

		<StoryMusicPicker
			v-bind:open="state.isMusicPickerOpen"
			v-bind:selectedTrack="selectedMusicTrack"
			v-on:close="state.isMusicPickerOpen = false"
			v-on:select="selectMusicTrack"
		></StoryMusicPicker>
	</div>
</template>

<script>
	import { computed, defineComponent, onBeforeUnmount, reactive, ref } from 'vue';
	import { useRouter } from 'vue-router';
	import CreativeEditor from '@cesdk/cesdk-js/vue';
	import {
		BlurAssetSource,
		CaptionPresetsAssetSource,
		CropPresetsAssetSource,
		EffectsAssetSource,
		FiltersAssetSource,
		PagePresetsAssetSource,
		StickerAssetSource,
		TextAssetSource,
		TextComponentAssetSource,
		TypefaceAssetSource,
		UploadAssetSources,
		VectorShapeAssetSource
	} from '@cesdk/cesdk-js/plugins';

	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useStoriesEditorStore } from '@M/store/stories/editor.store.js';
	import StoryMusicPicker from '@/kernel/vue/components/story/StoryMusicPicker.vue';

	const STORY_WIDTH = 720;
	const STORY_HEIGHT = 1280;
	const STORY_MAX_SECONDS = 60;

	function errorMessage(error) {
		return error?.response?.data?.message || error?.message || String(error || 'Unknown editor error.');
	}

	export default defineComponent({
		emits: ['fallback'],
		setup: function(props, { emit }) {
			const router = useRouter();
			const storiesEditorStore = useStoriesEditorStore();
			const instance = ref(null);
			const editorReady = ref(false);
			const editorError = ref('');
			const isBusy = ref(false);
			const busyLabel = ref('Preparing');
			const loadingLabel = ref('Opening editor');
			const exportProgress = ref(0);
			const musicBaked = ref(false);
			const state = reactive({
				isMusicPickerOpen: false
			});
			let musicPreview = null;
			let musicBlock = null;

				const draft = computed(() => storiesEditorStore.creativeDraft);
				const selectedMusicTrack = computed(() => storiesEditorStore.selectedMusicTrack);
				const mediaName = computed(() => draft.value?.name || 'Story media');
				const legacyFallbackEnabled = computed(() => storiesEditorStore.legacyEditorFallbackEnabled);
				const editorConfig = computed(() => {
					const license = storiesEditorStore.imglyLicenseKey;
					const assetBaseURL = String(import.meta.env.VITE_IMGLY_CESDK_ASSET_BASE_URL || '').trim();

					return {
						license: license,
						licenseKey: license,
						role: 'Creator',
						theme: 'dark',
						...(assetBaseURL ? { baseURL: assetBaseURL } : {}),
						ui: {
							smallViewportOptimization: true,
							elements: {
								view: 'default',
								panels: {
										settings: true,
										inspector: true
								},
								navigation: {
									action: {
										back: false,
										close: false,
										save: false,
										load: false,
										export: false
									}
								}
							}
						},
						callbacks: {
							onUnsupportedBrowser: () => {
								handleEditorError(new Error('This device does not support the creative editor.'));
							}
						}
					};
				});

			const stopMusicPreview = () => {
				if(musicPreview) {
					musicPreview.pause();
					musicPreview.src = '';
					musicPreview = null;
				}
			};

			const addMusicLayer = (playUrl) => {
				const cesdk = instance.value;
				const page = cesdk?.engine?.scene?.getCurrentPage?.();

				if(! cesdk || ! page || draft.value?.type !== 'video') {
					return false;
				}

				try {
					if(musicBlock) {
						cesdk.engine.block.destroy(musicBlock);
						musicBlock = null;
					}

					const audio = cesdk.engine.block.create('audio');
					cesdk.engine.block.setString(audio, 'audio/fileURI', playUrl);
					cesdk.engine.block.appendChild(page, audio);
					cesdk.engine.block.setVolume(audio, 1);

					const pageDuration = Math.min(
						STORY_MAX_SECONDS,
						Math.max(1, Number(cesdk.engine.block.getDuration(page) || STORY_MAX_SECONDS))
					);

					if(cesdk.engine.block.supportsDuration(audio)) {
						cesdk.engine.block.setDuration(audio, pageDuration);
					}

					cesdk.engine.block.getChildren(page).forEach((child) => {
						try {
							if(cesdk.engine.block.getType(child) === '//ly.img.ubq/graphic' && cesdk.engine.block.supportsPlaybackControl(child)) {
								cesdk.engine.block.setVolume(child, 0);
							}
						} catch (error) {
							console.warn('Unable to mute original story audio.', error);
						}
					});

					musicBlock = audio;
					musicBaked.value = true;
					storiesEditorStore.setCreativeMusicBaked(true);

					return true;
				} catch (error) {
					console.warn('Unable to add story music to CE.SDK scene.', error);
					return false;
				}
			};

			const previewMusic = async (trackData) => {
				stopMusicPreview();

				if(! trackData?.id) {
					return;
				}

				try {
					const response = await colibriAPI().storyMusic().getFrom(`tracks/${trackData.id}/play-url`);
					const playUrl = response.data.data.play_url;

					if(! playUrl) {
						return;
					}

						const musicWasAddedToVideo = addMusicLayer(playUrl);

						if(! musicWasAddedToVideo) {
							musicPreview = new Audio(playUrl);
							musicPreview.loop = true;
							musicPreview.volume = 0.85;
							musicPreview.play().catch(() => {});
						}
				} catch (error) {
					toastError(errorMessage(error));
				}
			};

			const addPlugins = async (cesdk) => {
				await cesdk.addPlugin(new BlurAssetSource());
				await cesdk.addPlugin(new CaptionPresetsAssetSource());
				await cesdk.addPlugin(new CropPresetsAssetSource());
				await cesdk.addPlugin(new EffectsAssetSource());
				await cesdk.addPlugin(new FiltersAssetSource());
				await cesdk.addPlugin(new PagePresetsAssetSource());
				await cesdk.addPlugin(new StickerAssetSource());
				await cesdk.addPlugin(new TextAssetSource());
				await cesdk.addPlugin(new TextComponentAssetSource());
				await cesdk.addPlugin(new TypefaceAssetSource());
				await cesdk.addPlugin(new UploadAssetSources({
					include: ['ly.img.image.upload', 'ly.img.video.upload']
				}));
				await cesdk.addPlugin(new VectorShapeAssetSource());
			};

			const normalizeStoryPage = (cesdk) => {
				const page = cesdk.engine.scene.getCurrentPage();

				if(! page) {
					throw new Error('The editor did not create a story page.');
				}

				cesdk.engine.block.setSize(page, STORY_WIDTH, STORY_HEIGHT);

				cesdk.engine.block.getChildren(page).forEach((child) => {
					try {
						if(cesdk.engine.block.getType(child) === '//ly.img.ubq/graphic') {
							cesdk.engine.block.setPosition(child, 0, 0);
							cesdk.engine.block.setSize(child, STORY_WIDTH, STORY_HEIGHT, {
								maintainCrop: true
							});
						}
					} catch (error) {
						console.warn('Unable to normalize story media bounds.', error);
					}
				});
			};

			const initEditor = async (cesdk) => {
				instance.value = cesdk;
				loadingLabel.value = 'Loading story media';

				if(! draft.value?.objectUrl) {
					throw new Error('The selected story media is no longer available.');
				}

				cesdk.ui.setTheme('dark');
				cesdk.i18n.setLocale('en');
				await addPlugins(cesdk);

				if(draft.value.type === 'video') {
					await cesdk.createFromVideo(draft.value.objectUrl);
					cesdk.engine.scene.setMode('Video');
				} else {
					await cesdk.createFromImage(draft.value.objectUrl);
					cesdk.engine.scene.setMode('Design');
				}

				normalizeStoryPage(cesdk);
				editorReady.value = true;
				loadingLabel.value = 'Ready';
			};

			const handleLoadingStateChange = (loadingState) => {
				if(typeof loadingState === 'string') {
					loadingLabel.value = loadingState;
				}
			};

			const handleEditorError = (error) => {
				const message = errorMessage(error);

				editorError.value = message;
				editorReady.value = false;
				console.error('Zulors CE.SDK error:', error);
			};

			const useLegacyEditor = () => {
				emit('fallback', new Error(editorError.value || 'Creative editor is unavailable.'));
			};

			const exportMedia = async () => {
				const cesdk = instance.value;
				const page = cesdk?.engine?.scene?.getCurrentPage?.();

				if(! cesdk || ! page) {
					throw new Error('The editor is not ready yet.');
				}

				if(draft.value.type === 'video') {
					if(! await cesdk.utils.supportsVideoEncode()) {
						throw new Error('This device cannot export videos in the browser.');
					}

					const duration = Math.min(
						STORY_MAX_SECONDS,
						Math.max(1, Number(cesdk.engine.block.getDuration(page) || STORY_MAX_SECONDS))
					);

					const blob = await cesdk.engine.block.exportVideo(page, {
						mimeType: 'video/mp4',
						videoBitrate: 'Auto',
						audioBitrate: 128000,
						framerate: 30,
						duration: duration,
						targetWidth: STORY_WIDTH,
						targetHeight: STORY_HEIGHT,
						onProgress: (rendered, encoded, total) => {
							exportProgress.value = total ? Math.min(100, Math.round((encoded / total) * 100)) : 0;
							busyLabel.value = `Exporting ${exportProgress.value}%`;
						}
					});

					return {
						blob: blob,
						mime: 'video/mp4',
						extension: 'mp4',
						width: STORY_WIDTH,
						height: STORY_HEIGHT,
						fps: 30,
						duration: duration
					};
				}

				const outputMime = draft.value.mime === 'image/png' ? 'image/png' : 'image/jpeg';
				const blob = await cesdk.engine.block.export(page, {
					mimeType: outputMime,
					targetWidth: STORY_WIDTH,
					targetHeight: STORY_HEIGHT,
					jpegQuality: 0.9,
					pngCompressionLevel: 7
				});

				return {
					blob: blob,
					mime: outputMime,
					extension: outputMime === 'image/png' ? 'png' : 'jpg',
					width: STORY_WIDTH,
					height: STORY_HEIGHT,
					fps: null,
					duration: null
				};
			};

			const saveAndNext = async () => {
				if(isBusy.value || ! editorReady.value) {
					return;
				}

				try {
					isBusy.value = true;
					exportProgress.value = 0;
					busyLabel.value = 'Exporting';

					const exported = await exportMedia();
					const filename = `zulors-story-${Date.now()}.${exported.extension}`;
					const file = new File([exported.blob], filename, {
						type: exported.mime,
						lastModified: Date.now()
					});

					busyLabel.value = 'Uploading';
					await storiesEditorStore.uploadCreativeExport(file, {
						editor_provider: 'imgly_cesdk',
						editor_export_format: exported.extension,
						editor_export_resolution: `${exported.width}x${exported.height}`,
						editor_export_width: exported.width,
						editor_export_height: exported.height,
						editor_export_fps: exported.fps || '',
						editor_export_source_mime: draft.value.mime
					});

					busyLabel.value = 'Publishing';
					const result = await storiesEditorStore.publishStory({
						musicBaked: musicBaked.value
					});
					toastSuccess(result?.queued ? 'Story upload started' : __t('toast.story.story_published'));
					storiesEditorStore.resetEditor();
					router.push({ name: 'home_index' });
				} catch (error) {
					if(/cannot export videos|browser/i.test(errorMessage(error))) {
						toastError('This device cannot export edited videos. No media was uploaded.');
						return;
					}

					toastError(errorMessage(error));
				} finally {
					isBusy.value = false;
					exportProgress.value = 0;
					busyLabel.value = 'Preparing';
				}
			};

			const leaveEditor = () => {
				if(isBusy.value) {
					return;
				}

				stopMusicPreview();
				storiesEditorStore.resetEditor();
				router.push({ name: 'home_index' });
			};

			const openMusicPicker = () => {
				if(! editorReady.value || isBusy.value) {
					return;
				}

				state.isMusicPickerOpen = true;
			};

			const openTextTools = () => {
				if(! editorReady.value || isBusy.value) {
					return;
				}

				try {
					instance.value.ui.openPanel('//ly.img.panel/inspector');
				} catch (error) {
					console.warn('Unable to open CE.SDK text tools.', error);
				}
			};

			const selectMusicTrack = (trackData) => {
				storiesEditorStore.setSelectedMusicTrack(trackData);
				state.isMusicPickerOpen = false;
				previewMusic(trackData);
			};

			onBeforeUnmount(() => {
				stopMusicPreview();

				if(instance.value) {
					try {
						if(musicBlock) {
							instance.value.engine.block.destroy(musicBlock);
							musicBlock = null;
						}
						instance.value.dispose();
					} catch (error) {
						console.warn('Unable to dispose CE.SDK instance.', error);
					}
				}
			});

			return {
				state: state,
				draft: draft,
				editorConfig: editorConfig,
				editorReady: editorReady,
				editorError: editorError,
				isBusy: isBusy,
				busyLabel: busyLabel,
				loadingLabel: loadingLabel,
				mediaName: mediaName,
				selectedMusicTrack: selectedMusicTrack,
				musicBaked: musicBaked,
				legacyFallbackEnabled: legacyFallbackEnabled,
				initEditor: initEditor,
				handleEditorError: handleEditorError,
				handleLoadingStateChange: handleLoadingStateChange,
				saveAndNext: saveAndNext,
				leaveEditor: leaveEditor,
				openMusicPicker: openMusicPicker,
				openTextTools: openTextTools,
				selectMusicTrack: selectMusicTrack,
				useLegacyEditor: useLegacyEditor
			};
		},
		components: {
			CreativeEditor: CreativeEditor,
			StoryMusicPicker: StoryMusicPicker
		}
	});
</script>

<style scoped>
	.story-creative-editor {
		display: flex;
		flex-direction: column;
		overflow: hidden;
		padding-top: var(--mobile-safe-top, 0px);
		padding-bottom: var(--mobile-safe-bottom, 0px);
	}

	.story-creative-editor__header {
		position: relative;
		z-index: 5;
		display: flex;
		align-items: center;
		gap: 0.5rem;
		min-height: 3.75rem;
		padding: 0.5rem 0.75rem;
		background: rgba(0, 0, 0, 0.78);
	}

	.story-creative-editor__canvas {
		position: relative;
		flex: 1 1 auto;
		min-height: 0;
	}

	.story-creative-editor__footer {
		position: relative;
		z-index: 5;
		display: flex;
		align-items: center;
		gap: 0.625rem;
		min-height: 3.25rem;
		padding: 0.5rem 0.75rem;
		background: rgba(0, 0, 0, 0.88);
	}

	.story-creative-editor__icon-button,
	.story-creative-editor__save-button,
	.story-creative-editor__tool-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 0.375rem;
		min-height: 2.75rem;
		border: 0;
		color: white;
	}

	.story-creative-editor__icon-button {
		width: 2.75rem;
		border-radius: 0.75rem;
		background: rgba(255, 255, 255, 0.08);
	}

	.story-creative-editor__save-button {
		min-width: 4.5rem;
		padding: 0 1rem;
		border-radius: 0.75rem;
		background: white;
		color: black;
		font-size: 0.875rem;
		font-weight: 700;
	}

	.story-creative-editor__save-button:disabled {
		cursor: wait;
		opacity: 0.5;
	}

	.story-creative-editor__tool-button {
			max-width: 12rem;
			padding: 0 0.75rem;
			border: 1px solid rgba(255, 255, 255, 0.16);
			border-radius: 0.75rem;
			background: rgba(255, 255, 255, 0.08);
			font-size: 0.75rem;
			font-weight: 600;
		}

		.story-creative-editor__tool-button:disabled {
			cursor: wait;
			opacity: 0.5;
		}

	.story-creative-editor__loading,
	.story-creative-editor__error {
		position: absolute;
		inset: 0;
		z-index: 4;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		background: #050505;
	}
</style>
