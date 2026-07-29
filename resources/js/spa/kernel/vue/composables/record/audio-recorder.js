import { ref, onBeforeUnmount } from "vue";

export function useAudioRecorder({ maxDuration = 120 } = {}) {
    const stream = ref(null);
    const isRecording = ref(false);
    const blob = ref(null);
    const error = ref(null);
    const elapsed = ref(0);

    let recorder = null;
    let chunks = [];
    let timer = null;
    let startTime = 0;

    function getPreferredMimeType() {
        const types = [
            "audio/webm;codecs=opus",
            "audio/webm",
            "audio/mp4",
            "audio/ogg;codecs=opus",
        ];

        return types.find((t) => MediaRecorder.isTypeSupported(t)) || "";
    }

    async function startMic() {
        try {
            stream.value = await navigator.mediaDevices.getUserMedia({
                audio: true,
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
        elapsed.value = 0;

        const mimeType = getPreferredMimeType();
        recorder = new MediaRecorder(stream.value, {
            mimeType,
            audioBitsPerSecond: 128_000,
        });

        recorder.ondataavailable = (e) => {
            if (e.data.size > 0) {
                chunks.push(e.data);
            };
        };

        recorder.onstop = () => {
            blob.value = new Blob(chunks, { type: mimeType || "audio/webm" });

            clearInterval(timer);
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
            recorder.stop();
            isRecording.value = false;
        }
    }

    function stopMic() {
        stream.value?.getTracks().forEach((t) => t.stop());
        stream.value = null;
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
        startMic,
        startRecording,
        stopRecording,
        stopMic,
    };
}
