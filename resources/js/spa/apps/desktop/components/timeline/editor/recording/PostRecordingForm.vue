<template>
    <div class="popup-card">
        <PopupPanel>
            <div class="py-5">
                <div class="px-4 pb-1.5">
                    <h3 class="text-lab-pr2 text-par-m font-medium">{{ $t('editor.recording_audio') }}</h3>
                </div>
                <div class="px-4 py-6">
                    <div class="border-b-2" v-bind:class="[recorderState.isRecording ? 'border-brand-900' : 'border-fill-pr']">
                        <canvas ref="waveformCanvas" id="waveform" class="w-full h-6"></canvas>
                    </div>
                </div>
                <div class="">
                    <div class="mb-8">
                        <h4 class="text-lab-pr2 text-title-1 text-center mb-2">
                            {{ $filters.formatTime(recorderState.recordingTime.seconds) }} 
                        </h4>
                        <p class="text-center">
                            <span class="text-lab-sc text-par-l">{{ recorderState.recordingTime.milliseconds.toString().toString().padStart(2, '0') }}</span>
                        </p>
                    </div>
                    
                    <div class="flex justify-center mb-8">
                        <div class="inline-flex-center size-12 bg-transparent ring-2 ring-fill-pr overflow-hidden outline-hidden rounded-full">
                            <button v-if="recorderState.isRecording" v-on:click="stopRecording" type="button" class="inline-block outline-none cursor-pointer size-4 rounded-xs bg-red-600"></button>
                            <button v-else v-on:click="startRecording" type="button" class="inline-block outline-none size-full bg-red-600 cursor-pointer"></button>
                        </div>
                    </div>
    
                    <div v-if="! recorderState.isRecording" class="flex justify-center">
                        <PrimaryTextButton buttonRole="marginal" v-on:click="$emit('close')" v-bind:buttonText="$t('labels.cancel')"></PrimaryTextButton>
                    </div>
                </div>
            </div>
        </PopupPanel>
    </div>
</template>

<script>
    import { defineComponent, computed, reactive, ref, onMounted } from 'vue';
    
    import { audioVisualizer } from '@/kernel/services/audio-visualizer/index.js';
    
    import RecordRTC from 'recordrtc';
    import PopupPanel from '@D/components/inter-ui/popups/PopupPanel.vue';

    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
    
    export default defineComponent({
        emits: ['uploaded', 'close'],
        setup: function(props, context) {
            const waveformCanvas = ref(null);

            const audioRecorder = {
                audioStream: null,
                recorderRTC: null,
                audioVisualizer: null
            };

            const recorderState = reactive({
                recordingTime: {
                    seconds: 0,
                    milliseconds: 0,
                },
                recorderTimerInterval: null,
                isRecording: false,
            })

            const startRecording = async () => {
                try {
                    await navigator.mediaDevices.getUserMedia({ 
                        audio: true
                    }).then((audioStream) => {
                        audioRecorder.audioStream = audioStream;
                        audioRecorder.recorderRTC = new RecordRTC(audioStream, {
                            type: 'audio',
                            mimeType: 'audio/webm',
                            timeSlice: 1000,
                            disableLogs: false
                        });

                        audioRecorder.recorderRTC.startRecording();
                        recorderState.isRecording = true;
                        recorderState.recorderTimerInterval = setInterval(() => {
                            recorderState.recordingTime.milliseconds += 1;

                            if (recorderState.recordingTime.milliseconds >= 99) {
                                recorderState.recordingTime.milliseconds = 0;
                                recorderState.recordingTime.seconds += 1;
                            }
                        }, 1000 / 100);

                        let audioContext = new (window.AudioContext || window.webkitAudioContext)();

                        audioRecorder.audioVisualizer = audioVisualizer().setCanvas(waveformCanvas.value).setAudioElement({
                            context: audioContext,
                            source: audioContext.createMediaStreamSource(audioStream)
                        });

                        audioRecorder.audioVisualizer.linesWaveStart();
                    });
                } catch (error) {
                    toastError(error.message);
                }
            }

            const stopRecording = async () => {
                try {
                    await audioRecorder.recorderRTC.stopRecording(() => {
                        const audioBlob = audioRecorder.recorderRTC.getBlob();

                        recorderState.isRecording = false;

                        const date = new Date();
                        const day = String(date.getDate()).padStart(2, '0');
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const year = date.getFullYear();
                        const hours = String(date.getHours()).padStart(2, '0');
                        const minutes = String(date.getMinutes()).padStart(2, '0');

                        let fileName = `AUDREC-${year}${month}${day}-${hours}${minutes}.webm`;

                        context.emit('uploaded', new File([audioBlob], fileName, {
                            type: 'audio/webm'
                        }));

                        audioRecorder.audioStream.getTracks().forEach((track) => {
                            track.stop();
                        });

                        audioRecorder.audioStream = null;
                        audioRecorder.recorderRTC = null;
                        audioRecorder.audioVisualizer = null;

                        clearInterval(recorderState.recorderTimerInterval);
                    });
                } catch (error) {
                    toastError(error.message);
                }
            }

            return {
                startRecording: startRecording,
                stopRecording: stopRecording,
                recorderState: recorderState,
                waveformCanvas: waveformCanvas
            };
        },
        components: {
            PopupPanel: PopupPanel,
            PrimaryTextButton: PrimaryTextButton
        }
    });
</script>