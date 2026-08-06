import { ref, onBeforeUnmount } from "vue";
import { probeAudioDuration } from '@/kernel/helpers/media/audio/index.js';

export function useAudioRecorder({ maxDuration = 120 } = {}) {
    const stream = ref(null);
    const isRecording = ref(false);
    const blob = ref(null);
    const duration = ref(0);
    const error = ref(null);
    const errorCode = ref('');
    const errorMessage = ref('');
    const elapsed = ref(0);
    const mimeType = ref('');

    let recorder = null;
    let chunks = [];
    let timer = null;
    let startTime = 0;
    let finalizeRecordingPromise = null;
    let resolveFinalizeRecording = null;

    function getPreferredMimeType() {
        if (!supportsMediaRecorder()) {
            return "";
        }

        const types = [
            "audio/mp4",
            "audio/webm;codecs=opus",
            "audio/webm",
            "audio/ogg;codecs=opus",
            "audio/mpeg",
        ];

        return types.find((t) => MediaRecorder.isTypeSupported(t)) || "";
    }

    function resetRecorderError() {
        error.value = null;
        errorCode.value = '';
        errorMessage.value = '';
    }

    function setRecorderError(code, message, rawError = null) {
        error.value = rawError;
        errorCode.value = code;
        errorMessage.value = message;

        return {
            ok: false,
            code: code,
            message: message,
            error: rawError,
        };
    }

    async function startMic() {
        resetRecorderError();
        stopMic();

        if (!canUseUserMedia()) {
            return setRecorderError('unsupported', 'Voice recording is not supported in this browser or app build yet.');
        }

        const permissionState = await getMicrophonePermissionState();

        if (permissionState === 'denied') {
            return setRecorderError('permission-denied', 'Microphone access is blocked. Please allow microphone access in your browser or app settings and try again.');
        }

        const microphoneConstraintSets = [
            {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    channelCount: { ideal: 1 },
                    sampleRate: { ideal: 48000 },
                    sampleSize: { ideal: 16 },
                },
                video: false,
            },
            {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
                video: false,
            },
            {
                audio: true,
                video: false,
            },
        ];

        let lastError = null;

        try {
            for (const constraints of microphoneConstraintSets) {
                try {
                    stream.value = await navigator.mediaDevices.getUserMedia(constraints);

                    return {
                        ok: true,
                    };
                } catch (constraintError) {
                    lastError = constraintError;

                    if (isPermissionDeniedError(constraintError)) {
                        break;
                    }
                }
            }
        } catch (e) {
            lastError = e;
        }

        return setRecorderError(
            resolveMicErrorCode(lastError, permissionState),
            resolveMicErrorMessage(lastError, permissionState),
            lastError,
        );
    }

    function startRecording() {
        if (! stream.value) {
            return false;
        };

        resetRecorderError();

        if (!supportsMediaRecorder()) {
            setRecorderError('unsupported', 'Voice recording is not supported in this browser or app build yet.');

            return false;
        }

        chunks = [];
        blob.value = null;
        duration.value = 0;
        elapsed.value = 0;
        mimeType.value = getPreferredMimeType();

        const recorderOptions = {
            audioBitsPerSecond: 128_000,
        };

        if (mimeType.value) {
            recorderOptions.mimeType = mimeType.value;
        }

        try {
            recorder = new MediaRecorder(stream.value, recorderOptions);
        } catch (primaryError) {
            try {
                recorder = new MediaRecorder(stream.value);
                mimeType.value = '';
            } catch (fallbackError) {
                setRecorderError('recorder-init-failed', 'We could not start voice recording on this device. Please try again.', fallbackError);

                return false;
            }
        }

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

        return true;
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
        errorCode,
        errorMessage,
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

function canUseUserMedia() {
    return typeof navigator !== 'undefined'
        && !!navigator.mediaDevices
        && typeof navigator.mediaDevices.getUserMedia === 'function';
}

function supportsMediaRecorder() {
    return typeof MediaRecorder !== 'undefined';
}

async function getMicrophonePermissionState() {
    if (typeof navigator === 'undefined' || !navigator.permissions?.query) {
        return null;
    }

    try {
        const result = await navigator.permissions.query({ name: 'microphone' });

        return result?.state || null;
    } catch {
        return null;
    }
}

function isPermissionDeniedError(error) {
    return [
        'NotAllowedError',
        'PermissionDeniedError',
        'SecurityError',
    ].includes(error?.name);
}

function resolveMicErrorCode(error, permissionState) {
    if (permissionState === 'denied' || isPermissionDeniedError(error)) {
        return 'permission-denied';
    }

    if ([
        'NotReadableError',
        'TrackStartError',
        'AbortError',
    ].includes(error?.name)) {
        return 'mic-unavailable';
    }

    if ([
        'OverconstrainedError',
        'ConstraintNotSatisfiedError',
    ].includes(error?.name)) {
        return 'mic-constraints';
    }

    return 'mic-unavailable';
}

function resolveMicErrorMessage(error, permissionState) {
    if (permissionState === 'denied' || isPermissionDeniedError(error)) {
        return 'Microphone access is blocked. Please allow microphone access in your browser or app settings and try again.';
    }

    if ([
        'NotReadableError',
        'TrackStartError',
        'AbortError',
    ].includes(error?.name)) {
        return 'Your microphone is busy or unavailable right now. Close other apps using it and try again.';
    }

    return 'We could not start voice recording right now. Please try again.';
}
