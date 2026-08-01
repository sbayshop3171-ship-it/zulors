<template>
	<div class="flex flex-col fixed inset-0 z-50 bg-bg-pr" v-bind:class="{ 'pb-5': $isStandalone() }">
		<div class="shrink-0">
				<Toolbar v-bind:title="editorTitle" v-on:close="leaveEditor"></Toolbar>
		</div>
		<Border height="h-2" opacity="opacity-60"></Border>
		<div class="flex-1 overflow-y-auto">
			<div class="flex flex-col h-full">
				<div class="flex-1">
					<textarea ref="contentInput"
							v-model="postData.content"
							v-on:input="textInputHandler"
							class="resize-none py-4 px-6 h-full w-full leading-normal bg-transparent text-title-3 text-lab-pr2 outline-hidden placeholder:font-light placeholder:text-title-3"
						v-bind:placeholder="postTextInputPlaceholder"></textarea>
				</div>
				<div class="shrink-0">
					<template v-if="postHasMedia">
						<template v-if="PostTypeUtils.isImage(currentPostType)">
								<PostImagePreview v-bind:canDelete="! isEditingPost" v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostImagePreview>
							</template>
							<template v-if="PostTypeUtils.isVideo(currentPostType)">
								<PostVideoPreview v-bind:canDelete="! isEditingPost" v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostVideoPreview>
							</template>
							<template v-else-if="PostTypeUtils.isDocument(currentPostType) || PostTypeUtils.isAudio(currentPostType)">
	                            <PostDocumentPreview v-bind:canDelete="! isEditingPost" v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostDocumentPreview>
	                        </template>

							<template v-else-if="PostTypeUtils.isGif(currentPostType)">
	                            <PostGifPreview v-bind:canDelete="! isEditingPost" v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostGifPreview>
	                        </template>
					</template>
				</div>
			</div>
		</div>
			<div v-if="state.uploadProgress && ! isEditingPost">
			<div class="flex">
				<div class="flex-1 bg-fill-tr h-1">
					<div class="bg-green-900 min-w-10 h-full" v-bind:style="{ width: state.uploadProgress + '%' }"></div>
				</div>
			</div>
		</div>
		<div class="shrink-0 px-6 pt-4 pb-4 mb-safe-bottom">
				<div class="flex gap-2 items-center mb-2 -translate-x-1.5">
					<template v-if="! isEditingPost">
					<PrimaryIconButton v-on:click="selectImage" v-bind:disabled="postMediaButtonStatus(PostType.IMAGE)" iconName="image-01" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>
					<PrimaryIconButton v-on:click="selectVideo" v-bind:disabled="postMediaButtonStatus(PostType.VIDEO)" iconName="video-recorder" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>
					<PrimaryIconButton v-on:click="selectAudio" v-bind:disabled="postMediaButtonStatus(PostType.AUDIO)" iconName="music-note-01" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>
					<PrimaryIconButton v-on:click="createPoll" v-bind:disabled="postMediaButtonStatus(PostType.POLL)" iconName="bar-chart-12" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>
					<PrimaryIconButton v-on:click="toggleGifPicker" v-bind:disabled="postMediaButtonStatus(PostType.GIF)" iconName="gif" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>
					</template>

					<div class="ml-auto opacity-80">
						<PrimaryIconButton v-bind:disabled="submitButtonStatus" v-on:click="submitForm" v-bind:iconName="submitButtonIcon" buttonColor="text-lab-pr2"></PrimaryIconButton>
					</div>
				</div>

			<p class="text-par-s text-red-900 mb-2" v-if="validationError">
				{{ validationError }}
			</p>

				<p v-if="! isEditingPost && userData.is_author" class="text-par-s text-lab-sc">
					{{ $t('editor.post_privacy') }}
				</p>
				<p v-else-if="! isEditingPost" class="text-par-s text-lab-sc">
					{{ $t('editor.post_author_note') }} <a v-bind:href="$getRoute('become_author')" class="hover:underline text-brand-900">{{ $t('labels.learn_more') }}</a>
				</p>
		</div>

		<div class="hidden">
			<input v-on:change="onImageSelect" type="file" accept="image/*" ref="imageFileInput">
			<input v-on:change="onVideoSelect" type="file" accept="video/*" ref="videoFileInput">
			<input v-on:change="onAudioSelect" type="file" accept="audio/*" ref="audioFileInput">
		</div>
	</div>


		<GIFPicker v-on:select="selectGif" v-if="state.isGifPickerOpen && ! isEditingPost" v-on:close="state.isGifPickerOpen = false"></GIFPicker>

		<PollEditor v-if="postHasPoll && ! isEditingPost" v-on:leave="leaveEditor"></PollEditor>
</template>

