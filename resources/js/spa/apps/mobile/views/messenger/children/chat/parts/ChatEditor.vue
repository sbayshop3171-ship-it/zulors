<template>
	<ToastNotification></ToastNotification>

    <template v-if="state.videoRecorder.open">
        <VideoRecordPreview></VideoRecordPreview>
    </template>
    <template v-else-if="state.audioRecorder.open">
        <AudioRecorder v-on:sendAudio="sendAudio" v-on:cancel="state.audioRecorder.open = false"></AudioRecorder>
    </template>
    <template v-else>
        <template v-if="repliedMessage">
            <MessageReplyPreview v-bind:messageData="repliedMessage" v-on:cancel="cancelReply" v-bind:key="repliedMessage.id"></MessageReplyPreview>
        </template>

        <div class="relative z-20 pb-3 px-4 pt-3">
            <div class="flex overflow-visible gap-2">
                <div class="flex-1">
                    <textarea ref="messageContentField" class="resize-none border border-bord-pr pl-4 pt-2.5 pr-22 pb-2 leading-normal text-lab-pr text-par-l bg-fill-qt w-full h-12 min-h-12 max-h-40 overflow-x-hidden overflow-y-auto rounded-3xl outline-hidden placeholder:whitespace-nowrap placeholder:text-par-l placeholder:text-lab-sc placeholder:font-normal"
                        v-model.trim="messageContent"
                        v-on:input="messageInputHandler"
                    v-bind:placeholder="inputPlaceholder"></textarea>
                </div>

                <div class="shrink-0 pt-2">
                    <div class="inline-flex gap-2">
                        <PrimaryIconButton v-if="hasTyped" v-on:click="submitForm" iconName="send-03" iconSize="icon-normal" buttonColor="text-brand-900"></PrimaryIconButton>
                        <template v-else>
                            <div class="relative">
                                <PrimaryIconButton v-on:click.stop="state.attachments.open = ! state.attachments.open" v-bind:disabled="state.isSubmitting" iconName="paperclip" iconType="line"></PrimaryIconButton>
                                <div v-if="state.attachments.open" v-outside-click="closeAttachments" class="absolute bottom-full right-0 z-[1000] mb-2 w-56 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-bord-card bg-bg-pr py-1 shadow-xl">
                                    <button type="button" v-on:click="triggerFileInput(messageImageFileInput)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-par-s font-semibold text-lab-pr2 hover:bg-fill-qt">
                                        <span class="size-icon-small shrink-0">
                                            <SvgIcon type="line" name="image-01" classes="size-full"></SvgIcon>
                                        </span>
                                        <span class="truncate">Image</span>
                                    </button>
                                    <button type="button" v-on:click="triggerFileInput(messageVideoFileInput)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-par-s font-semibold text-lab-pr2 hover:bg-fill-qt">
                                        <span class="size-icon-small shrink-0">
                                            <SvgIcon type="line" name="video-recorder" classes="size-full"></SvgIcon>
                                        </span>
                                        <span class="truncate">Video</span>
                                    </button>
                                    <button type="button" v-on:click="triggerFileInput(messageDocumentFileInput)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-par-s font-semibold text-lab-pr2 hover:bg-fill-qt">
                                        <span class="size-icon-small shrink-0">
                                            <SvgIcon type="line" name="file-attachment-01" classes="size-full"></SvgIcon>
                                        </span>
                                        <span class="truncate">File</span>
                                    </button>
                                    <button type="button" v-on:click="sendLocation" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-par-s font-semibold text-lab-pr2 hover:bg-fill-qt">
                                        <span class="size-icon-small shrink-0">
                                            <SvgIcon type="line" name="marker-pin-01" classes="size-full"></SvgIcon>
                                        </span>
                                        <span class="truncate">Location</span>
                                    </button>
                                </div>
                            </div>
                            <PrimaryIconButton iconName="microphone-01" iconType="line" v-bind:disabled="state.isSubmitting" v-on:click.stop="state.audioRecorder.open = true"></PrimaryIconButton>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="hidden">
            <input v-on:change="sendImage" type="file" accept="image/jpeg, image/png, image/webp, image/gif, image/heic, image/heif, image/heif-sequence, image/heic-sequence" ref="messageImageFileInput">
            <input v-on:change="sendVideoFile" type="file" accept="video/mp4, video/webm, video/quicktime" ref="messageVideoFileInput">
            <input v-on:change="sendDocument" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,application/zip,application/x-zip-compressed,application/x-rar-compressed,application/vnd.rar" ref="messageDocumentFileInput">
        </div>
    </template>

    <template v-if="state.videoRecorder.open">
        <VideoRecorder v-on:sendVideo="sendVideo" v-on:cancel="state.videoRecorder.open = false"></VideoRecorder>
    </template>
