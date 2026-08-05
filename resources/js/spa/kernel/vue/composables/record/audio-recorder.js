import { ref, onBeforeUnmount } from "vue";
import { probeAudioDuration } from '@/kernel/helpers/media/audio/index.js';

export function useAudioRecorder({ maxDuration = 120 } = {}) {
    const stream = ref(null);
    const isRecording = ref(false);
    const blob = ref(null);
    const duration = ref(0);
    const error = ref(null);
    const elapsed = ref(0);
    const mimeType = ref('');

    let recorder = null;
    let chunks = [];
    let timer = null;
    let startTime = 0;
    let finalizeRecordingPromise = null;
    let resolveFinalizeRecording = null;

    function getPreferredMimeType() {
        const types = [
            "audio/mp4",
            "audio/webm;codecs=opus",
            "audio/webm",
            "audio/ogg;codecs=opus",
            "audio/mpeg",
        ];

        return types.find((t) => MediaRecorder.isTypeSupported(t)) || "";
    }

    async function startMic() {
        try {
            stream.value = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    channelCount: 1,
                    sampleRate: 48000,
                    sampleSize: 16,
                },
                video: false,
            });
        } catch (e) {
            error.value = e;
        }
    }

    function startRecording() {
        if (! stream.value) {
            return false;
        };

        chunks = [];
        blob.value = null;
        duration.value = 0;
        elapsed.value = 0;
        error.value = null;
        mimeType.value = getPreferredMimeType();

        const recorderOptions = {
            audioBitsPerSecond: 128_000,
        };

        if (mimeType.value) {
            recorderOptions.mimeType = mimeType.value;
        }

        recorder = new MediaRecorder(stream.value, recorderOptions);

        finalizeRecordingPromise = new Promise((resolve) => {
            resolveFinalizeRecording = resolve;
        });

        recorder.ondataavailable = (e) => {
            if (e.data.size > 0) {
                chunks.push(e.data);
            };
        };

        recorder.onstop = async () => {
            blob.value = new Blob(chunks, { type: mimeType.value || "audio/webm" });
            duration.value = await probeAudioDuration(blob.value, elapsed.value || 1);

            clearInterval(timer);

            resolveFinalizeRecording?.({
                blob: blob.value,
                duration: duration.value,
                mimeType: mimeType.value || blob.value.type || "audio/webm",
            });

            resolveFinalizeRecording = null;
        };

        recorder.start(200);
        isRecording.value = true;
        startTime = Date.now();

        timer = setInterval(() => {
            elapsed.value = Math.floor((Date.now() - startTime) / 1000);

            if (elapsed.value >= maxDuration) {
                stopRecording();
            }
        }, 250);
    }

    function stopRecording() {
        if (recorder?.state === "recording") {
            isRecording.value = false;
            recorder.stop();
        }

        return finalizeRecordingPromise;
    }

    async function finalizeRecording() {
        if (recorder?.state === "recording") {
            stopRecording();

            return await finalizeRecordingPromise;
        }

        if (blob.value) {
            return {
                blob: blob.value,
                duration: Math.max(1, duration.value || elapsed.value || 1),
                mimeType: mimeType.value || blob.value.type || "audio/webm",
            };
        }

        return null;
    }

    function stopMic() {
        stream.value?.getTracks().forEach((t) => t.stop());
        stream.value = null;
        clearInterval(timer);
    }

    onBeforeUnmount(() => {
        stopRecording();
        stopMic();
    });

    return {
        stream,
        isRecording,
        blob,
        error,
        elapsed,
        duration,
        mimeType,
        startMic,
        startRecording,
        stopRecording,
        finalizeRecording,
        stopMic,
    };
}