<script>
	import { defineComponent, reactive, ref, defineAsyncComponent, computed, onMounted, onBeforeUnmount } from 'vue';
	import { useRouter } from 'vue-router';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { applyVideoPresentationMetadata, readVideoFileMetadata } from '@/kernel/services/media/video-metadata.js';
	import { usePostEditorStore } from '@M/store/timeline/editor.store.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
		import { PostTypeUtils, PostType } from '@/kernel/enums/post/post.type.js';
		import { PostStatus } from '@/kernel/enums/post/post.status.js';
		import { colibriSounds } from '@/kernel/services/sounds/index.js';
		import { colibriEventBus } from '@/kernel/events/bus/index.js';
	    import { useTimelineStore } from '@M/store/timeline/timeline.store.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	const mobileBackgroundVideoUploads = new Set();

	const retainBackgroundVideoUpload = (uploadTask) => {
		if(! uploadTask || typeof uploadTask.then !== 'function') {
			return uploadTask;
		}

		mobileBackgroundVideoUploads.add(uploadTask);

		const releaseUploadTask = () => {
			mobileBackgroundVideoUploads.delete(uploadTask);
		}

		uploadTask.then(releaseUploadTask, releaseUploadTask);

		return uploadTask;
	}

	export default defineComponent({
		setup: function() {
			const postEditorStore = usePostEditorStore();
			const imageFileInput = ref(null);
			const videoFileInput = ref(null);
			const audioFileInput = ref(null);
			const contentInput = ref(null);
			const authStore = useAuthStore();
			const router = useRouter();
			const validationError = ref(null);
			const { autoResize } = useInputHandlers();
            const timelineStore = useTimelineStore();
			let editorIsActive = true;

			const postData = computed(() => {
                return postEditorStore.draftPost;
            });

			const userData = computed(() => {
				return authStore.userData;
			});

			const state = reactive({
				postSubmitting: false,
				uploadProgress: 0,
				directVideoUploadReady: false,
				directVideoUploadMedia: null,
				isGifPickerOpen: false,
				localMediaPreviews: [],
			});

			const canSubmitWhileVideoUploadContinues = computed(() => {
				return Boolean(state.directVideoUploadReady && state.uploadProgress && ! postEditorStore.isEditingPost);
			});

			const submitButtonStatus = computed(() => {
				return state.postSubmitting || (Boolean(state.uploadProgress) && ! canSubmitWhileVideoUploadContinues.value);
			});

			const validatePost = (message) => {
				// TODO: Add validation error to the editor store
				// Improve UX in future.

				validationError.value = message;

				debounce(() => {
					validationError.value = null;
				}, 3000);

				try {
					colibriSounds.uiFeedback();

					navigator.vibrate([150, 50, 150]);

				} catch (error) {
					//
				}
			}

			const getFormSubmitData = () => {
                let formData = {
                    content: postData.value.content
                };

                return formData;
            }

			onMounted(async function() {
                await postEditorStore.fetchDraftPost();
            });

			const clearLocalMediaPreviews = () => {
				state.localMediaPreviews.forEach((mediaItem) => {
					if (mediaItem.preview_url) {
						URL.revokeObjectURL(mediaItem.preview_url);
					}
				});

				state.localMediaPreviews = [];
			}

			const createLocalMediaPreview = (mediaFile, type = 'image') => {
				if (! ['image', 'video'].includes(type)) {
					return null;
				}

				const previewUrl = URL.createObjectURL(mediaFile);

				return {
					id: `local-${Date.now()}-${Math.random().toString(36).slice(2)}`,
					deleted: false,
					is_local_preview: true,
					type: type,
					source_url: previewUrl,
					preview_url: previewUrl,
					thumbnail_url: '',
					metadata: {
						duration: 0
					}
				};
			}

			const getFileExtension = (mediaFile) => {
				return (mediaFile.name?.split('.').pop() || '').toLowerCase();
			}

			const getUploadResponseData = (response) => {
				return response?.data?.data || {};
			}

			const normalizePartETag = (etag) => {
				return String(etag || '').trim();
			}

			const defaultDirectUploadStallTimeoutMs = 0;
			const defaultDirectUploadFirstProgressTimeoutMs = 0;
			const defaultRawFallbackMaxBytes = 8 * 1024 * 1024;

			const normalizeUploadProgress = (progress) => {
				return Math.max(0, Math.min(100, Math.round(Number(progress || 0))));
			}

			const syncUploadMedia = (uploadData, mediaData) => {
				if(! mediaData) {
					return;
				}

				uploadData.media = mediaData;
				state.directVideoUploadMedia = mediaData.type === PostType.VIDEO ? mediaData : state.directVideoUploadMedia;
				timelineStore.setPostMedia(mediaData);
				colibriEventBus.emit('timeline:media-updated', mediaData);
			}

			const syncUploadMediaProgress = (uploadData, progress) => {
				if(! uploadData?.media) {
					return;
				}

				const normalizedProgress = normalizeUploadProgress(progress);
				const mediaMetadata = uploadData.media.metadata || {};
				const uploadState = normalizedProgress > 0
					? (mediaMetadata.upload_state === 'uploaded' ? 'uploaded' : 'uploading')
					: (mediaMetadata.upload_state || 'waiting_for_upload');

				syncUploadMedia(uploadData, {
					...uploadData.media,
					metadata: {
						...mediaMetadata,
						upload_state: uploadState,
						upload_progress: normalizedProgress,
						upload_progress_updated_at: new Date().toISOString()
					}
				});
			}

			const uploadDirectRequest = (requestMethod, uploadUrl, uploadHeaders, payload, onProgress, options = {}) => {
				return new Promise((resolve, reject) => {
					const request = new XMLHttpRequest();
					const stallTimeoutMs = Number(options.stallTimeoutMs ?? defaultDirectUploadStallTimeoutMs);
					const firstProgressTimeoutMs = Number(options.firstProgressTimeoutMs ?? defaultDirectUploadFirstProgressTimeoutMs);
					const uploadTotalBytes = Math.max(0, Number(options.totalBytes || 0));
					let settled = false;
					let stallTimerId = null;
					let firstProgressTimerId = null;
					let hasUploadProgressStarted = false;

					const clearUploadTimers = () => {
						if(stallTimerId) {
							clearTimeout(stallTimerId);
							stallTimerId = null;
						}

						if(firstProgressTimerId) {
							clearTimeout(firstProgressTimerId);
							firstProgressTimerId = null;
						}
					}

					const failRequest = (error) => {
						if(settled) {
							return;
						}

						settled = true;
						clearUploadTimers();

						try {
							request.abort();
						}
						catch (abortError) {
							//
						}

						reject(error);
					}

					const finishRequest = (value) => {
						if(settled) {
							return;
						}

						settled = true;
						clearUploadTimers();
						resolve(value);
					}

					const resetStallTimer = () => {
						if(stallTimeoutMs < 1) {
							return;
						}

						if(stallTimerId) {
							clearTimeout(stallTimerId);
						}

						stallTimerId = setTimeout(() => {
							failRequest(new Error('Direct upload stalled. Retrying...'));
						}, stallTimeoutMs);
					}

					const startFirstProgressTimer = () => {
						if(firstProgressTimeoutMs < 1) {
							return;
						}

						firstProgressTimerId = setTimeout(() => {
							if(! hasUploadProgressStarted) {
								const error = new Error('Direct upload did not start. Retrying through app...');
								error.skipDirectUploadRetry = true;

								failRequest(error);
							}
						}, firstProgressTimeoutMs);
					}

					request.open(requestMethod, uploadUrl, true);

					if(options.requestTimeoutMs || options.timeoutMs) {
						request.timeout = options.requestTimeoutMs || options.timeoutMs;
					}

					Object.entries(uploadHeaders || {}).forEach(([header, value]) => {
						request.setRequestHeader(header, value);
					});

					request.upload.onprogress = (event) => {
						if(Number(event.loaded || 0) > 0) {
							hasUploadProgressStarted = true;

							if(firstProgressTimerId) {
								clearTimeout(firstProgressTimerId);
								firstProgressTimerId = null;
							}
						}

						resetStallTimer();

						const progressTotal = event.lengthComputable ? event.total : uploadTotalBytes;

						if(progressTotal > 0 && typeof onProgress === 'function') {
							onProgress(event.loaded, progressTotal);
						}
					};

					request.onload = () => {
						if(request.status >= 200 && request.status < 300) {
							if(uploadTotalBytes > 0 && typeof onProgress === 'function') {
								onProgress(uploadTotalBytes, uploadTotalBytes);
							}

							finishRequest({
								etag: normalizePartETag(request.getResponseHeader('ETag'))
							});
						}

						else {
							failRequest(new Error(`Direct upload failed with status ${request.status}`));
						}
					};

					request.onerror = () => {
						failRequest(new Error('Direct upload failed'));
					};

					request.ontimeout = () => {
						failRequest(new Error('Direct upload timed out'));
					};

					request.onabort = () => {
						failRequest(new Error('Direct upload was cancelled'));
					};

					resetStallTimer();
					startFirstProgressTimer();
					request.send(payload);
				});
			}

			const retryDirectUpload = async (callback, attempts = 3) => {
				let lastError = null;

				for(let attempt = 1; attempt <= attempts; attempt++) {
					try {
						return await callback(attempt);
					}
					catch (error) {
						lastError = error;

						if(error?.skipDirectUploadRetry) {
							break;
						}

						if(attempt < attempts) {
							await new Promise((resolve) => setTimeout(resolve, attempt * 1000));
						}
					}
				}

				throw lastError || new Error('Direct upload failed');
			}

			const createDirectUploadProgressReporter = (uploadData) => {
				let lastProgress = -1;
				let lastReportedAt = 0;
				let isReporting = false;
				let pendingProgress = null;

				const flushProgress = () => {
					if(isReporting || pendingProgress === null) {
						return;
					}

					const progressToReport = pendingProgress;
					pendingProgress = null;
					isReporting = true;

					colibriAPI().postEditor().with({
						media_id: uploadData.media.id,
						uid: uploadData.uid,
						upload_progress: progressToReport
					}).sendTo('media/video/direct/progress').then((response) => {
						syncUploadMedia(uploadData, getUploadResponseData(response).media);
					}).catch(() => {}).finally(() => {
						isReporting = false;
						flushProgress();
					});
				}

				return (progress, options = {}) => {
					if(! uploadData?.media?.id || ! uploadData?.uid) {
						return;
					}

					const normalizedProgress = normalizeUploadProgress(progress);
					const nowTimestamp = Date.now();

					syncUploadMediaProgress(uploadData, normalizedProgress);

					if(! options.force && normalizedProgress < 100 && (normalizedProgress - lastProgress) < 2 && (nowTimestamp - lastReportedAt) < 2000) {
						return;
					}

					lastProgress = normalizedProgress;
					lastReportedAt = nowTimestamp;
					pendingProgress = normalizedProgress;
					flushProgress();
				};
			}

			const uploadRawFileViaApp = (uploadData, mediaFile, onProgress) => {
				return colibriAPI().postEditor().with(mediaFile).params({
					media_id: uploadData.media?.id,
					uid: uploadData.uid,
					content_type: mediaFile.type || 'video/mp4'
				}).withHeaders({
					'Content-Type': 'application/octet-stream'
				}).uploadProgress((progressEvent) => {
					if(progressEvent.lengthComputable || progressEvent.total) {
						onProgress(progressEvent.loaded, progressEvent.total || mediaFile.size);
					}
				}).sendTo('media/video/direct/raw').then((response) => {
					return getUploadResponseData(response);
				});
			}

			const uploadFileToDirectUrl = async (uploadData, mediaFile, onProgress) => {
				try {
					return await retryDirectUpload(() => {
						const requestMethod = uploadData.upload_method || 'POST';
						const uploadType = uploadData.upload_type || 'form';

						if(uploadType === 'raw' || requestMethod === 'PUT') {
							return uploadDirectRequest(requestMethod, uploadData.upload_url, uploadData.upload_headers, mediaFile, (loaded, total) => {
								const uploadTotal = Math.max(1, total || mediaFile.size);
								onProgress(Math.round((loaded / uploadTotal) * 100));
							}, {
								requestTimeoutMs: 60 * 60 * 1000,
								totalBytes: mediaFile.size,
								stallTimeoutMs: defaultDirectUploadStallTimeoutMs,
								firstProgressTimeoutMs: defaultDirectUploadFirstProgressTimeoutMs
							});
						}

						const formData = new FormData();
						formData.append('file', mediaFile);

						return uploadDirectRequest(requestMethod, uploadData.upload_url, uploadData.upload_headers, formData, (loaded, total) => {
							const uploadTotal = Math.max(1, total || mediaFile.size);
							onProgress(Math.round((loaded / uploadTotal) * 100));
						}, {
							requestTimeoutMs: 60 * 60 * 1000,
							totalBytes: mediaFile.size,
							stallTimeoutMs: defaultDirectUploadStallTimeoutMs,
							firstProgressTimeoutMs: defaultDirectUploadFirstProgressTimeoutMs
						});
					}, 5);
				}
				catch (error) {
					const requestMethod = uploadData.upload_method || 'POST';
					const uploadType = uploadData.upload_type || 'form';
					const rawFallbackMaxBytes = Number(uploadData.raw_fallback_max_bytes || defaultRawFallbackMaxBytes);

					if((uploadType === 'raw' || requestMethod === 'PUT') && mediaFile.size <= rawFallbackMaxBytes) {
						return uploadRawFileViaApp(uploadData, mediaFile, (loaded, total) => {
							const uploadTotal = Math.max(1, total || mediaFile.size);
							onProgress(Math.round((loaded / uploadTotal) * 100));
						});
					}

					throw error;
				}
			}

			const uploadMultipartPartViaApp = (uploadData, part, partBlob, onProgress) => {
				return colibriAPI().postEditor().with(partBlob).params({
					media_id: uploadData.media?.id,
					uid: uploadData.uid,
					upload_id: uploadData.upload_id,
					part_number: part.part_number
				}).withHeaders({
					'Content-Type': 'application/octet-stream'
				}).uploadProgress((progressEvent) => {
					if(progressEvent.lengthComputable || progressEvent.total) {
						onProgress(progressEvent.loaded, progressEvent.total || partBlob.size);
					}
				}).sendTo('media/video/direct/part').then((response) => {
					return getUploadResponseData(response);
				});
			}

			const uploadMultipartFileToDirectUrl = async (uploadData, mediaFile, onProgress) => {
				const parts = Array.isArray(uploadData.parts) ? uploadData.parts : [];
				const completedParts = [];
				const loadedParts = new Map();
				const uploadConcurrency = Math.min(4, Math.max(1, Number(uploadData.upload_concurrency || 4)));
				const partFallbackMaxBytes = Math.max(0, Number(uploadData.part_fallback_max_bytes || 0));
				let uploadedBytes = 0;
				let nextPartIndex = 0;
				let shouldBypassDirectUpload = false;

				const updateMultipartProgress = (partNumber, loaded, total) => {
					loadedParts.set(partNumber, Math.min(Number(total || 0), Number(loaded || 0)));

					const activeUploadedBytes = Array.from(loadedParts.values()).reduce((totalBytes, partBytes) => {
						return totalBytes + partBytes;
					}, 0);

					const totalUploaded = Math.min(mediaFile.size, uploadedBytes + activeUploadedBytes);
					onProgress(Math.round((totalUploaded / mediaFile.size) * 100));
				}

				const uploadPart = async (part) => {
					const partStart = Number(part.start || 0);
					const partEnd = Number(part.end || Math.min(mediaFile.size, partStart + Number(uploadData.part_size || 0)));
					const partBlob = mediaFile.slice(partStart, partEnd);

					let result = null;

					if(! shouldBypassDirectUpload) {
						result = await retryDirectUpload(() => {
							return uploadDirectRequest(part.upload_method || 'PUT', part.upload_url, part.upload_headers || {}, partBlob, (loaded) => {
								updateMultipartProgress(part.part_number, loaded, partBlob.size);
							}, {
								requestTimeoutMs: 60 * 60 * 1000,
								totalBytes: partBlob.size,
								stallTimeoutMs: defaultDirectUploadStallTimeoutMs,
								firstProgressTimeoutMs: defaultDirectUploadFirstProgressTimeoutMs
							});
						}, 5).catch((error) => {
							if(error?.skipDirectUploadRetry) {
								shouldBypassDirectUpload = true;
							}

							return null;
						});
					}

					if(! result && partFallbackMaxBytes > 0 && partBlob.size <= partFallbackMaxBytes) {
						loadedParts.set(part.part_number, 0);

						result = await retryDirectUpload(() => {
							return uploadMultipartPartViaApp(uploadData, part, partBlob, (loaded) => {
								updateMultipartProgress(part.part_number, loaded, partBlob.size);
							});
						}, 2);
					}

					if(result) {
						updateMultipartProgress(part.part_number, partBlob.size, partBlob.size);
					}
					else {
						// Let the server verify R2 parts before failing. Browsers can lose
						// the final upload response even after R2 has accepted the chunk.
						updateMultipartProgress(part.part_number, partBlob.size, partBlob.size);
					}

					uploadedBytes += partBlob.size;
					loadedParts.delete(part.part_number);

					completedParts.push({
						part_number: part.part_number,
						etag: result?.etag || ''
					});

					onProgress(Math.round((uploadedBytes / mediaFile.size) * 100));
				}

				const workers = Array.from({
					length: Math.min(uploadConcurrency, parts.length)
				}, async () => {
					while(nextPartIndex < parts.length) {
						const part = parts[nextPartIndex++];
						await uploadPart(part);
					}
				});

				await Promise.all(workers);

				return completedParts.sort((firstPart, secondPart) => {
					return firstPart.part_number - secondPart.part_number;
				});
			}

			const uploadMediaLocally = (mediaFile, type = 'image', shouldCreatePreview = true) => {
				if(! mediaFile) {
					return false;
				}

				const localPreview = shouldCreatePreview ? createLocalMediaPreview(mediaFile, type) : null;

					if (localPreview) {
						clearLocalMediaPreviews();
						state.localMediaPreviews.push(localPreview);

						if(type === 'video') {
							readVideoFileMetadata(mediaFile).then((metadata) => {
								applyVideoPresentationMetadata(localPreview, metadata);
							});
						}
					}

                const formData = new FormData();
                formData.append(type, mediaFile);

                return colibriAPI().postEditor().with(formData).withHeaders({
                    'Content-Type': 'multipart/form-data'
                }).uploadProgress((progressEvent) => {
                    state.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
                }).sendTo(`media/${type}/upload`).then(async (response) => {

                    await postEditorStore.fetchDraftPost({
						preserveContent: true
					});

					clearLocalMediaPreviews();

                    state.uploadProgress = 0;

                    resetFileInputTags();

                }).catch((error) => {
					clearLocalMediaPreviews();

                    validatePost(error.response?.data?.message || error.message || 'Upload failed');

                    state.uploadProgress = 0;

                    resetFileInputTags();
                });
            }

			const refreshDraftAfterActiveUpload = async () => {
				if(! editorIsActive) {
					return;
				}

				await postEditorStore.fetchDraftPost({
					preserveContent: true
				});

				clearLocalMediaPreviews();
			}

			const uploadVideoDirectly = async (mediaFile) => {
				if(! mediaFile) {
					return false;
				}

				state.directVideoUploadReady = false;
				postEditorStore.setVideoUploadActive(true);

				const localPreview = createLocalMediaPreview(mediaFile, 'video');
				let uploadData = null;
				let presentationMetadata = {};

				if(localPreview) {
					clearLocalMediaPreviews();
					state.localMediaPreviews.push(localPreview);
				}

				try {
					state.uploadProgress = 5;
					presentationMetadata = await readVideoFileMetadata(mediaFile);

					if(localPreview) {
						applyVideoPresentationMetadata(localPreview, presentationMetadata);
					}

					const response = await colibriAPI().postEditor().with({
						name: mediaFile.name || 'video',
						size: mediaFile.size,
						mime: mediaFile.type || 'video/mp4',
						extension: getFileExtension(mediaFile),
						width: presentationMetadata.dimensions?.width || null,
						height: presentationMetadata.dimensions?.height || null,
						duration_seconds: presentationMetadata.duration_seconds || null
					}).sendTo('media/video/direct/create');

					uploadData = getUploadResponseData(response);
					const isMultipartUpload = uploadData.upload_type === 'multipart' && Array.isArray(uploadData.parts);

					if(! uploadData.direct_upload || (! uploadData.upload_url && ! isMultipartUpload)) {
						state.uploadProgress = 0;
						state.directVideoUploadReady = false;

						return await uploadMediaLocally(mediaFile, 'video', false);
					}

					syncUploadMedia(uploadData, uploadData.media);

					const reportUploadProgress = createDirectUploadProgressReporter(uploadData);
					reportUploadProgress(0, {
						force: true
					});

					let completedParts = [];

					if(isMultipartUpload) {
						completedParts = await uploadMultipartFileToDirectUrl(uploadData, mediaFile, (progress) => {
							state.uploadProgress = normalizeUploadProgress(progress);
							reportUploadProgress(progress);
						});
					}

					else {
						await uploadFileToDirectUrl(uploadData, mediaFile, (progress) => {
							state.uploadProgress = normalizeUploadProgress(progress);
							reportUploadProgress(progress);
						});
					}

					state.uploadProgress = 100;
					reportUploadProgress(100, {
						force: true
					});

					const completionData = {
						media_id: uploadData.media?.id,
						uid: uploadData.uid,
						upload_id: uploadData.upload_id,
						parts: completedParts || []
					};

					let completionError = null;
					let completionResponse = null;

					for(let attempt = 1; attempt <= 5; attempt++) {
						try {
							completionResponse = await colibriAPI().postEditor().with(completionData).sendTo('media/video/direct/complete');
							completionError = null;
							break;
						}
						catch(error) {
							completionError = error;

							if(attempt < 5) {
								await new Promise((resolve) => setTimeout(resolve, attempt * 1000));
							}
						}
					}

					if(completionError) {
						throw completionError;
					}

					state.uploadProgress = 100;
					state.directVideoUploadReady = false;
					syncUploadMedia(uploadData, getUploadResponseData(completionResponse).media);

					await refreshDraftAfterActiveUpload();
				}

				catch (error) {
					state.directVideoUploadReady = false;

					validatePost(error.response?.data?.message || error.message || 'Video upload could not finish. Please retry.');
				}

				finally {
					postEditorStore.setVideoUploadActive(false);
					state.uploadProgress = 0;

					resetFileInputTags();
				}
			}

			const uploadMedia = (mediaFile, type = 'image') => {
				if(type === 'video') {
					return uploadVideoDirectly(mediaFile);
				}

				return uploadMediaLocally(mediaFile, type);
			}

			onBeforeUnmount(() => {
				editorIsActive = false;
				clearLocalMediaPreviews();
			});

			const formattedZeroCount = () => {
				return {
					raw: 0,
					formatted: '0'
				};
			}

			const getOptimisticPostMedia = () => {
				const draftMedia = Array.isArray(postData.value.relations?.media) ? postData.value.relations.media : [];

				if(draftMedia.length) {
					return draftMedia;
				}

				return state.directVideoUploadMedia ? [state.directVideoUploadMedia] : [];
			}

			const buildOptimisticPost = (clientId) => {
				const mediaItems = getOptimisticPostMedia();
				const postType = mediaItems.length ? (postData.value.type || mediaItems[0].type) : (postData.value.type || PostType.TEXT);
				const nowIso = new Date().toISOString();

				return {
					id: postData.value.id || state.directVideoUploadMedia?.mediaable_id || clientId,
					content: postData.value.content || '',
					type: postType,
					status: PostTypeUtils.isVideo(postType)
						? (mediaItems.some((mediaItem) =>
							mediaItem.status === 'processed' ||
							mediaItem.metadata?.upload_state === 'uploaded'
						) ? PostStatus.ACTIVE : PostStatus.PROCESSING_VIDEO)
						: PostStatus.ACTIVE,
					text_language: postData.value.text_language || '',
					hash_id: postData.value.hash_id || `local-${clientId}`,
					relations: {
						user: {
							id: userData.value.id,
							name: userData.value.name,
							avatar_url: userData.value.avatar_url,
							is_auth_user: true,
							username: userData.value.username,
							caption: userData.value.caption || `@${userData.value.username}`,
							verified: userData.value.verified || false
						},
						reactions: [],
						comments: [],
						media: mediaItems,
						poll: postData.value.relations?.poll || null,
						link_snapshot: postData.value.relations?.link_snapshot || null,
						quoted_post: postData.value.relations?.quoted_post || null
					},
					views_count: formattedZeroCount(),
					comments_count: formattedZeroCount(),
					bookmarks_count: formattedZeroCount(),
					shares_count: formattedZeroCount(),
					date: {
						iso: nowIso,
						time_ago: 'now',
						timestamp: Math.floor(Date.now() / 1000)
					},
					meta: {
						client_id: clientId,
						is_optimistic: true,
						permissions: {
							can_like: false,
							can_comment: false,
							can_edit: false,
							can_delete: false,
							can_report: false
						},
						activity: {
							bookmarked: false
						},
						is_translatable: false,
						is_quoting: Boolean(postData.value.relations?.quoted_post),
						is_sensitive: false,
						is_edited: false,
						is_ai_generated: false
					}
				};
			}

			const navigateBack = () => {
				router.go(-1);
			}

			const publishNewPostInstantly = (submitData) => {
				const clientId = `mobile-post-${Date.now()}-${Math.random().toString(36).slice(2)}`;
				const optimisticPost = buildOptimisticPost(clientId);

				timelineStore.prependOptimisticPost(optimisticPost);
				colibriEventBus.emit('timeline:post-updated', optimisticPost);

				postEditorStore.finishEditing();
				navigateBack();

				colibriAPI().postEditor().with(submitData).sendTo('create').then((response) => {
					timelineStore.replaceOptimisticPost(clientId, response.data.data);
					colibriEventBus.emit('timeline:post-updated', response.data.data);
					toastSuccess(__t('toast.post_published'));
				}).catch((error) => {
					timelineStore.removeOptimisticPost(clientId);
					toastError(error.response?.data?.message || error.message);
				}).finally(() => {
					state.postSubmitting = false;
				});
			}

			const updatePostInstantly = (submitData) => {
				const originalPost = JSON.parse(JSON.stringify(postData.value));
				const optimisticPost = {
					...postData.value,
					content: submitData.content,
					meta: {
						...(postData.value.meta || {}),
						is_edited: true
					}
				};

				timelineStore.updatePost(optimisticPost);
				colibriEventBus.emit('timeline:post-updated', optimisticPost);

				postEditorStore.finishEditing();
				navigateBack();

				colibriAPI().userTimeline().with(submitData).putTo('post/update').then((response) => {
					timelineStore.updatePost(response.data.data);
					colibriEventBus.emit('timeline:post-updated', response.data.data);
					toastSuccess(__t('toast.post.updated'));
				}).catch((error) => {
					timelineStore.updatePost(originalPost);
					colibriEventBus.emit('timeline:post-updated', originalPost);
					toastError(error.response?.data?.message || error.message);
				}).finally(() => {
					state.postSubmitting = false;
				});
			}

			const submitForm = () => {
				if(state.postSubmitting) {
					return;
				}

				if(state.uploadProgress && ! canSubmitWhileVideoUploadContinues.value) {
					validatePost('Please wait until the video upload reaches 100%.');
					return;
				}

				state.postSubmitting = true;

				const submitData = postEditorStore.isEditingPost ? {
					id: postData.value.id,
					content: postData.value.content
				} : getFormSubmitData();

				if(postEditorStore.isEditingPost) {
					updatePostInstantly(submitData);
				}
				else {
					publishNewPostInstantly(submitData);
				}
			}

			const resetFileInputTags = () => {
				if(imageFileInput.value) {
					imageFileInput.value.value = '';
				}

				if(videoFileInput.value) {
					videoFileInput.value.value = '';
				}

				if(audioFileInput.value) {
					audioFileInput.value.value = '';
				}
            }

			const leaveEditor = () => {
				if(postEditorStore.videoUploadActive) {
					validatePost('Please wait until the video upload finishes.');
					return;
				}

				if(postEditorStore.isEditingPost) {
					postEditorStore.finishEditing();
				}

				navigateBack();
			}

			return {
				leaveEditor: leaveEditor,
				state: state,
				PostTypeUtils: PostTypeUtils,
				PostType: PostType,
				userData: userData,
					submitForm: submitForm,
					validationError: validationError,
					postData: postData,
					isEditingPost: computed(() => {
						return postEditorStore.isEditingPost;
					}),
					editorTitle: computed(() => {
						return postEditorStore.isEditingPost ? __t('editor.edit_post') : __t('editor.new_post');
					}),
						submitButtonIcon: computed(() => {
							return postEditorStore.isEditingPost ? 'check-circle' : 'send-03';
						}),
						postTextInputPlaceholder: computed(() => {
							return postEditorStore.isEditingPost ? __t('editor.edit_post_text_input_placeholder') : __t('editor.post_text_input_placeholder');
						}),
						currentPostType: computed(() => {
						if (state.localMediaPreviews.length) {
							return state.localMediaPreviews[0].type;
					}

					return postData.value.type;
				}),
				contentInput: contentInput,
				videoFileInput: videoFileInput,
				imageFileInput: imageFileInput,
				audioFileInput: audioFileInput,
				textInputHandler: function() {
					autoResize(contentInput.value);
				},
				onImageSelect: function(event) {
					uploadMedia(event.target.files[0], 'image');
				},
				onAudioSelect: function(event) {
					uploadMedia(event.target.files[0], 'audio');
				},
				onVideoSelect: function(event) {
					retainBackgroundVideoUpload(uploadMedia(event.target.files[0], 'video'));
				},
				selectImage: function() {
					imageFileInput.value.click();
				},
				selectAudio: function() {
					audioFileInput.value.click();
				},
				selectVideo: function() {
					videoFileInput.value.click();
				},
				postHasMedia: computed(() => {
                    return state.localMediaPreviews.length || postData.value.relations?.media?.length;
                }),
				postHasPoll: computed(() => {
                    return postData.value.relations?.poll;
                }),
				postMedia: computed(() => {
					const serverMedia = postData.value.relations?.media || [];

                    return serverMedia.concat(state.localMediaPreviews);
                }),
				selectGif: (gifItem) => {
					colibriAPI().postEditor().with({
						id: gifItem.id
					}).sendTo('gif/create').then((response) => {
						postEditorStore.fetchDraftPost({
							preserveContent: true
						});
					}).catch((error) => {
						validatePost(error.response.data.message);
					});

					state.isGifPickerOpen = false;
				},
					createPoll: () => {
						if(postEditorStore.isEditingPost) {
							return false;
						}

						colibriAPI().postEditor().sendTo('poll/create').then((response) => {
							postEditorStore.fetchDraftPost({
							preserveContent: true
						});
					}).catch((error) => {
						validatePost(error.response.data.message);
					});
				},
					submitButtonStatus: submitButtonStatus,
					postMediaButtonStatus: (postType = null) => {
	                    // Disable media button if post is being submitted
	                    if (state.postSubmitting || state.uploadProgress || postEditorStore.isEditingPost) {
	                        return true;
	                    }
                    else {
						const editorPostType = state.localMediaPreviews.length ? state.localMediaPreviews[0].type : postData.value.type;

                        // For text posts, media button is always enabled
                        if(PostTypeUtils.isText(editorPostType)) {
                            return false;
                        }
                        else {
                            // For image posts, enable media button only if both
							// current and target types are images

                            if (PostTypeUtils.isImage(editorPostType) && PostTypeUtils.isImage(postType)) {
                                return false;
                            }

                            // Otherwise disable if post type is set
                            return !!editorPostType;
                        }
                    }
                },
					deletePostMedia: (mediaItem) => {
						if(postEditorStore.isEditingPost) {
							return false;
						}

						if (mediaItem.is_local_preview) {
							clearLocalMediaPreviews();
						return;
					}

                    mediaItem.deleted = true;

                    colibriAPI().postEditor().with({
                        id: mediaItem.id
                    }).delete('media/delete').then((response) => {
                        postEditorStore.fetchDraftPost({
							preserveContent: true
						});
                    });
                },
				toggleGifPicker: () => {
					state.isGifPickerOpen = !state.isGifPickerOpen;
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			PrimaryIconButton: PrimaryIconButton,
			PostImagePreview: defineAsyncComponent(() => {
				return import('@M/views/editors/post/parts/preview/PostImagePreview.vue');
			}),
			PostVideoPreview: defineAsyncComponent(() => {
				return import('@M/views/editors/post/parts/preview/PostVideoPreview.vue');
			}),
			PostDocumentPreview: defineAsyncComponent(() => {
				return import('@M/views/editors/post/parts/preview/PostDocumentPreview.vue');
			}),
			PostGifPreview: defineAsyncComponent(() => {
				return import('@M/views/editors/post/parts/preview/PostGifPreview.vue');
			}),
			GIFPicker: defineAsyncComponent(() => {
                return import('@M/components/gif/GIFPicker.vue');
            }),
			PollEditor: defineAsyncComponent(() => {
                return import('@M/views/editors/post/parts/poll/PollEditor.vue');
            }),
		}
	});
</script>