</template>

<script>
	import { defineComponent, ref, reactive, computed, nextTick, onMounted } from 'vue';
	import { useChatStore } from '@M/store/chats/chat.store.js';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { inferAudioExtension } from '@/kernel/helpers/media/audio/index.js';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ToastNotification from '@M/components/notifications/toast/ToastNotification.vue';
	import MessageReplyPreview from '@M/views/messenger/children/chat/parts/editor/MessageReplyPreview.vue';
    import VideoRecorder from '@M/views/messenger/children/chat/parts/form/VideoRecorder.vue';
    import AudioRecorder from '@M/views/messenger/children/chat/parts/form/AudioRecorder.vue';
    import VideoRecordPreview from '@M/views/messenger/children/chat/parts/form/VideoRecordPreview.vue';

	export default defineComponent({
		emits: ['typing'],
		setup: function (props, context) {
			const chatStore = useChatStore();
			const messageContentField = ref(null);
            const messageImageFileInput = ref(null);
            const messageVideoFileInput = ref(null);
            const messageDocumentFileInput = ref(null);
			const messageContent = ref('');
			const repliedMessage = ref(null);
			const { autoResize } = useInputHandlers();

			const state = reactive({
				isSubmitting: false,
                videoRecorder: {
                    open: false,
                },
                audioRecorder: {
                    open: false,
                },
                attachments: {
                    open: false,
                },
			});

			onMounted(() => {
				colibriEventBus.on('messenger-message:reply', (event) => {
					repliedMessage.value = event.messageData;

					if(messageContentField.value) {
						messageContentField.value.focus();
					}
				});
			});

			const messageInputHandler = function() {
				autoResize(messageContentField.value);

				context.emit('typing');
			}

			const submitForm = async function(event) {
            const content = messageContent.value;

            if(! content.length) {
                return;
            }

            const payload = {
                content: content,
                parent_message: repliedMessage.value || null,
            };

            if(repliedMessage.value) {
                payload.parent_id = repliedMessage.value.id;
            }

            messageContent.value = '';
            repliedMessage.value = null;
            state.isSubmitting = false;

            nextTick(() => {
                messageInputHandler();
                messageInputField.value?.focus();
            });

            colibriSounds.chatMessageSent();
            chatStore.sendMessage(payload).catch((error) => {
                alert(error);
            });
            }

            const getFileExtension = (file) => {
                return file.name.split('.').pop().toLowerCase();
            }

            const getVideoDuration = (file) => {
                return new Promise((resolve) => {
                    const video = document.createElement('video');
                    const objectUrl = URL.createObjectURL(file);

                    video.preload = 'metadata';
                    video.onloadedmetadata = function() {
                        URL.revokeObjectURL(objectUrl);
                        resolve(Math.max(1, Math.ceil(video.duration || 1)));
                    };

                    video.onerror = function() {
                        URL.revokeObjectURL(objectUrl);
                        resolve(1);
                    };

                    video.src = objectUrl;
                });
            }

            const resetFileInput = (event) => {
                event.target.value = '';
            }

            const getReplyId = () => {
                return repliedMessage.value ? repliedMessage.value.id : null;
            }

            const clearReplyAfterSend = () => {
                repliedMessage.value = null;
            }

            const sendImage = async (event) => {
                const file = event.target.files[0];

                if(! file) {
                    return false;
                }

                try {
                    state.isSubmitting = true;

                    await chatStore.sendMediaMessage({
                        type: 'image',
                        extension: getFileExtension(file),
                        file: file,
                        name: file.name,
                        parent_id: getReplyId(),
                    });

                    clearReplyAfterSend();
                }
                catch(error) {
                    alert(error);
                }
                finally {
                    state.isSubmitting = false;
                    resetFileInput(event);
                }
            }

            const sendVideoFile = async (event) => {
                const file = event.target.files[0];

                if(! file) {
                    return false;
                }

                try {
                    state.isSubmitting = true;

                    await chatStore.sendMediaMessage({
                        type: 'video',
                        extension: getFileExtension(file),
                        file: file,
                        name: file.name,
                        duration: await getVideoDuration(file),
                        parent_id: getReplyId(),
                    });

                    clearReplyAfterSend();
                }
                catch(error) {
                    alert(error);
                }
                finally {
                    state.isSubmitting = false;
                    resetFileInput(event);
                }
            }

            const sendDocument = async (event) => {
                const file = event.target.files[0];

                if(! file) {
                    return false;
                }

                try {
                    state.isSubmitting = true;

                    await chatStore.sendMediaMessage({
                        type: 'document',
                        extension: getFileExtension(file),
                        file: file,
                        name: file.name,
                        parent_id: getReplyId(),
                    });

                    clearReplyAfterSend();
                }
                catch(error) {
                    alert(error);
                }
                finally {
                    state.isSubmitting = false;
                    resetFileInput(event);
                }
            }

            const triggerFileInput = (fileInput) => {
                closeAttachments();

                nextTick(() => {
                    fileInput?.click();
                });
            }

            const closeAttachments = () => {
                state.attachments.open = false;
            }

            const sendLocation = async () => {
                closeAttachments();

                if(! navigator.geolocation) {
                    alert('Location is not supported by this browser.');

                    return false;
                }

                state.isSubmitting = true;

                navigator.geolocation.getCurrentPosition(async (position) => {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;

                    try {
                        await chatStore.sendMessage({
                            content: `https://www.google.com/maps?q=${latitude},${longitude}`,
                            message_type: 'location',
                            parent_id: getReplyId(),
                        });

                        clearReplyAfterSend();
                        colibriSounds.chatMessageSent();
                    }
                    catch(error) {
                        alert(error);
                    }
                    finally {
                        state.isSubmitting = false;
                    }
                }, (error) => {
                    state.isSubmitting = false;
                    alert(error.message);
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000,
                });
            }

            const sendVideo = async (videoData) => {
                state.videoRecorder.open = false;

                await chatStore.sendMediaMessage({
                    type: 'video',
                    file: videoData.blob,
                    extension: videoData.blob.type.includes('mp4') ? 'mp4' : 'webm',
                    duration: videoData.duration,
                });
            }

            const sendAudio = async (audioData) => {
                state.audioRecorder.open = false;

                await chatStore.sendAudioMessage({
                    extension: inferAudioExtension(audioData.mimeType || audioData.blob.type, 'webm'),
                    file: audioData.blob,
                    duration: audioData.duration,
                    mime_type: audioData.mimeType || audioData.blob.type,
                    parent_id: getReplyId(),
                    parent_message: repliedMessage.value,
                });

                clearReplyAfterSend();
                colibriSounds.chatMessageSent();
            }

			return {
				state: state,
				messageContent: messageContent,
				submitForm: submitForm,
				repliedMessage: repliedMessage,
                messageContentField: messageContentField,
                messageImageFileInput: messageImageFileInput,
                messageVideoFileInput: messageVideoFileInput,
                messageDocumentFileInput: messageDocumentFileInput,
				messageInputHandler: messageInputHandler,
                sendVideo: sendVideo,
                sendAudio: sendAudio,
				isReplaying: computed(() => {
					return repliedMessage.value !== null;
				}),
                hasTyped: computed(() => {
					return messageContent.value.length > 0;
                }),
                sendImage: sendImage,
                sendVideoFile: sendVideoFile,
                sendDocument: sendDocument,
                sendLocation: sendLocation,
                triggerFileInput: triggerFileInput,
                closeAttachments: closeAttachments,
				cancelReply: () => {
					repliedMessage.value = null;
				},
				inputPlaceholder: computed(() => {
					if(state.isSubmitting) {
						return __t('chat.sending_message');
					}

					else if(repliedMessage.value) {
						return __t('chat.write_reply');
					}

					return __t('chat.write_message');
				})
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			ToastNotification: ToastNotification,
			MessageReplyPreview: MessageReplyPreview,
            VideoRecorder: VideoRecorder,
            VideoRecordPreview: VideoRecordPreview,
            AudioRecorder: AudioRecorder,
		}
	});
</script>
