import { ref, onBeforeUnmount } from 'vue';

export function useVideoRecorder({ maxDuration = 60 } = {}) {
    const stream = ref(null);
    const isRecording = ref(false);
    const blob = ref(null);
    const error = ref(null);
    const elapsed = ref(0);

    let recorder = null;
    let chunks = [];
    let timer = null;
    let startTime = 0;

    async function startCamera(videoEl) {
        try {
            stream.value = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: "user",
                    width: { ideal: 480 },
                    height: { ideal: 480 },
                },
                audio: true,
            });

            if (videoEl) {
                videoEl.srcObject = stream.value;
            }
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
            videoBitsPerSecond: 1_000_000, // 1 Mbps — good quality for circles
        });

        recorder.ondataavailable = (e) => {
            if (e.data.size > 0) {
                return chunks.push(e.data);
            };
        };

        recorder.onstop = () => {
            blob.value = new Blob(chunks, { type: mimeType || "video/webm" });
            clearInterval(timer);
        };

        recorder.start(200); // collect chunks every 200ms
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

    function stopCamera() {
        stream.value?.getTracks().forEach((t) => t.stop());
        stream.value = null;
    }

    onBeforeUnmount(() => {
        stopRecording();
        stopCamera();
    });

    return {
        stream,
        isRecording,
        blob,
        error,
        elapsed,
        startCamera,
        startRecording,
        stopRecording,
        stopCamera,
    };
}

function getPreferredMimeType() {
    const types = [
        "video/webm;codecs=vp9",
        "video/webm;codecs=vp8",
        "video/webm",
        "video/mp4;codecs=h264",
        "video/mp4",
    ];

    return types.find((t) => MediaRecorder.isTypeSupported(t)) || "";
}
