<template>
    <div class="flex h-16 items-center" v-outside-click.stop.prevent="cancelAudioRecording">
        <div class="w-3/12 inline-flex items-center gap-2">
            <span class="size-icon-x-small bg-red-900 rounded-full animate-pulse"></span>
            <span class="text-par-l text-lab-pr font-normal">
                {{ $filters.formatTime(elapsed) }}
            </span>
        </div>

        <div class="w-6/12 text-center">
            <span class="text-par-m text-lab-sc font-normal">
                {{ $t('chat.cancel_media_recording') }}
            </span>
        </div>

        <div class="w-3/12 flex justify-end">
            <IconButton v-on:click="sendAudio" iconName="send-03" iconType="solid"></IconButton>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
    import { useAudioRecorder } from '@/kernel/vue/composables/record/audio-recorder.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import hotkeys from 'hotkeys-js';

    import IconButton from '@D/views/messenger/children/chat/parts/ui/IconButton.vue';

    export default defineComponent({
        emits: ['cancel', 'sendAudio'],
        setup: function(props, context) {
            const audioDurationSeconds = 120;
            const { startMic, startRecording, stopRecording, stopMic, blob, elapsed } = useAudioRecorder({
                maxDuration: audioDurationSeconds,
            });

            onMounted(async () => {
                await startMic();
                startRecording();

                hotkeys('esc', cancelAudioRecording);

                // Pause all media players what ever they are.
                colibriEventBus.emit('media:pause-all');
            });

            onBeforeUnmount(() => {
                stopRecording();

                hotkeys.unbind('esc');
            });

            const cancelAudioRecording = () => {
                stopMic();
                stopRecording();

                context.emit('cancel');
            }

            const sendAudio = () => {
                stopMic();
                stopRecording();

                setTimeout(() => {
                    context.emit('sendAudio', {
                        blob: blob.value,
                        duration: elapsed.value,
                    });
                }, 300);
            }

            watch(elapsed, (newElapsed) => {
                if(newElapsed >= audioDurationSeconds) {
                    sendAudio();
                }
            });

            return {
                IconButton: IconButton,
                sendAudio: sendAudio,
                elapsed: elapsed,
                cancelAudioRecording: cancelAudioRecording,
            };
        },
        components: {
            IconButton: IconButton,
        }
    });
</script>
