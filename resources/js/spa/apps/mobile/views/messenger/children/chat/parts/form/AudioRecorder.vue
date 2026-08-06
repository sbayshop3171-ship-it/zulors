<template>
    <div class="flex h-16 items-center px-4 border-t border-bord-pr" v-outside-click.stop.prevent="cancelAudioRecording">
        <div class="w-3/12 inline-flex items-center gap-2">
            <span class="size-icon-x-small bg-red-900 rounded-full animate-pulse"></span>
            <span class="text-par-s text-lab-pr font-normal">
                {{ $filters.formatTime(elapsed) }}
            </span>
        </div>

        <div class="w-6/12 text-center">
            <span class="text-par-s text-lab-sc font-normal">
                {{ $t('chat.cancel_media_recording') }}
            </span>
        </div>

        <div class="w-3/12 flex justify-end">
            <PrimaryIconButton v-on:click="sendAudio" iconName="send-03" iconType="solid"></PrimaryIconButton>
        </div>
    </div>
</template>

<script>
    import { defineComponent, onMounted, onBeforeUnmount, watch, ref } from 'vue';
    import { useAudioRecorder } from '@/kernel/vue/composables/record/audio-recorder.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        emits: ['cancel', 'sendAudio'],
        setup: function(props, context) {
            const audioDurationSeconds = 120;
            const isSubmitting = ref(false);
            const { startMic, startRecording, stopRecording, stopMic, finalizeRecording, elapsed, errorMessage } = useAudioRecorder({
                maxDuration: audioDurationSeconds,
            });

            onMounted(async () => {
                const micStartResult = await startMic();

                if(micStartResult?.ok === false) {
                    alert(errorMessage.value || 'We could not start voice recording right now. Please try again.');
                    context.emit('cancel');

                    return;
                }

                if(! startRecording()) {
                    alert(errorMessage.value || 'We could not start voice recording on this device. Please try again.');
                    context.emit('cancel');

                    return;
                }

                // Pause all media players what ever they are.
                colibriEventBus.emit('media:pause-all');
            });

            onBeforeUnmount(() => {
                stopRecording();
                stopMic();
            });

            const cancelAudioRecording = () => {
                stopRecording();
                stopMic();

                context.emit('cancel');
            }

            const sendAudio = async () => {
                if(isSubmitting.value) {
                    return;
                }

                isSubmitting.value = true;

                const recordingData = await finalizeRecording();

                stopMic();

                if(recordingData?.blob && recordingData.blob.size > 0) {
                    context.emit('sendAudio', {
                        blob: recordingData.blob,
                        duration: Math.max(1, recordingData.duration || elapsed.value || 1),
                        mimeType: recordingData.mimeType || recordingData.blob.type || 'audio/webm',
                    });
                }
                else{
                    context.emit('cancel');
                }

                isSubmitting.value = false;
            }

            watch(elapsed, (newElapsed) => {
                if(newElapsed >= audioDurationSeconds && ! isSubmitting.value) {
                    sendAudio();
                }
            });

            return {
                sendAudio: sendAudio,
                elapsed: elapsed,
                cancelAudioRecording: cancelAudioRecording,
            };
        },
        components: {
            PrimaryIconButton: PrimaryIconButton,
        }
    });
</script>
