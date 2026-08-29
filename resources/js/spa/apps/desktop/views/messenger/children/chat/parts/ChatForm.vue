<template>
	<div class="bg-bg-pr px-6" v-bind:class="[(isReplaying || state.videoRecorder.open || state.audioRecorder.open) ? 'border-t border-t-bord-card' : '']">
        <template v-if="state.videoRecorder.open">
            <VideoRecordPreview></VideoRecordPreview>
        </template>
        <template v-else-if="state.audioRecorder.open">
            <AudioRecorder v-on:cancel="state.audioRecorder.open = false" v-on:sendAudio="sendAudio"></AudioRecorder>
        </template>
        <template v-else>
            <div class="py-4">
                <div v-if="isReplaying" class="mb-3">
                    <MessageReplyPreview v-on:cancel="cancelMessageReply" v-bind:key="repliedMessage.id" v-bind:messageData="repliedMessage"></MessageReplyPreview>
                </div>
                <div class="relative leading-none">
                    <div class="absolute left-3 top-3">
                        <div class="relative">
                            <IconButton v-on:click.stop="state.isEmojisPickerOpen = true" v-bind:disabled="state.isSubmitting" iconName="face-smile" iconType="line"></IconButton>
                            <template v-if="state.isEmojisPickerOpen">
                                <div class="block absolute bottom-6 left-0 w-80 z-50">
                                    <EmojisPicker
                                        v-on:pick="insertMessageEmoji"
                                    v-on:close="state.isEmojisPickerOpen = false"></EmojisPicker>
                                </div>
                            </template>
                        </div>
                    </div>

                    <textarea ref="messageInputField" class="resize-none pl-11 pr-36 pt-2.5 pb-2 leading-normal text-lab-pr font-normal text-par-l border border-bord-card w-full h-12 min-h-12 max-h-40 overflow-x-hidden overflow-y-auto rounded-3xl outline-hidden placeholder:text-par-l placeholder:text-lab-sc placeholder:font-normal"
                        v-on:input.trim="messageInputHandler"
                        v-on:keydown.enter="submitForm"
                        v-model.trim="inputMessageText"
                    v-bind:placeholder="isReplaying ? $t('chat.write_reply') : $t('chat.write_message')"></textarea>

                    <div class="absolute right-4 top-3">
                        <div class="flex gap-4">
                            <IconButton v-if="hasTyped" v-on:click="submitForm" iconName="send-03" iconType="solid"></IconButton>
                            <template v-else>
                                <div class="relative">
                                    <IconButton v-on:click.stop="state.attachments.open = ! state.attachments.open" iconName="paperclip" iconType="line"></IconButton>
                                    <div v-if="state.attachments.open" v-outside-click="closeAttachments" class="absolute bottom-8 right-0 z-50 w-56 overflow-hidden rounded-2xl border border-bord-card bg-bg-pr py-1 shadow-xl">
                                        <button type="button" v-on:click="triggerFileInput(messageImageFileInput)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-par-s font-medium text-lab-pr2 hover:bg-fill-qt">
                                            <SvgIcon type="line" name="image-01" classes="size-icon-small"></SvgIcon>
                                            <span class="truncate">Image</span>
                                        </button>
                                        <button type="button" v-on:click="triggerFileInput(messageVideoFileInput)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-par-s font-medium text-lab-pr2 hover:bg-fill-qt">
                                            <SvgIcon type="line" name="video-recorder" classes="size-icon-small"></SvgIcon>
                                            <span class="truncate">Video</span>
                                        </button>
                                        <button type="button" v-on:click="triggerFileInput(messageDocumentFileInput)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-par-s font-medium text-lab-pr2 hover:bg-fill-qt">
                                            <SvgIcon type="line" name="file-attachment-01" classes="size-icon-small"></SvgIcon>
                                            <span class="truncate">File</span>
                                        </button>
                                        <button type="button" v-on:click="sendLocation" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-par-s font-medium text-lab-pr2 hover:bg-fill-qt">
                                            <SvgIcon type="line" name="marker-pin-01" classes="size-icon-small"></SvgIcon>
                                            <span class="truncate">Location</span>
                                        </button>
                                    </div>
                                </div>
                                <IconButton v-on:click.stop="state.audioRecorder.open = true" iconName="microphone-01" iconType="line"></IconButton>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>
	</div>

    <template v-if="state.videoRecorder.open">
        <VideoRecorder v-on:sendVideo="sendVideo" v-on:cancel="state.videoRecorder.open = false"></VideoRecorder>
    </template>

    <div class="hidden">
        <input v-on:change="sendImage" type="file" accept="image/jpeg, image/png, image/webp, image/gif, image/heic, image/heif, image/heif-sequence, image/heic-sequence" ref="messageImageFileInput">
        <input v-on:change="sendVideoFile" type="file" accept="video/mp4, video/webm, video/quicktime" ref="messageVideoFileInput">
        <input v-on:change="sendDocument" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,application/zip,application/x-zip-compressed,application/x-rar-compressed,application/vnd.rar" ref="messageDocumentFileInput">
    </div>
