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
	import { usePostEditorStore } from '@M/store/timeline/editor.store.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
		import { PostTypeUtils, PostType } from '@/kernel/enums/post/post.type.js';
		import { colibriSounds } from '@/kernel/services/sounds/index.js';
		import { colibriEventBus } from '@/kernel/events/bus/index.js';
	    import { useTimelineStore } from '@M/store/timeline/timeline.store.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

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

			const postData = computed(() => {
                return postEditorStore.draftPost;
            });

			const userData = computed(() => {
				return authStore.userData;
			});

			const state = reactive({
				postSubmitting: false,
				uploadProgress: 0,
				isGifPickerOpen: false,
				localMediaPreviews: [],
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
				return response.data?.data || {};
			}

			const normalizePartETag = (etag) => {
				return String(etag || '').trim();
			}

			const uploadDirectRequest = (requestMethod, uploadUrl, uploadHeaders, payload, onProgress) => {
				return new Promise((resolve, reject) => {
					const request = new XMLHttpRequest();
					request.open(requestMethod, uploadUrl, true);

					Object.entries(uploadHeaders || {}).forEach(([header, value]) => {
						request.setRequestHeader(header, value);
					});

					request.upload.onprogress = (event) => {
						if(event.lengthComputable && typeof onProgress === 'function') {
							onProgress(event.loaded, event.total);
						}
					};

					request.onload = () => {
						if(request.status >= 200 && request.status < 300) {
							resolve({
								etag: normalizePartETag(request.getResponseHeader('ETag'))
							});
						}

						else {
							reject(new Error(`Direct upload failed with status ${request.status}`));
						}
					};

					request.onerror = () => {
						reject(new Error('Direct upload failed'));
					};

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

						if(attempt < attempts) {
							await new Promise((resolve) => setTimeout(resolve, attempt * 1000));
						}
					}
				}

				throw lastError || new Error('Direct upload failed');
			}

			const uploadRawFileViaApp = (uploadData, mediaFile, onProgress) => {
				const formData = new FormData();
				formData.append('media_id', uploadData.media?.id);
				formData.append('uid', uploadData.uid);
				formData.append('video', mediaFile);

				return colibriAPI().postEditor().with(formData).withHeaders({
					'Content-Type': 'multipart/form-data'
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
							});
						}

						const formData = new FormData();
						formData.append('file', mediaFile);

						return uploadDirectRequest(requestMethod, uploadData.upload_url, uploadData.upload_headers, formData, (loaded, total) => {
							const uploadTotal = Math.max(1, total || mediaFile.size);
							onProgress(Math.round((loaded / uploadTotal) * 100));
						});
					});
				}
				catch (error) {
					const requestMethod = uploadData.upload_method || 'POST';
					const uploadType = uploadData.upload_type || 'form';

					if(uploadType === 'raw' || requestMethod === 'PUT') {
						return uploadRawFileViaApp(uploadData, mediaFile, (loaded, total) => {
							const uploadTotal = Math.max(1, total || mediaFile.size);
							onProgress(Math.round((loaded / uploadTotal) * 100));
						});
					}

					throw error;
				}
			}

			const uploadMultipartPartViaApp = (uploadData, part, partBlob, onProgress) => {
				const formData = new FormData();
				formData.append('media_id', uploadData.media?.id);
				formData.append('uid', uploadData.uid);
				formData.append('upload_id', uploadData.upload_id);
				formData.append('part_number', part.part_number);
				formData.append('part', partBlob, `part-${part.part_number}`);

				return colibriAPI().postEditor().with(formData).withHeaders({
					'Content-Type': 'multipart/form-data'
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
				const uploadConcurrency = Math.min(3, Math.max(1, Number(uploadData.upload_concurrency || 3)));
				let uploadedBytes = 0;
				let nextPartIndex = 0;

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

					let result = await retryDirectUpload(() => {
						return uploadDirectRequest(part.upload_method || 'PUT', part.upload_url, part.upload_headers || {}, partBlob, (loaded) => {
							updateMultipartProgress(part.part_number, loaded, partBlob.size);
						});
					}, 2).catch(() => null);

					if(! result?.etag) {
						loadedParts.set(part.part_number, 0);

						result = await uploadMultipartPartViaApp(uploadData, part, partBlob, (loaded) => {
							updateMultipartProgress(part.part_number, loaded, partBlob.size);
						});
					}

					if(! result?.etag) {
						throw new Error('Direct upload did not return an ETag.');
					}

					uploadedBytes += partBlob.size;
					loadedParts.delete(part.part_number);

					completedParts.push({
						part_number: part.part_number,
						etag: result.etag
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

			const uploadVideoDirectly = async (mediaFile) => {
				if(! mediaFile) {
					return false;
				}

				const localPreview = createLocalMediaPreview(mediaFile, 'video');

				if(localPreview) {
					clearLocalMediaPreviews();
					state.localMediaPreviews.push(localPreview);
				}

				try {
					state.uploadProgress = 5;

						const response = await colibriAPI().postEditor().with({
							name: mediaFile.name || 'video',
							size: mediaFile.size,
							mime: mediaFile.type || 'video/mp4',
							extension: getFileExtension(mediaFile)
						}).sendTo('media/video/direct/create');

						const uploadData = getUploadResponseData(response);
						const isMultipartUpload = uploadData.upload_type === 'multipart' && Array.isArray(uploadData.parts);

						if(! uploadData.direct_upload || (! uploadData.upload_url && ! isMultipartUpload)) {
							state.uploadProgress = 0;

							return await uploadMediaLocally(mediaFile, 'video', false);
						}

						let completedParts = [];

						if(isMultipartUpload) {
							completedParts = await uploadMultipartFileToDirectUrl(uploadData, mediaFile, (progress) => {
								state.uploadProgress = Math.min(90, Math.max(10, Math.round(10 + (progress * 0.8))));
							});
						}

						else {
							await uploadFileToDirectUrl(uploadData, mediaFile, (progress) => {
								state.uploadProgress = Math.min(90, Math.max(10, Math.round(10 + (progress * 0.8))));
							});
						}

						state.uploadProgress = 95;

						await colibriAPI().postEditor().with({
							media_id: uploadData.media?.id,
							uid: uploadData.uid,
							upload_id: uploadData.upload_id,
							parts: completedParts
						}).sendTo('media/video/direct/complete');

					await postEditorStore.fetchDraftPost({
						preserveContent: true
					});

					clearLocalMediaPreviews();
				}

				catch (error) {
					clearLocalMediaPreviews();

					validatePost(error.response?.data?.message || error.message || 'Upload failed');
				}

				finally {
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
				clearLocalMediaPreviews();
			});

				const submitForm = async () => {
	                state.postSubmitting = true;

	                const endpoint = postEditorStore.isEditingPost ? 'post/update' : 'create';
	                const apiClient = postEditorStore.isEditingPost ? colibriAPI().userTimeline() : colibriAPI().postEditor();
	                const submitData = postEditorStore.isEditingPost ? {
	                    id: postData.value.id,
	                    content: postData.value.content
	                } : getFormSubmitData();

	                await apiClient.with(submitData)[postEditorStore.isEditingPost ? 'putTo' : 'sendTo'](endpoint).then((response) => {
						if(postEditorStore.isEditingPost) {
							timelineStore.updatePost(response.data.data);
							colibriEventBus.emit('timeline:post-updated', response.data.data);
							toastSuccess(__t('toast.post.updated'));
						}
						else {
							timelineStore.prependPost(response.data.data);
							toastSuccess(__t('toast.post_published'));
						}

						postEditorStore.finishEditing();
	
	                    autoResize(contentInput.value);
	
						leaveEditor();
	                }).catch((error) => {
	                    validatePost(error.response?.data?.message || error.message);
	                });

                state.postSubmitting = false;
            }

			const resetFileInputTags = () => {
                imageFileInput.value.value = '';
				videoFileInput.value.value = '';
				audioFileInput.value.value = '';
            }

				const leaveEditor = () => {
					if(postEditorStore.isEditingPost) {
						postEditorStore.finishEditing();
					}

					router.go(-1);
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
					uploadMedia(event.target.files[0], 'video');
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
				submitButtonStatus: computed(() => {
					return state.postSubmitting || state.uploadProgress;
				}),
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
