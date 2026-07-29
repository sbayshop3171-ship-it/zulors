<template>
    <div class="block leading-none"
        v-on:drop.prevent="handleFileDrop"
		v-on:dragenter.prevent="state.isDragging = true"
		v-on:dragover.prevent="state.isDragging = true">
        <form class="block" v-on:submit.prevent="submitForm">
            <div class="block px-5 pb-3 pt-6">
                <div class="flex gap-2.5">
                    <div class="shrink-0">
                        <AvatarSmall v-bind:avatarSrc="userData.avatar_url"></AvatarSmall>
                    </div>
                    <div class="flex-1 pb-2">
                        <div class="mb-1">
                            <h4 class="text-par-m font-semibold text-lab-pr2">
                                {{ userData.name }}
                            </h4>
                        </div>
                        <textarea
                            v-on:paste="onMediaPaste"
                            v-on:input="textInputHandler"
                            ref="postTextInputField"
                            v-model="postData.content"
                            class="resize-none w-full min-h-[80px] leading-5 bg-transparent text-par-l text-lab-pr2 pr-4 outline-hidden placeholder:font-light placeholder:text-par-l pt-0.5"
                        v-bind:placeholder="postTextInputPlaceholder"></textarea>

                        <template v-if="state.emojisMenu.status">
                            <div class="relative">
                                <div class="absolute top-full left-0 w-80 z-50">
                                    <EmojisPicker
                                        v-on:pick="insertPostEmoji"
                                    v-on:close="state.emojisMenu.close"></EmojisPicker>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="shrink-0 opacity-90">
                        <EmojisPickerButton v-on:click.stop="state.emojisMenu.open"></EmojisPickerButton>
                    </div>
                </div>
                <div v-if="isAiGeneratedPost" class="block mb-3">
                    <div class="text-cap-s text-lab-sc font-medium">
                        {{ $t('labels.ai_generated') }}
                    </div>
                </div>
                <MentionsPicker
                    v-on:select="selectMention"
                classes="absolute top-0 left-0 w-80 z-50 border border-bord-pr rounded-lg popup-background-tr"></MentionsPicker>
                <template v-if="state.isDragging">
                    <MediaFileDropper v-on:click="state.isDragging = false"></MediaFileDropper>
                </template>
                <template v-else>
                    <div v-if="postHasMedia" class="block mb-3">
                        <template v-if="PostTypeUtils.isImage(currentPostType)">
                            <div class="overflow-hidden">
                                <div class="grid grid-cols-3 gap-1">
                                    <div v-for="mediaItem in postMedia" v-bind:key="mediaItem.id" class="relative rounded-md overflow-hidden border border-bord-card">
                                        <MediaBlurOverlay v-if="mediaItem.deleted"></MediaBlurOverlay>

                                        <div v-else-if="! isEditingPost" class="absolute top-2 right-2 inline-block">
                                            <MediaDeleteButton v-on:click="deletePostMedia(mediaItem)"></MediaDeleteButton>
                                        </div>

                                        <img v-bind:src="mediaItem.source_url" class="w-full aspect-square h-full object-cover bg-fill-tr" alt="Image">
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-else-if="PostTypeUtils.isVideo(currentPostType)">
                            <PostVideoPreview
                                v-for="mediaItem in postMedia" v-bind:key="mediaItem.id"
                                v-bind:mediaItem="mediaItem"
                                v-bind:canDelete="! isEditingPost"
                            v-on:delete="deletePostMedia"></PostVideoPreview>
                        </template>
                        <template v-else-if="PostTypeUtils.isGif(currentPostType)">
                            <PostGifPreview v-bind:canDelete="! isEditingPost" v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostGifPreview>
                        </template>
                        <template v-else-if="PostTypeUtils.isDocument(currentPostType) || PostTypeUtils.isAudio(currentPostType)">
                            <PostDocumentPreview v-bind:canDelete="! isEditingPost" v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostDocumentPreview>
                        </template>
                    </div>
                    <div v-else-if="postHasPoll && ! isEditingPost" class="block mb-3">
                        <PostPollForm v-on:remove="deletePostPoll"></PostPollForm>
                    </div>
                    <div v-else-if="postHasLinkSnapshot" class="block mb-3">
                        <PostLinkSnapshotPreview
                            v-bind:canDelete="! isEditingPost"
                            v-bind:key="postLinkSnapshot.id"
                            v-on:delete="deletePostLinkSnapshot"
                        v-bind:linkSnapshot="postLinkSnapshot"></PostLinkSnapshotPreview>
                    </div>
                    <div v-if="postHasQuotedPost" class="block mb-3">
                        <PublicationQuote v-bind:quotedPost="quotedPost" v-bind:key="quotedPost.id"></PublicationQuote>
                    </div>
                </template>
            </div>
            <div class="pb-4 px-5 pt-3">
                <div class="flex items-center mb-5 gap-2.5">
                    <template v-if="! isEditingPost">
                        <div class="shrink-0 inline-flex relative">
                            <MediaCreateButton v-on:click.stop="state.mainMenu.open" iconName="plus" iconType="solid"></MediaCreateButton>

                            <div class="absolute w-96 top-full left-0 z-50" v-if="state.mainMenu.status">
                                <RichMenu v-outside-click="state.mainMenu.close" v-on:click="state.mainMenu.close">
                                    <RichMenuItem
                                        v-on:click="selectImage"
                                        v-bind:disabled="postMediaButtonStatus(PostType.IMAGE)"
                                        iconName="image-05"
                                        v-bind:title="$t('editor.main_menu.image.title')"
                                    v-bind:description="$t('editor.main_menu.image.description')"></RichMenuItem>
                                    <RichMenuItem
                                        v-on:click="selectVideo"
                                        v-bind:disabled="postMediaButtonStatus(PostType.VIDEO)"
                                        iconName="video-recorder"
                                        v-bind:title="$t('editor.main_menu.video.title')"
                                    v-bind:description="$t('editor.main_menu.video.description')"></RichMenuItem>
                                    <RichMenuItem
                                        v-on:click="createPoll"
                                        v-bind:disabled="postMediaButtonStatus(PostType.POLL)"
                                        iconName="bar-chart-12"
                                        v-bind:title="$t('editor.main_menu.poll.title')"
                                    v-bind:description="$t('editor.main_menu.poll.description')"></RichMenuItem>
                                    <RichMenuItem
                                        v-on:click="selectAudio"
                                        v-bind:disabled="postMediaButtonStatus(PostType.AUDIO)"
                                        iconName="music-note-01"
                                        v-bind:title="$t('editor.main_menu.audio.title')"
                                    v-bind:description="$t('editor.main_menu.audio.description')"></RichMenuItem>
                                    <RichMenuItem
                                        v-on:click="selectDocument"
                                        v-bind:disabled="postMediaButtonStatus(PostType.DOCUMENT)"
                                        iconName="sticker-square"
                                        v-bind:title="$t('editor.main_menu.document.title')"
                                    v-bind:description="$t('editor.main_menu.document.description')"></RichMenuItem>
                                    <RichMenuItem
                                        v-on:click="state.recorderMenu.open"
                                        v-bind:disabled="postMediaButtonStatus(PostType.RECORDING)"
                                        iconName="recording-02"
                                        iconType="line"
                                        v-bind:title="$t('editor.main_menu.recording.title')"
                                        trailingIconName="microphone-01"
                                    v-bind:description="$t('editor.main_menu.recording.description')"></RichMenuItem>
                                    <RichMenuItem
                                        v-on:click.stop="state.gifMenu.open(); state.mainMenu.close();"
                                        v-bind:disabled="postMediaButtonStatus(PostType.GIF)"
                                        iconName="gif"
                                        iconType="line"
                                        v-bind:title="$t('editor.main_menu.gif.title')"
                                    v-bind:description="$t('editor.main_menu.gif.description')"></RichMenuItem>
                                </RichMenu>
                            </div>
                            <template v-if="state.recorderMenu.status">
                                <div class="absolute top-full left-0 w-80">
                                    <PostRecordingForm v-on:uploaded="onAudioRecorded" v-on:close="state.recorderMenu.close"></PostRecordingForm>
                                </div>
                            </template>

                            <template v-if="state.gifMenu.status">
                                <div class="absolute top-full left-0 w-80 z-50">
                                    <PostGifForm v-outside-click="state.gifMenu.close" v-on:select="selectGif"></PostGifForm>
                                </div>
                            </template>
                        </div>
                        <span class="w-px h-4 bg-bord-sc"></span>
                    </template>
                    <div class="shrink-0 inline-flex relative">
                        <MediaCreateButton v-on:click="openCheatSheetPanel" iconName="type-01"></MediaCreateButton>
                    </div>
                    <template v-if="! isEditingPost">
                        <span class="w-px h-4 bg-bord-sc"></span>
                        <div class="shrink-0 inline-flex relative">
                            <MediaCreateButton v-on:click.stop="state.moreMenu.open" iconName="circle-dots"></MediaCreateButton>

                            <div class="absolute top-full left-0 z-50" v-if="state.moreMenu.status">
                                <DropdownMenu v-outside-click="state.moreMenu.close" v-on:click="state.moreMenu.close">
                                    <DropdownMenuItem v-on:click="markPostAsSensitive" iconName="alert-triangle" v-bind:textLabel="(isSensitivePost ? $t('editor.unmark_sensitive') : $t('editor.mark_sensitive'))"></DropdownMenuItem>
                                    <DropdownMenuItem v-on:click="markPostAsAiGenerated" iconName="cpu-chip-02" v-bind:textLabel="(isAiGeneratedPost ? $t('editor.unmark_ai_generated') : $t('editor.mark_ai_generated'))"></DropdownMenuItem>
                                    <Border/>
                                    <a v-bind:href="$link('guide_links.publication_rules')" target="_blank">
                                        <DropdownMenuItem
                                            iconName="arrow-up-right"
                                        v-bind:textLabel="$t('editor.publication_guidelines')"></DropdownMenuItem>
                                    </a>
                                </DropdownMenu>
                            </div>
                        </div>
                    </template>
                    <template v-if="state.postMediaUploadProgress && ! isEditingPost">
                        <span class="w-px h-4 bg-bord-sc"></span>
                        <div class="shrink-0 inline-flex">
                            <span class="inline-flex text-par-s items-center gap-2 text-lab-sc leading-none disabled:opacity-60">
                                <span class="text-brand-900">{{ $t('labels.uploading') }} <span class="inline-block w-8">{{ state.postMediaUploadProgress }}%</span></span>
                            </span>
                        </div>
                    </template>

                    <div class="ml-auto">
                        <PrimaryTextButton buttonRole="marginal" buttonType="submit" v-bind:loading="state.postSubmitting" v-bind:isDisabled="Boolean(state.postMediaUploadProgress)" v-bind:buttonText="submitButtonText"></PrimaryTextButton>
                    </div>
                </div>
                <div v-if="! isEditingPost" class="block leading-normal">
                    <div class="w-10/12">
                        <p  v-if="userData.is_author" class="text-par-s text-lab-sc">
                            {{ $t('editor.post_privacy') }}
                        </p>
                        <p v-else class="text-par-s text-lab-sc">
                            {{ $t('editor.post_author_note') }} <a v-bind:href="$getRoute('become_author')" class="hover:underline text-brand-900">{{ $t('labels.learn_more') }}</a>
                        </p>
                    </div>
                </div>
            </div>
            <div class="hidden">
                <input v-on:change="onImageSelect" type="file" accept="image/*" ref="postImageFileInput">
                <input v-on:change="onVideoSelect" type="file" accept="video/*" ref="postVideoFileInput">
                <input v-on:change="onDocumentSelect" type="file" ref="postDocumentFileInput">
                <input v-on:change="onAudioSelect" type="file" accept="audio/*" ref="postAudioFileInput">
            </div>
        </form>
        <SensitivePostTape v-if="isSensitivePost"></SensitivePostTape>
    </div>