</template>

<script>
	import { defineComponent, ref, computed, reactive, defineAsyncComponent, onMounted, nextTick } from 'vue';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
    import { inferAudioExtension } from '@/kernel/helpers/media/audio/index.js';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';

	import MessageReplyPreview from '@D/views/messenger/children/chat/parts/form/MessageReplyPreview.vue';
    import IconButton from '@D/views/messenger/children/chat/parts/ui/IconButton.vue';
    import VideoRecorder from '@D/views/messenger/children/chat/parts/form/VideoRecorder.vue';
    import AudioRecorder from '@D/views/messenger/children/chat/parts/form/AudioRecorder.vue';
    import VideoRecordPreview from '@D/views/messenger/children/chat/parts/form/VideoRecordPreview.vue';

	export default defineComponent({
		emits: ['typing'],
		setup: function (props, context) {
			const repliedMessage = ref(null);
			const chatStore = useChatStore();
			const messageInputField = ref(null);
            const messageImageFileInput = ref(null);
            const messageVideoFileInput = ref(null);
            const messageDocumentFileInput = ref(null);
			const inputMessageText = ref('');
			const { autoResize, insertSymbolAtCaret, preserveInputFocus } = useInputHandlers();
			const state = reactive({
				isSubmitting: false,
				isEmojisPickerOpen: false,
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

            const messageInputHandler = function() {
                autoResize(messageInputField.value);

				context.emit('typing');
            }

			onMounted(() => {
				colibriEventBus.on('messenger-message:reply', (event) => {
					repliedMessage.value = event.messageData;

					if(messageInputField.value) {
						messageInputField.value.focus();
					}
				});
			});

            const sendMessage = function(payload = null) {
                if(payload !== null) {
					if(repliedMessage.value) {
						payload.parent_id = repliedMessage.value.id;
						payload.parent_message = repliedMessage.value;
					}

					state.isSubmitting = false;
					repliedMessage.value = null;
					colibriSounds.chatMessageSent();
					chatStore.sendMessage(payload).catch((error) => {
						alert(error);
					});
                }
            }

			const submitForm = async function(event) {
				if(! state.isSubmitting) {
					if (event.shiftKey) {
						messageInputHandler();
					}
					else{
						event.preventDefault();
						state.isEmojisPickerOpen = false;

                        sendMessage({
                            content: inputMessageText.value,
                        });

                        preserveInputFocus(messageInputField.value, '');
                        autoResize(messageInputField.value);
					}
				}
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

            const getFileExtension = (file) => {
                return file.name.split('.').pop().toLowerCase();
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

            const closeAttachments = () => {
                state.attachments.open = false;
            }

            const triggerFileInput = (fileInput) => {
                closeAttachments();

                nextTick(() => {
                    fileInput?.click();
                });
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
                        await sendMessage({
                            content: `https://www.google.com/maps?q=${latitude},${longitude}`,
                            message_type: 'location',
                        });
                    }
                    catch(error) {
                        state.isSubmitting = false;
                        alert(error);
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

			return {
				state: state,
				repliedMessage: repliedMessage,
				messageInputHandler: messageInputHandler,
				submitForm: submitForm,
                sendVideo: sendVideo,
                sendAudio: sendAudio,
				autoResize: autoResize,
                messageInputField: messageInputField,
                messageImageFileInput: messageImageFileInput,
                messageVideoFileInput: messageVideoFileInput,
                messageDocumentFileInput: messageDocumentFileInput,
                inputMessageText: inputMessageText,
				hasTyped: computed(() => {
					return inputMessageText.value.length > 0;
				}),
				insertMessageEmoji: (emojiSymbol) => {
                    inputMessageText.value = insertSymbolAtCaret(messageInputField.value, emojiSymbol);
                    messageInputField.value.focus();
                },
				isReplaying: computed(() => {
					return (repliedMessage.value !== null);
				}),
				cancelMessageReply: () => {
					repliedMessage.value = null;
                },
                sendImage: sendImage,
                sendVideoFile: sendVideoFile,
                sendDocument: sendDocument,
                sendLocation: sendLocation,
                closeAttachments: closeAttachments,
                triggerFileInput: triggerFileInput,
			};
		},
		components: {
            IconButton: IconButton,
			EmojisPicker: defineAsyncComponent(() => {
                return import('@D/components/emojis/EmojisPicker.vue');
            }),
			MessageReplyPreview: MessageReplyPreview,
            VideoRecorder: VideoRecorder,
            VideoRecordPreview: VideoRecordPreview,
            AudioRecorder: AudioRecorder,
		}
	});
</script>
