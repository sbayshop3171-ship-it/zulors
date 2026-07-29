<template>
    <div v-on:click.self="cancelVideoRecorder" class="absolute backdrop-blur-xs top-0 left-0 right-0 bottom-16 flex-center z-50">
        <div class="size-80 border-3 border-bg-pr bg-bg-pr rounded-full overflow-hidden">
            <video ref="videoPlaybackRef" autoplay playsinline muted class="size-full object-cover"></video>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, watch, onMounted, onBeforeUnmount } from 'vue';
    import { useVideoRecorder } from '@/kernel/vue/composables/record/video-recorder.js';
    import { useChatStore } from '@M/store/chats/chat.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    export default defineComponent({
        emits: ['cancel', 'sendVideo'],
        setup: function(props, context) {
            const chatStore = useChatStore();
            const videoPlaybackRef = ref(null);
            const videoDurationSeconds = 60;
            const { startCamera, blob, startRecording, stopRecording, stopCamera, elapsed } = useVideoRecorder({
                maxDuration: videoDurationSeconds,
            });

            const cancelVideoRecorder = () => {
                stopCamera();
                stopRecording();
                context.emit('cancel');
            }

            const stopVideoRecording = (eventData) => {
                stopCamera();
                stopRecording();

                setTimeout(() => {
                    context.emit('sendVideo', {
                        duration: elapsed.value,
                        blob: blob.value,
                    });
                }, 300);
            }

            onMounted(async () => {
                await startCamera(videoPlaybackRef.value);
                startRecording();

                colibriEventBus.on('messenger-message:stop-video-recording', stopVideoRecording);

                // Pause all media players what ever they are.
                colibriEventBus.emit('media:pause-all');
            });

            onBeforeUnmount(() => {
                stopCamera();
                stopRecording();
                colibriEventBus.off('messenger-message:stop-video-recording', stopVideoRecording);
            });

            watch(elapsed, (newElapsed) => {
                chatStore.messageForm.videoRecorder.elapsed = newElapsed;

                if(newElapsed >= videoDurationSeconds) {
                    stopVideoRecording();
                }
            });

            return {
                videoPlaybackRef: videoPlaybackRef,
                elapsed: elapsed,
                cancelVideoRecorder: cancelVideoRecorder,
            }
        }
    });
</script>