</template>

<script>
    import { defineComponent, defineAsyncComponent, onMounted, onBeforeUnmount, ref, reactive, computed, nextTick } from 'vue';
    import { PostTypeUtils, PostType } from '@/kernel/enums/post/post.type.js';

    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { imagePasteHandler } from '@/kernel/events/image-paste/index.js';
    import { useCheatSheet } from '@D/core/composables/cheat-sheet/index.js';
    import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useTimelineStore } from '@D/store/timeline/timeline.store.js';
    import { usePostEditorStore } from '@D/store/timeline/editor.store.js';
    import { useMenu } from '@/kernel/vue/composables/menu/index.js';

    import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';

    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
    import MediaCreateButton from '@D/components/timeline/editor/buttons/MediaCreateButton.vue';
    import EmojisPickerButton from '@D/components/timeline/editor/buttons/EmojisPickerButton.vue';

    import MentionsPicker from '@D/components/mentions/MentionsPicker.vue';
    import DropdownMenu from '@D/components/general/dropdowns/parts/DropdownMenu.vue';
    import DropdownMenuItem from '@D/components/general/dropdowns/parts/DropdownMenuItem.vue';
    import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
    import RichMenu from '@D/components/general/rich-menu/RichMenu.vue';
    import RichMenuItem from '@D/components/general/rich-menu/RichMenuItem.vue';

    export default defineComponent({
        setup: function(props, context) {
            const postImageFileInput = ref(null);
            const postDocumentFileInput = ref(null);
            const postVideoFileInput = ref(null);
            const postAudioFileInput = ref(null);
            const postTextInputField = ref('');
            const ignoredLinkSnapshots = ref([]);

            const { openCheatSheetPanel } = useCheatSheet();
            const { autoResize, insertSymbolAtCaret, matchMention, matchLink, completeText } = useInputHandlers();

            const authStore = useAuthStore();
            const timelineStore = useTimelineStore();
            const postEditorStore = usePostEditorStore();
            const userData = computed(() => {
                return authStore.userData;
            });

            const postData = computed(() => {
                return postEditorStore.draftPost;
            });

            const state = reactive({
                postSubmitting: false,
                postMediaUploadProgress: 0,
                localMediaPreviews: [],
                isDragging: false,
                isLinkPreviewing: false,
                isFetchingLinkPreview: false,
                moreMenu: useMenu(),
                emojisMenu: useMenu(),
                recorderMenu: useMenu(),
                gifMenu: useMenu(),
                mainMenu: useMenu()
            });

            const textInputHandler = function() {
                const mentionMatch = matchMention(postTextInputField.value);

                if(mentionMatch) {
                    colibriEventBus.emit('editor:mention-input', mentionMatch.username);
                }

                if(! postEditorStore.isEditingPost && ! state.isLinkPreviewing && ! state.isFetchingLinkPreview) {
                    const linkMatch = matchLink(postTextInputField.value);

                    if(PostTypeUtils.isText(postData.value.type) && linkMatch && ! ignoredLinkSnapshots.value.includes(linkMatch)) {
                        // If the link has already been previewed and ignored, don't preview it again
                        // This is to prevent spamming the API with the same link over and over

                        state.isFetchingLinkPreview = true;

                        colibriAPI().postEditor().with({
                            url: linkMatch
                        }).sendTo('link/preview').then((response) => {

                            // Using nextTick to ensure reactivity updates are processed
                            nextTick(() => {
                                postEditorStore.setLinkSnapshot(response.data.data);
                            });

                            state.isLinkPreviewing = true;
                            state.isFetchingLinkPreview = false;
                        }).catch((error) => {
                            state.isLinkPreviewing = false;
                            state.isFetchingLinkPreview = false;
                        });
                    }
                }

                if(postTextInputField.value.value.length <= 10) {
                    postEditorStore.preservedPostData = {};
                }

                autoResize(postTextInputField.value);
            }

            const resetFileInputTags = () => {
                postImageFileInput.value.value = '';
                postDocumentFileInput.value.value = '';
                postVideoFileInput.value.value = '';
                postAudioFileInput.value.value = '';
            }

            onMounted(async function() {
                await postEditorStore.fetchDraftPost();
            });

            const clearLocalMediaPreviews = () => {
                state.localMediaPreviews.forEach((mediaItem) => {
                    if(mediaItem.preview_url) {
                        URL.revokeObjectURL(mediaItem.preview_url);
                    }
                });

                state.localMediaPreviews = [];
            }

            const createLocalMediaPreview = (mediaFile, type = 'image') => {
                if(! ['image', 'video'].includes(type)) {
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

            onBeforeUnmount(() => {
                clearLocalMediaPreviews();
            });

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

            const uploadPostMediaLocally = (mediaFile, type = 'image') => {
                if(! mediaFile) {
                    return false;
                }

                const formData = new FormData();
                formData.append(type, mediaFile);

                return colibriAPI().postEditor().with(formData).withHeaders({
                    'Content-Type': 'multipart/form-data'
                }).uploadProgress((progressEvent) => {
                    state.postMediaUploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
                }).sendTo(`media/${type}/upload`).then((response) => {

                    postEditorStore.fetchDraftPost({
                        preserveContent: true
                    });

                    clearLocalMediaPreviews();

                    state.postMediaUploadProgress = 0;

                    resetFileInputTags();
                }).catch((error) => {
                    clearLocalMediaPreviews();

                    toastError(error.response?.data?.message || error.message || 'Upload failed');

                    state.postMediaUploadProgress = 0;

                    resetFileInputTags();
                });
            }

            const uploadPostVideoDirectly = async (mediaFile) => {
                if(! mediaFile) {
                    return false;
                }

                const localPreview = createLocalMediaPreview(mediaFile, 'video');

                if(localPreview) {
                    clearLocalMediaPreviews();
                    state.localMediaPreviews.push(localPreview);
                }

                try {
                    state.postMediaUploadProgress = 5;

                    const response = await colibriAPI().postEditor().with({
                        name: mediaFile.name || 'video',
                        size: mediaFile.size,
                        mime: mediaFile.type || 'video/mp4',
                        extension: getFileExtension(mediaFile)
                    }).sendTo('media/video/direct/create');

                    const uploadData = getUploadResponseData(response);
                    const isMultipartUpload = uploadData.upload_type === 'multipart' && Array.isArray(uploadData.parts);

                    if(! uploadData.direct_upload || (! uploadData.upload_url && ! isMultipartUpload)) {
                        state.postMediaUploadProgress = 0;

                        return await uploadPostMediaLocally(mediaFile, 'video');
                    }

                    let completedParts = [];

                    if(isMultipartUpload) {
                        completedParts = await uploadMultipartFileToDirectUrl(uploadData, mediaFile, (progress) => {
                            state.postMediaUploadProgress = Math.min(90, Math.max(10, Math.round(10 + (progress * 0.8))));
                        });
                    }

                    else {
                        await uploadFileToDirectUrl(uploadData, mediaFile, (progress) => {
                            state.postMediaUploadProgress = Math.min(90, Math.max(10, Math.round(10 + (progress * 0.8))));
                        });
                    }

                    state.postMediaUploadProgress = 95;

                    const completionData = {
                        media_id: uploadData.media?.id,
                        uid: uploadData.uid,
                        upload_id: uploadData.upload_id,
                        parts: completedParts || []
                    };

                    let completionError = null;

                    for(let attempt = 1; attempt <= 3; attempt++) {
                        try {
                            await colibriAPI().postEditor().with(completionData).sendTo('media/video/direct/complete');
                            completionError = null;
                            break;
                        }
                        catch(error) {
                            completionError = error;

                            if(attempt < 3) {
                                await new Promise((resolve) => setTimeout(resolve, attempt * 1000));
                            }
                        }
                    }

                    if(completionError) {
                        throw completionError;
                    }

                    state.postMediaUploadProgress = 100;

                    await postEditorStore.fetchDraftPost({
                        preserveContent: true
                    });

                    clearLocalMediaPreviews();
                }

                catch (error) {
                    try {
                        await postEditorStore.fetchDraftPost({
                            preserveContent: true
                        });
                        clearLocalMediaPreviews();
                    }
                    catch (draftError) {
                        // Keep the local preview visible when the recovery request also fails.
                    }

                    toastError(error.response?.data?.message || error.message || 'Upload failed');
                }

                finally {
                    state.postMediaUploadProgress = 0;

                    resetFileInputTags();
                }
            }

            const uploadPostMedia = (mediaFile, type = 'image') => {
                if(type === 'video') {
                    return uploadPostVideoDirectly(mediaFile);
                }

                return uploadPostMediaLocally(mediaFile, type);
            }

            const getFormSubmitData = () => {
                let formData = {
                    content: postData.value.content,
                    marks: {
                        is_sensitive: postEditorStore.isSensitive,
                        is_ai_generated: postEditorStore.isAiGenerated
                    }
                };

                if(PostTypeUtils.isPoll(postData.value.type)) {
                    formData['poll_options'] = postData.value.relations.poll.choices;
                }

                if(postEditorStore.quotedPost) {
                    formData['quoted_post_id'] = postEditorStore.quotedPost.id;
                }

                return formData;
            }

            const submitForm = async () => {
                if(state.postMediaUploadProgress) {
                    toastError('Please wait until the video upload reaches 100%.');
                    return;
                }

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
                    }

                    autoResize(postTextInputField.value);

                    postEditorStore.finishEditing();

                    colibriEventBus.emit('post-editor:close');
                }).catch((error) => {
                    toastError(error.response?.data?.message || error.message);

                    if(PostTypeUtils.isPoll(postData.value.type)) {
                        postEditorStore.validatePollOptions(error.response?.data?.errors || {});
                    }
                });

                state.postSubmitting = false;
            }

            const selectDocument = () => {
                postDocumentFileInput.value.click();
            }

            const selectImage = () => {
                postImageFileInput.value.click();
            }

            const selectAudio = () => {
                postAudioFileInput.value.click();
            }

            const selectVideo = () => {
                postVideoFileInput.value.click();
            }

            const createPoll = () => {
                colibriAPI().postEditor().sendTo('poll/create').then((response) => {
                    postEditorStore.fetchDraftPost({
                        preserveContent: true
                    });
                });
            };

            const selectGif = (gifItem) => {
                colibriAPI().postEditor().with({
                    id: gifItem.id
                }).sendTo('gif/create').then((response) => {
                    postEditorStore.fetchDraftPost({
                        preserveContent: true
                    });
                }).catch((error) => {
                    toastError(error.response.data.message);
                });

                state.gifMenu.close();
            };

            return {
                state: state,
                userData: userData,
                postData: postData,
                textInputHandler: textInputHandler,
                postTextInputField: postTextInputField,
                PostTypeUtils: PostTypeUtils,
                PostType: PostType,
                openCheatSheetPanel: openCheatSheetPanel,
                onImageSelect: (event) => {
                    uploadPostMedia(event.target.files[0], 'image');
                },
                onAudioSelect: (event) => {
                    uploadPostMedia(event.target.files[0], 'audio');
                },
                onVideoSelect: (event) => {
                    uploadPostMedia(event.target.files[0], 'video');
                },
                onDocumentSelect: (event) => {
                    uploadPostMedia(event.target.files[0], 'document');
                },
                onAudioRecorded: (audioFile) => {
                    state.recorderMenu.close();

                    uploadPostMedia(audioFile, 'audio');
                },
                onMediaPaste: (event) => {
                    if(postEditorStore.isEditingPost) {
                        return false;
                    }

                    postEditorStore.preservedPostData = {
                        content: postData.value.content
                    };

                    imagePasteHandler(event, (imageFile) => {
                        uploadPostMedia(imageFile, 'image');
                    });
                },
                submitForm: submitForm,
                deletePostMedia: (mediaItem) => {
                    if(postEditorStore.isEditingPost) {
                        return false;
                    }

                    if(mediaItem.is_local_preview) {
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
                deletePostLinkSnapshot: () => {
                    if(postEditorStore.isEditingPost) {
                        return false;
                    }

                    ignoredLinkSnapshots.value.push(postData.value.relations.link_snapshot.url);

                    colibriAPI().postEditor().delete('link/delete').then(() => {
                        postEditorStore.deleteLinkSnapshot();
                        state.isLinkPreviewing = false;
                    });
                },
                deletePostPoll: () => {
                    if(postEditorStore.isEditingPost) {
                        return false;
                    }

                    colibriAPI().postEditor().delete('poll/delete').then(() => {
                        postEditorStore.fetchDraftPost({
                            preserveContent: true
                        });
                    });
                },
                deletePostGif: () => {
                    postEditorStore.resetDraftPost();
                },
                postHasMedia: computed(() => {
                    return state.localMediaPreviews.length || postData.value.relations?.media?.length;
                }),
                postHasPoll: computed(() => {
                    return postData.value.relations?.poll;
                }),
                postHasLinkSnapshot: computed(() => {
                    if(postData.value.relations?.link_snapshot) {
                        return true;
                    }

                    return false;
                }),
                postHasQuotedPost: computed(() => {
                    return postEditorStore.quotedPost !== null;
                }),
                postMedia: computed(() => {
                    const serverMedia = postData.value.relations?.media || [];

                    return serverMedia.concat(state.localMediaPreviews);
                }),
                currentPostType: computed(() => {
                    if(state.localMediaPreviews.length) {
                        return state.localMediaPreviews[0].type;
                    }

                    return postData.value.type;
                }),
                quotedPost: computed(() => {
                    return postEditorStore.quotedPost;
                }),
                postLinkSnapshot: computed(() => {
                    return postData.value.relations.link_snapshot;
                }),
                selectGif: selectGif,
                createPoll: createPoll,
                selectImage: selectImage,
                selectVideo: selectVideo,
                selectAudio: selectAudio,
                selectDocument: selectDocument,
                postImageFileInput: postImageFileInput,
                postVideoFileInput: postVideoFileInput,
                postDocumentFileInput: postDocumentFileInput,
                postAudioFileInput: postAudioFileInput,
                postTextInputPlaceholder: computed(() => {
                    if(postEditorStore.isEditingPost) {
                        return __t('editor.edit_post_text_input_placeholder');
                    }

                    if(PostTypeUtils.isPoll(postData.value.type)) {
                        return __t('editor.post_poll_input_placeholder');
                    }
                    else{
                        return __t('editor.post_text_input_placeholder');
                    }
                }),
                postMediaButtonStatus: (postType = null) => {
                    if (state.postSubmitting || postEditorStore.isEditingPost) {
                        return true;
                    }
                    else if(state.recorderMenu.status && PostTypeUtils.isRecording(postType)) {
                        return false;
                    }
                    else if(state.recorderMenu.status) {
                        return true;
                    }
                    else{
                        const editorPostType = state.localMediaPreviews.length ? state.localMediaPreviews[0].type : postData.value.type;

                        if(PostTypeUtils.isText(editorPostType)) {
                            return false;
                        }
                        else{
                            if (PostTypeUtils.isImage(editorPostType) && PostTypeUtils.isImage(postType)) {
                                return false;
                            }

                            return !!editorPostType;
                        }
                    }
                },
                insertPostEmoji: (emojiSymbol) => {
                    postData.value.content = insertSymbolAtCaret(postTextInputField.value, emojiSymbol);
                    postTextInputField.value.focus();
                },
                handleFileDrop: (event) => {
                    state.isDragging = false;

                    if(postEditorStore.isEditingPost) {
                        return false;
                    }

					const droppedFile = event.dataTransfer.files[0];

					if (droppedFile) {
						if (droppedFile.type.startsWith('image')) {
                            uploadPostMedia(droppedFile, 'image');
                        }
                        else if(droppedFile.type.startsWith('video')) {
                            uploadPostMedia(droppedFile, 'video');
                        }
					}
                },
                markPostAsSensitive: postEditorStore.markPostAsSensitive,
                markPostAsAiGenerated: postEditorStore.markPostAsAiGenerated,
                isSensitivePost: computed(() => {
                    return postEditorStore.isSensitive;
                }),
                isAiGeneratedPost: computed(() => {
                    return postEditorStore.isAiGenerated;
	                }),
                    isEditingPost: computed(() => {
                        return postEditorStore.isEditingPost;
                    }),
                    submitButtonText: computed(() => {
                        return postEditorStore.isEditingPost ? __t('labels.save_changes') : __t('editor.publish');
                    }),
	                selectMention: (username) => {
					let mentionMatch = matchMention(postTextInputField.value);

					if(mentionMatch) {
						postData.value.content = completeText(postTextInputField.value, {
							completable: `@${username}`,
							start: mentionMatch.start,
							end: mentionMatch.end
						});

						postTextInputField.value.focus();
					}
                }
            };
        },
        components: {
            PrimaryTextButton: PrimaryTextButton,
            MediaCreateButton: MediaCreateButton,
            DropdownButton: DropdownButton,
            DropdownMenu: DropdownMenu,
            AvatarSmall: AvatarSmall,
            DropdownMenuItem: DropdownMenuItem,
            MentionsPicker: MentionsPicker,
            RichMenu: RichMenu,
            RichMenuItem: RichMenuItem,
            EmojisPickerButton: EmojisPickerButton,
            MediaDeleteButton: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/buttons/MediaDeleteButton.vue');
            }),
            MediaBlurOverlay: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/animations/MediaBlurOverlay.vue');
            }),
            PostPollForm: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/poll/PostPollForm.vue');
            }),
            PostGifForm: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/gif/PostGifForm.vue');
            }),
            PostRecordingForm: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/recording/PostRecordingForm.vue');
            }),
            PostGifPreview: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/preview/gif/PostGifPreview.vue');
            }),
            PostDocumentPreview: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/preview/document/PostDocumentPreview.vue');
            }),
            PostVideoPreview: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/preview/video/PostVideoPreview.vue');
            }),
            SensitivePostTape: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/assets/SensitivePostTape.vue');
            }),
            MediaFileDropper: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/parts/MediaFileDropper.vue');
            }),
            EmojisPicker: defineAsyncComponent(() => {
                return import('@D/components/emojis/EmojisPicker.vue');
            }),
            PublicationQuote: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/quote/PublicationQuote.vue');
            }),
            PostLinkSnapshotPreview: defineAsyncComponent(() => {
                return import('@D/components/timeline/editor/preview/link/PostLinkSnapshotPreview.vue');
            })
        }
    });
</script>
