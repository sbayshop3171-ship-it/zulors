import { AIDenoiserExtension } from 'agora-extension-ai-denoiser';

const parsePositiveNumber = (value, defaultValue) => {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? number : defaultValue;
};

const parsePositiveInteger = (value, defaultValue) => {
    const number = Number.parseInt(value, 10);

    return Number.isFinite(number) && number > 0 ? number : defaultValue;
};

const parseUnitNumber = (value, defaultValue) => {
    const number = Number(value);

    if(! Number.isFinite(number)) {
        return defaultValue;
    }

    return Math.max(0, Math.min(1, number));
};

const parseBooleanEnv = (value, defaultValue = true) => {
    if(value === undefined || value === null || value === '') {
        return defaultValue;
    }

    return ! ['false', '0', 'off', 'no'].includes(String(value).toLowerCase());
};

const agoraSupportedAreaCodes = new Set([
    'GLOBAL',
    'CHINA',
    'NORTH_AMERICA',
    'EUROPE',
    'ASIA',
    'JAPAN',
    'INDIA'
]);
const agoraAudioEncoderPresets = {
    speech_low_quality: {
        sampleRate: 16000,
        stereo: false,
        bitrate: 24
    },
    speech_standard: {
        sampleRate: 32000,
        stereo: false,
        bitrate: 24
    },
    music_standard: {
        sampleRate: 48000,
        stereo: false,
        bitrate: 32
    },
    standard_stereo: {
        sampleRate: 48000,
        stereo: true,
        bitrate: 64
    },
    high_quality: {
        sampleRate: 48000,
        stereo: false,
        bitrate: 128
    },
    high_quality_stereo: {
        sampleRate: 48000,
        stereo: true,
        bitrate: 192
    }
};
const normalizeAgoraAreaCode = (value, fallback = '') => {
    const normalizedValue = String(value ?? fallback ?? '')
        .trim()
        .replaceAll('-', '_')
        .replaceAll(' ', '_')
        .toUpperCase();

    if(! normalizedValue) {
        return '';
    }

    return agoraSupportedAreaCodes.has(normalizedValue)
        ? normalizedValue
        : String(fallback || '').trim().toUpperCase();
};
const normalizeAgoraAudioEncoderProfile = (value, fallback = 'speech_low_quality') => {
    const normalizedValue = String(value ?? fallback ?? '')
        .trim()
        .toLowerCase();

    if(agoraAudioEncoderPresets[normalizedValue]) {
        return normalizedValue;
    }

    return agoraAudioEncoderPresets[fallback]
        ? fallback
        : 'speech_low_quality';
};
const normalizeAgoraAudioSampleRate = (value, fallback = 16000) => {
    const sampleRate = parsePositiveInteger(value, fallback);

    return [16000, 32000, 48000].includes(sampleRate)
        ? sampleRate
        : fallback;
};
const clampAgoraAudioBitrateKbps = (value, fallback = 20, minimum = 12, maximum = 192) => {
    const bitrate = parsePositiveInteger(value, fallback);

    return Math.max(minimum, Math.min(maximum, bitrate));
};

const qualityMonitorIntervalMs = parsePositiveNumber(import.meta.env.VITE_CALL_QUALITY_MONITOR_INTERVAL, 3000);
const qualityWarningSamples = Math.max(1, parsePositiveNumber(import.meta.env.VITE_CALL_QUALITY_WARNING_SAMPLES, 2));
const agoraSdkLoadTimeoutMs = parsePositiveNumber(import.meta.env.VITE_AGORA_CALL_SDK_TIMEOUT_MS, 12000);
const agoraPermissionTimeoutMs = parsePositiveNumber(import.meta.env.VITE_AGORA_CALL_PERMISSION_TIMEOUT_MS, 12000);
const agoraJoinTimeoutMs = parsePositiveNumber(import.meta.env.VITE_AGORA_CALL_JOIN_TIMEOUT_MS, 12000);
const agoraTrackTimeoutMs = parsePositiveNumber(import.meta.env.VITE_AGORA_CALL_TRACK_TIMEOUT_MS, 12000);
const agoraPublishTimeoutMs = parsePositiveNumber(import.meta.env.VITE_AGORA_CALL_PUBLISH_TIMEOUT_MS, 12000);
const agoraRemoteSweepIntervalMs = parsePositiveNumber(import.meta.env.VITE_AGORA_CALL_REMOTE_SWEEP_INTERVAL_MS, 1000);
const agoraPreferredAudioLatency = parsePositiveNumber(import.meta.env.VITE_AGORA_CALL_AUDIO_LATENCY, 0.02);
const enableVoiceProcessing = parseBooleanEnv(import.meta.env.VITE_CALL_AUDIO_PROCESSING, false);
const enableNativeAppVoiceProcessing = parseBooleanEnv(import.meta.env.VITE_CALL_NATIVE_APP_AUDIO_PROCESSING, false);
const voiceProcessingOutputGain = Math.min(
    0.7,
    parseUnitNumber(import.meta.env.VITE_CALL_AUDIO_INPUT_GAIN, 0.65)
);
const maximumRemoteOutputVolume = Math.min(
    2,
    parsePositiveNumber(import.meta.env.VITE_CALL_REMOTE_MAX_VOLUME, 1)
);
const enableAgoraAiDenoiser = parseBooleanEnv(import.meta.env.VITE_AGORA_AI_DENOISER, true);
const agoraAiDenoiserLevel = String(import.meta.env.VITE_AGORA_AI_DENOISER_LEVEL || 'SOFT').toUpperCase();
const agoraAiDenoiserMode = String(import.meta.env.VITE_AGORA_AI_DENOISER_MODE || 'STATIONARY_NS').toUpperCase();
const agoraAiDenoiserAssetsPath = String(import.meta.env.VITE_AGORA_AI_DENOISER_ASSETS_PATH || '/assets/agora-ai-denoiser')
    .replace(/\/+$/, '');
const processedLocalPublishVolume = Math.round(voiceProcessingOutputGain * 100);
const defaultAgoraAreaCode = normalizeAgoraAreaCode(import.meta.env.VITE_AGORA_CALL_AREA_CODE, 'GLOBAL');
const defaultAgoraExcludedArea = normalizeAgoraAreaCode(import.meta.env.VITE_AGORA_CALL_EXCLUDED_AREA, '');
const defaultAgoraAudioEncoderProfile = normalizeAgoraAudioEncoderProfile(
    import.meta.env.VITE_AGORA_CALL_AUDIO_ENCODER_PROFILE,
    'speech_low_quality'
);
const defaultAgoraSpeechEncoderConfig = {
    ...agoraAudioEncoderPresets[defaultAgoraAudioEncoderProfile],
    sampleRate: normalizeAgoraAudioSampleRate(
        import.meta.env.VITE_AGORA_CALL_AUDIO_SAMPLE_RATE,
        agoraAudioEncoderPresets[defaultAgoraAudioEncoderProfile].sampleRate
    ),
    bitrate: clampAgoraAudioBitrateKbps(
        import.meta.env.VITE_AGORA_CALL_AUDIO_BITRATE,
        20,
        12,
        32
    )
};
let agoraRtcPromise = null;
let agoraAiDenoiserExtension = null;
let agoraAiDenoiserRegistered = false;

const createTimeoutError = (message) => {
    const error = new Error(message || 'Operation timed out.');

    error.name = 'TimeoutError';

    return error;
};

const makeSilentAgoraError = (message = 'Call already ended.') => {
    const error = new Error(message);

    error.__zulorsSilentCallToast = true;

    return error;
};

const withTimeout = async (promise, timeoutMs, message) => {
    if(! timeoutMs || typeof window === 'undefined') {
        return Promise.resolve(promise);
    }

    let timer = null;

    try {
        return await Promise.race([
            Promise.resolve(promise),
            new Promise((resolve, reject) => {
                timer = window.setTimeout(() => {
                    reject(createTimeoutError(message));
                }, timeoutMs);
            })
        ]);
    }
    finally {
        if(timer) {
            window.clearTimeout(timer);
        }
    }
};

const hasNativeCallAudioBridge = () => {
    return typeof window !== 'undefined' && Boolean(window.ZulorsCallAudio);
};

const isLikelyMobileCallClient = () => {
    if(typeof navigator === 'undefined') {
        return false;
    }

    const userAgent = String(navigator.userAgent || navigator.vendor || '');
    const touchPoints = Number(navigator.maxTouchPoints || 0);

    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i.test(userAgent)
        || touchPoints > 1;
};

const yieldToBrowser = async (delayMs = 16) => {
    if(typeof window === 'undefined') {
        return;
    }

    await new Promise((resolve) => {
        window.requestAnimationFrame?.(() => {
            window.setTimeout(resolve, delayMs);
        }) || window.setTimeout(resolve, delayMs);
    });
};

const isAgoraAudioCallSupported = () => {
    return Boolean(
        typeof window !== 'undefined'
        && typeof navigator !== 'undefined'
        && navigator.mediaDevices?.getUserMedia
    );
};

const loadAgoraRTC = async () => {
    if(! agoraRtcPromise) {
        agoraRtcPromise = import('agora-rtc-sdk-ng')
            .then((module) => module.default || module);
    }

    return agoraRtcPromise;
};

const getAgoraAiDenoiserExtension = (AgoraRTC) => {
    if(
        ! enableAgoraAiDenoiser
        || isLikelyMobileCallClient()
        || typeof AIDenoiserExtension !== 'function'
        || typeof AgoraRTC?.registerExtensions !== 'function'
    ) {
        return null;
    }

    try {
        if(! agoraAiDenoiserExtension) {
            agoraAiDenoiserExtension = new AIDenoiserExtension({
                assetsPath: agoraAiDenoiserAssetsPath,
                fetchOptions: {
                    cache: 'no-cache'
                }
            });
            agoraAiDenoiserExtension.onloaderror = () => {};
        }

        if(! agoraAiDenoiserExtension.checkCompatibility?.()) {
            return null;
        }

        if(! agoraAiDenoiserRegistered) {
            AgoraRTC.registerExtensions([agoraAiDenoiserExtension]);
            agoraAiDenoiserRegistered = true;
        }

        return agoraAiDenoiserExtension;
    }
    catch(error) {
        return null;
    }
};

const enableAiDenoiserForTrack = async (AgoraRTC, audioTrack) => {
    if(
        ! audioTrack
        || typeof audioTrack.pipe !== 'function'
        || ! audioTrack.processorDestination
    ) {
        return null;
    }

    const extension = getAgoraAiDenoiserExtension(AgoraRTC);

    if(! extension || typeof extension.createProcessor !== 'function') {
        return null;
    }

    let processor = null;
    let restored = false;
    const restorePipeline = async () => {
        if(restored) {
            return;
        }

        restored = true;

        try {
            processor?.unpipe?.();
        }
        catch(error) {}

        try {
            audioTrack.unpipe?.();
        }
        catch(error) {}

        try {
            audioTrack.pipe?.(audioTrack.processorDestination);
        }
        catch(error) {}
    };

    try {
        processor = extension.createProcessor();
        processor.on?.('overload', async () => {
            try {
                await processor.setMode?.('STATIONARY_NS');
                await processor.setLevel?.('SOFT');
            }
            catch(error) {}
        });
        processor.on?.('pipeerror', () => {
            restorePipeline();
        });

        audioTrack.pipe(processor).pipe(audioTrack.processorDestination);
        await processor.setLatency?.('LOW');
        await processor.setLevel?.(agoraAiDenoiserLevel);
        await processor.setMode?.(agoraAiDenoiserMode);
        await processor.enable?.();

        return async () => {
            await restorePipeline();

            try {
                await processor?.destroy?.();
            }
            catch(error) {}
        };
    }
    catch(error) {
        await restorePipeline();

        try {
            await processor?.destroy?.();
        }
        catch(innerError) {}

        return null;
    }
};

const applyLocalPublishVolume = (audioTrack) => {
    try {
        audioTrack?.setVolume?.(shouldUseCustomVoiceProcessing() ? processedLocalPublishVolume : 100);
    }
    catch(error) {}
};

const warmAgoraAudioCallEngine = async () => {
    try {
        await withTimeout(
            loadAgoraRTC(),
            agoraSdkLoadTimeoutMs,
            'Audio engine took too long to warm up.'
        );
    }
    catch(error) {}

    return true;
};

const toNumber = (value, fallback = 0) => {
    const number = Number(value);

    return Number.isFinite(number) ? number : fallback;
};

const normalizeAgoraUid = (uid) => {
    if(uid === undefined || uid === null) {
        return '';
    }

    return String(uid);
};

const streamFromTrack = (audioTrack) => {
    const mediaTrack = audioTrack?.getMediaStreamTrack?.();

    return mediaTrack ? new MediaStream([mediaTrack]) : null;
};

const pickFirstValue = (...values) => {
    return values.find((value) => value !== undefined && value !== null && value !== '');
};

const normalizePacketLossPercent = (value) => {
    const number = toNumber(value, 0);

    if(number > 0 && number <= 1) {
        return Number((number * 100).toFixed(2));
    }

    return number;
};

const normalizeMilliseconds = (value) => {
    const number = toNumber(value, 0);

    if(number > 0 && number < 10) {
        return Number((number * 1000).toFixed(2));
    }

    return number;
};

const stopMediaStream = (stream) => {
    stream?.getTracks?.().forEach((track) => {
        try {
            track.enabled = false;
            track.stop();
        }
        catch(error) {}
    });
};

const applySpeechTrackHints = (stream) => {
    stream?.getAudioTracks?.().forEach((track) => {
        try {
            track.contentHint = 'speech';
        }
        catch(error) {}
    });
};

const resolveAgoraEncoderConfig = (mediaSession = {}) => {
    const profile = normalizeAgoraAudioEncoderProfile(
        mediaSession.audio_encoder_profile,
        defaultAgoraAudioEncoderProfile
    );
    const preset = agoraAudioEncoderPresets[profile] || agoraAudioEncoderPresets.speech_low_quality;

    return {
        sampleRate: normalizeAgoraAudioSampleRate(
            mediaSession.audio_sample_rate,
            preset.sampleRate || defaultAgoraSpeechEncoderConfig.sampleRate
        ),
        stereo: Boolean(mediaSession.audio_stereo ?? preset.stereo ?? false),
        bitrate: clampAgoraAudioBitrateKbps(
            mediaSession.audio_bitrate_kbps,
            preset.bitrate || defaultAgoraSpeechEncoderConfig.bitrate,
            12,
            Math.max(32, Number(preset.bitrate || defaultAgoraSpeechEncoderConfig.bitrate))
        )
    };
};

const applyAgoraRegionPreference = (AgoraRTC, mediaSession = {}) => {
    if(typeof AgoraRTC?.setArea !== 'function') {
        return;
    }

    const areaCode = normalizeAgoraAreaCode(mediaSession.area_code, defaultAgoraAreaCode);
    const excludedArea = normalizeAgoraAreaCode(mediaSession.excluded_area, defaultAgoraExcludedArea);

    if(! areaCode) {
        return;
    }

    try {
        if(areaCode === 'GLOBAL' && excludedArea && excludedArea !== 'GLOBAL') {
            AgoraRTC.setArea({
                areaCode: areaCode,
                excludedArea: excludedArea
            });

            return;
        }

        AgoraRTC.setArea({
            areaCode: areaCode
        });
    }
    catch(error) {}
};

const createInteractiveAudioContext = (sampleRate = defaultAgoraSpeechEncoderConfig.sampleRate) => {
    if(typeof window === 'undefined') {
        return null;
    }

    const AudioContext = window.AudioContext || window.webkitAudioContext;

    if(! AudioContext) {
        return null;
    }

    try {
        return new AudioContext({
            latencyHint: 'interactive',
            sampleRate: sampleRate
        });
    }
    catch(error) {
        try {
            return new AudioContext({
                latencyHint: 'interactive'
            });
        }
        catch(innerError) {
            return null;
        }
    }
};

const shouldUseCustomVoiceProcessing = () => {
    if(! enableVoiceProcessing) {
        return false;
    }

    if(isLikelyMobileCallClient()) {
        return false;
    }

    if(hasNativeCallAudioBridge() && ! enableNativeAppVoiceProcessing) {
        return false;
    }

    return true;
};

const createVoiceProcessedStream = async (rawStream, sampleRate = defaultAgoraSpeechEncoderConfig.sampleRate) => {
    const audioTracks = rawStream?.getAudioTracks?.() || [];

    if(
        ! shouldUseCustomVoiceProcessing()
        || ! audioTracks.length
    ) {
        return {
            stream: rawStream,
            cleanup: () => {}
        };
    }

    const context = createInteractiveAudioContext(sampleRate);

    if(! context) {
        return {
            stream: rawStream,
            cleanup: () => {}
        };
    }

    try {
        await context.resume?.().catch(() => {});
        await yieldToBrowser();

        const source = context.createMediaStreamSource(new MediaStream(audioTracks));
        const highPassFilter = context.createBiquadFilter();
        const lowPassFilter = context.createBiquadFilter();
        const compressor = context.createDynamicsCompressor();
        const gain = context.createGain();
        const destination = context.createMediaStreamDestination();

        highPassFilter.type = 'highpass';
        highPassFilter.frequency.value = 90;
        highPassFilter.Q.value = 0.7;

        lowPassFilter.type = 'lowpass';
        lowPassFilter.frequency.value = 12000;
        lowPassFilter.Q.value = 0.7;

        compressor.threshold.value = -24;
        compressor.knee.value = 24;
        compressor.ratio.value = 3;
        compressor.attack.value = 0.003;
        compressor.release.value = 0.25;

        gain.gain.value = voiceProcessingOutputGain;

        source
            .connect(highPassFilter)
            .connect(lowPassFilter)
            .connect(compressor)
            .connect(gain)
            .connect(destination);

        applySpeechTrackHints(destination.stream);

        return {
            stream: new MediaStream([
                ...destination.stream.getAudioTracks(),
                ...rawStream.getVideoTracks()
            ]),
            cleanup: () => {
                try {
                    source.disconnect();
                    highPassFilter.disconnect();
                    lowPassFilter.disconnect();
                    compressor.disconnect();
                    gain.disconnect();
                    destination.disconnect?.();
                    context.close?.().catch(() => {});
                }
                catch(error) {}
            }
        };
    }
    catch(error) {
        context.close?.().catch(() => {});

        return {
            stream: rawStream,
            cleanup: () => {}
        };
    }
};

const buildPreferredAudioConstraints = (
    lightweight = false,
    sampleRate = defaultAgoraSpeechEncoderConfig.sampleRate
) => {
    const baseConstraints = {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: false,
        channelCount: { ideal: 1 },
        sampleRate: { ideal: sampleRate },
        sampleSize: { ideal: 16 },
        latency: { ideal: agoraPreferredAudioLatency }
    };

    if(lightweight) {
        return baseConstraints;
    }

    return {
        ...baseConstraints,
        voiceIsolation: { ideal: true },
        googEchoCancellation: true,
        googEchoCancellation2: true,
        googDAEchoCancellation: true,
        googAutoGainControl: false,
        googAutoGainControl2: false,
        googNoiseSuppression: true,
        googNoiseSuppression2: true,
        googHighpassFilter: true,
        googTypingNoiseDetection: true
    };
};

const requestPreferredAudioCaptureStream = async (sampleRate = defaultAgoraSpeechEncoderConfig.sampleRate) => {
    const useLightweightConstraints = hasNativeCallAudioBridge() || isLikelyMobileCallClient();
    const attempts = useLightweightConstraints
        ? [
            {
                audio: buildPreferredAudioConstraints(true, sampleRate)
            },
            {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: false
                }
            },
            {
                audio: true
            }
        ]
        : [
            {
                audio: buildPreferredAudioConstraints(false, sampleRate)
            },
            {
                audio: buildPreferredAudioConstraints(true, sampleRate)
            },
            {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: false
                }
            },
            {
                audio: true
            }
        ];
    let lastError = null;

    for(const constraints of attempts) {
        try {
            const stream = await withTimeout(
                navigator.mediaDevices.getUserMedia(constraints),
                agoraPermissionTimeoutMs,
                'Microphone permission request timed out.'
            );

            applySpeechTrackHints(stream);

            return stream;
        }
        catch(error) {
            lastError = error;

            if(['NotAllowedError', 'PermissionDeniedError', 'SecurityError', 'NotFoundError', 'DevicesNotFoundError', 'TimeoutError'].includes(error?.name || '')) {
                break;
            }
        }
    }

    throw userFriendlyAgoraMediaError(lastError);
};

const userFriendlyAgoraMediaError = (error) => {
    if(error?.__zulorsSilentCallToast) {
        return error;
    }

    const errorName = error?.name || '';
    const errorMessage = error?.message || '';

    if(['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(errorName)) {
        return new Error('Microphone permission is blocked. Allow microphone access and try again.');
    }

    if(['NotFoundError', 'DevicesNotFoundError'].includes(errorName)) {
        return new Error('No microphone was found on this device.');
    }

    if(['NotReadableError', 'TrackStartError'].includes(errorName) || /could not start audio source/i.test(errorMessage)) {
        return new Error('Could not start microphone. Close other calls or apps using the microphone, then try again.');
    }

    if(['OverconstrainedError', 'ConstraintNotSatisfiedError'].includes(errorName)) {
        return new Error('This microphone cannot start with the current audio settings.');
    }

    if(errorName === 'TimeoutError') {
        return new Error(errorMessage || 'Microphone setup took too long. Please try again.');
    }

    return new Error(errorMessage || 'Unable to start microphone.');
};

const requestMicrophoneWarmup = async (sampleRate = defaultAgoraSpeechEncoderConfig.sampleRate) => {
    return requestPreferredAudioCaptureStream(sampleRate);
};

const setMediaStreamAudioTracksEnabled = (stream, enabled) => {
    stream?.getAudioTracks?.()?.forEach((track) => {
        try {
            track.enabled = Boolean(enabled);
        }
        catch(error) {}
    });
};

const createMicrophoneTrackWithFallback = async (AgoraRTC, prewarmedStream = null, options = {}) => {
    const encoderConfig = options?.encoderConfig || defaultAgoraSpeechEncoderConfig;
    const configs = [
        {
            AEC: true,
            AGC: false,
            ANS: true,
            encoderConfig: encoderConfig
        },
        {
            AEC: true,
            AGC: false,
            ANS: true
        },
        {}
    ];
    let lastError = null;
    const prewarmedAudioTrack = prewarmedStream?.getAudioTracks?.()?.[0] || null;
    const preferCustomTrack = Boolean(options?.preferCustomTrack);

    if(preferCustomTrack && prewarmedAudioTrack && AgoraRTC?.createCustomAudioTrack) {
        try {
            return {
                track: AgoraRTC.createCustomAudioTrack({
                    mediaStreamTrack: prewarmedAudioTrack,
                    encoderConfig: encoderConfig
                }),
                captureStream: prewarmedStream
            };
        }
        catch(error) {
            lastError = error;
            stopMediaStream(prewarmedStream);
        }
    }

    for(const config of configs) {
        try {
            return {
                track: await withTimeout(
                    AgoraRTC.createMicrophoneAudioTrack(config),
                    agoraTrackTimeoutMs,
                    'Microphone took too long to start.'
                ),
                captureStream: null
            };
        }
        catch(error) {
            lastError = error;

            if(['NotAllowedError', 'PermissionDeniedError', 'SecurityError', 'NotFoundError', 'DevicesNotFoundError'].includes(error?.name || '')) {
                break;
            }
        }
    }

    throw userFriendlyAgoraMediaError(lastError);
};

const classifyAgoraNetworkQuality = (quality = null) => {
    if(! quality) {
        return null;
    }

    const uplink = Number(quality.uplinkNetworkQuality);
    const downlink = Number(quality.downlinkNetworkQuality);
    const scores = [uplink, downlink].filter((score) => Number.isFinite(score) && score > 0);

    if(! scores.length) {
        return null;
    }

    const worstScore = Math.max(...scores);

    if(worstScore >= 5) {
        return 'poor';
    }

    if(worstScore >= 3) {
        return 'weak';
    }

    return 'good';
};

const createAgoraAudioCallPeer = (callbacks = {}, options = {}) => {
    let client = null;
    let localAudioTrack = null;
    let localCaptureStream = null;
    let rawCaptureStream = null;
    let voiceProcessingCleanup = null;
    let aiDenoiserCleanup = null;
    let remoteAudioTrack = null;
    let localStream = null;
    let remoteStream = null;
    let remoteMediaTrackId = null;
    let remoteOutputElement = null;
    let AgoraRTCModule = null;
    let joined = false;
    let isMuted = false;
    let qualityTimer = null;
    let currentMediaSession = options.mediaSession || {};
    let remoteUid = null;
    let closing = false;
    let latestNetworkQuality = null;
    let consecutiveWeakSamples = 0;
    let consecutivePoorSamples = 0;
    let remoteOutputVolume = 100;
    let remoteUserSweepTimer = null;
    let localAgoraUid = null;
    let remoteOutputAecTimers = [];

    const emit = (event, ...payload) => {
        try {
            callbacks[event]?.(...payload);
        }
        catch(error) {}
    };
    const cleanupVoiceProcessing = () => {
        try {
            voiceProcessingCleanup?.();
        }
        catch(error) {}

        voiceProcessingCleanup = null;
    };
    const disposeLocalCaptureStreams = () => {
        cleanupVoiceProcessing();

        stopMediaStream(localCaptureStream);

        if(rawCaptureStream && rawCaptureStream !== localCaptureStream) {
            stopMediaStream(rawCaptureStream);
        }

        localCaptureStream = null;
        rawCaptureStream = null;
    };
    const applyLocalMuteState = async () => {
        const shouldEnable = ! isMuted;

        setMediaStreamAudioTracksEnabled(rawCaptureStream, shouldEnable);
        setMediaStreamAudioTracksEnabled(localCaptureStream, shouldEnable);
        setMediaStreamAudioTracksEnabled(localStream, shouldEnable);

        if(! localAudioTrack) {
            return;
        }

        try {
            if(typeof localAudioTrack.setMuted === 'function') {
                await localAudioTrack.setMuted(isMuted);
            }
            else if(typeof localAudioTrack.setEnabled === 'function') {
                await localAudioTrack.setEnabled(shouldEnable);
            }
        }
        catch(error) {}
    };
    const throwIfClosing = () => {
        if(! closing) {
            return;
        }

        try {
            remoteAudioTrack?.stop?.();
        }
        catch(error) {}

        try {
            localAudioTrack?.close?.();
        }
        catch(error) {}

        disposeLocalCaptureStreams();

        try {
            client?.leave?.();
        }
        catch(error) {}

        remoteAudioTrack = null;
        localAudioTrack = null;
        localStream = null;
        remoteStream = null;
        remoteMediaTrackId = null;
        remoteUid = null;
        localAgoraUid = null;
        joined = false;

        throw makeSilentAgoraError();
    };

    const isLocalAgoraUser = (uid) => {
        const normalizedUid = normalizeAgoraUid(uid);

        return normalizedUid !== ''
            && (
                normalizedUid === normalizeAgoraUid(localAgoraUid)
                || normalizedUid === normalizeAgoraUid(currentMediaSession?.uid)
            );
    };

    const refreshMediaSession = async () => {
        if(typeof options.refreshMediaSession !== 'function') {
            return currentMediaSession;
        }

        const nextMediaSession = await options.refreshMediaSession();

        if(nextMediaSession?.provider === 'agora') {
            currentMediaSession = nextMediaSession;
        }

        return currentMediaSession;
    };

    const renewToken = async () => {
        try {
            const nextMediaSession = await refreshMediaSession();

            if(nextMediaSession?.token && client?.renewToken) {
                await client.renewToken(nextMediaSession.token);
            }
        }
        catch(error) {
            emit('onReconnectState', 'failed');
        }
    };

    const startQualityTimer = () => {
        if(qualityTimer || ! qualityMonitorIntervalMs) {
            return;
        }

        qualityTimer = window.setInterval(() => {
            if(! client || ! joined) {
                return;
            }

            const rtcStats = client.getRTCStats?.() || {};
            const localStats = client.getLocalAudioStats?.() || {};
            const remoteStatsMap = client.getRemoteAudioStats?.() || {};
            const remoteStats = remoteStatsMap[remoteUid]
                || remoteStatsMap[normalizeAgoraUid(remoteUid)]
                || Object.values(remoteStatsMap)[0]
                || {};
            const rtt = normalizeMilliseconds(pickFirstValue(remoteStats.end2EndDelay, remoteStats.receiveDelay, rtcStats.RTT));
            const jitter = normalizeMilliseconds(pickFirstValue(remoteStats.receiveJitterDelay, remoteStats.jitter));
            const packetLossPercent = normalizePacketLossPercent(pickFirstValue(remoteStats.packetLossRate, remoteStats.receivePacketLossRate, remoteStats.receivePacketLossRatePercent));
            const statsNetworkQuality = classifyNetworkQuality({
                rtt: rtt,
                jitter: jitter,
                packetLossPercent: packetLossPercent
            });
            const sdkNetworkQuality = classifyAgoraNetworkQuality(latestNetworkQuality || client.getNetworkQuality?.());
            const networkQuality = stabilizeNetworkQuality(sdkNetworkQuality || statsNetworkQuality);

            emit('onQualityStats', {
                network_quality: networkQuality,
                issue: networkQuality === 'good' ? null : 'agora_media_quality',
                connection_state: 'connected',
                ice_connection_state: 'connected',
                round_trip_time_ms: rtt,
                jitter_ms: jitter,
                packet_loss_percent: packetLossPercent,
                packets_lost: toNumber(pickFirstValue(remoteStats.receivePacketsLost, localStats.sendPacketsLost), 0),
                packets_received: toNumber(remoteStats.receivePackets, 0),
                bytes_sent: toNumber(localStats.sendBytes, 0),
                bytes_received: toNumber(remoteStats.receiveBytes, 0),
                available_outgoing_bitrate: toNumber(rtcStats.OutgoingAvailableBandwidth, 0),
                audio_level: toNumber(localStats.inputLevel, 0)
            });
        }, qualityMonitorIntervalMs);
    };

    const stopQualityTimer = () => {
        if(qualityTimer) {
            window.clearInterval(qualityTimer);
            qualityTimer = null;
        }
    };

    const applyRemoteOutputVolume = () => {
        const cappedRemoteOutputVolume = Math.min(remoteOutputVolume, Math.round(maximumRemoteOutputVolume * 100));
        const normalizedVolume = Math.max(0, Math.min(1, cappedRemoteOutputVolume / 100));

        try {
            remoteAudioTrack?.setVolume?.(cappedRemoteOutputVolume);
        }
        catch(error) {}

        if(! remoteOutputElement) {
            return;
        }

        try {
            remoteOutputElement.volume = normalizedVolume;
            remoteOutputElement.muted = normalizedVolume <= 0;
        }
        catch(error) {}
    };

    const processRemoteOutputAEC = () => {
        if(! remoteOutputElement || ! AgoraRTCModule?.processExternalMediaAEC) {
            return;
        }

        try {
            AgoraRTCModule.processExternalMediaAEC(remoteOutputElement);
        }
        catch(error) {}
    };

    const clearRemoteOutputAecTimers = () => {
        if(typeof window === 'undefined') {
            remoteOutputAecTimers = [];

            return;
        }

        remoteOutputAecTimers.forEach((timer) => {
            try {
                window.clearTimeout(timer);
            }
            catch(error) {}
        });
        remoteOutputAecTimers = [];
    };

    const refreshRemoteOutputAEC = () => {
        clearRemoteOutputAecTimers();
        processRemoteOutputAEC();

        if(typeof window === 'undefined' || ! remoteOutputElement) {
            return;
        }

        [160, 700].forEach((delayMs) => {
            remoteOutputAecTimers.push(window.setTimeout(() => {
                processRemoteOutputAEC();
            }, delayMs));
        });
    };

    const stopRemoteAudioTrackPlayback = () => {
        try {
            remoteAudioTrack?.stop?.();
        }
        catch(error) {}
    };

    const publishRemoteStream = () => {
        const mediaTrack = remoteAudioTrack?.getMediaStreamTrack?.() || null;

        if(! mediaTrack) {
            remoteStream = null;
            remoteMediaTrackId = null;

            return null;
        }

        const nextTrackId = String(mediaTrack.id || `${remoteUid || 'remote'}:audio`);

        if(! remoteStream || remoteMediaTrackId !== nextTrackId) {
            remoteStream = new MediaStream([mediaTrack]);
            remoteMediaTrackId = nextTrackId;
            emit('onRemoteStream', remoteStream);
        }

        if(mediaTrack) {
            mediaTrack.onmute = () => {
                if(closing || ! remoteAudioTrack) {
                    return;
                }

                emit('onReconnectState', 'reconnecting');
            };

            mediaTrack.onended = () => {
                if(closing || ! remoteAudioTrack) {
                    return;
                }

                emit('onReconnectState', 'reconnecting');
            };

            mediaTrack.onunmute = () => {
                if(closing || ! remoteAudioTrack) {
                    return;
                }

                emit('onReconnectState', 'stable');
            };
        }

        return remoteStream;
    };

    const playRemoteAudio = () => {
        applyRemoteOutputVolume();
        publishRemoteStream();

        if(! remoteOutputElement || ! remoteStream) {
            return;
        }

        try {
            if(remoteOutputElement.srcObject !== remoteStream) {
                remoteOutputElement.srcObject = remoteStream;
            }

            applyRemoteOutputVolume();
            refreshRemoteOutputAEC();
            remoteOutputElement.play?.().catch(() => {});
        }
        catch(error) {}

        stopRemoteAudioTrackPlayback();
    };

    const classifyNetworkQuality = ({ rtt, jitter, packetLossPercent }) => {
        if(packetLossPercent >= 12 || rtt >= 900 || jitter >= 180) {
            return 'poor';
        }

        if(packetLossPercent >= 5 || rtt >= 500 || jitter >= 90) {
            return 'weak';
        }

        return 'good';
    };

    const stabilizeNetworkQuality = (networkQuality) => {
        if(networkQuality === 'poor') {
            consecutivePoorSamples += 1;
            consecutiveWeakSamples += 1;
        }
        else if(networkQuality === 'weak') {
            consecutiveWeakSamples += 1;
            consecutivePoorSamples = 0;
        }
        else {
            consecutiveWeakSamples = 0;
            consecutivePoorSamples = 0;

            return 'good';
        }

        if(networkQuality === 'poor' && consecutivePoorSamples >= qualityWarningSamples) {
            return 'poor';
        }

        if(consecutiveWeakSamples >= qualityWarningSamples) {
            return 'weak';
        }

        return 'good';
    };

    const subscribeToRemoteAudio = async (user) => {
        if(closing || ! user || isLocalAgoraUser(user.uid)) {
            return false;
        }

        if(! user.audioTrack && user.hasAudio === false) {
            return false;
        }

        try {
            if(! user.audioTrack) {
                await withTimeout(
                    client.subscribe(user, 'audio'),
                    agoraTrackTimeoutMs,
                    'Connecting remote audio took too long.'
                );
            }

            if(! user.audioTrack) {
                return false;
            }

            await yieldToBrowser();
            const nextRemoteTrack = user.audioTrack;
            const nextRemoteMediaTrack = nextRemoteTrack?.getMediaStreamTrack?.() || null;
            const localMediaTrack = localAudioTrack?.getMediaStreamTrack?.() || null;

            if(nextRemoteMediaTrack?.id && localMediaTrack?.id === nextRemoteMediaTrack.id) {
                return false;
            }

            const nextRemoteTrackId = String(nextRemoteMediaTrack?.id || `${user.uid || 'remote'}:audio`);
            const isSameRemoteTrack = normalizeAgoraUid(remoteUid) === normalizeAgoraUid(user.uid)
                && remoteMediaTrackId === nextRemoteTrackId
                && remoteAudioTrack === nextRemoteTrack;

            remoteUid = user.uid;

            if(isSameRemoteTrack) {
                playRemoteAudio();
                emit('onConnected');
                startQualityTimer();

                return true;
            }

            stopRemoteAudioTrackPlayback();
            remoteAudioTrack = nextRemoteTrack;
            playRemoteAudio();
            emit('onConnected');
            startQualityTimer();

            return true;
        }
        catch(error) {
            emit('onReconnectState', 'failed');

            return false;
        }
    };

    const subscribeToPublishedRemoteUsers = async () => {
        const remoteUsers = Array.isArray(client?.remoteUsers) ? client.remoteUsers : [];

        for(const user of remoteUsers) {
            if(await subscribeToRemoteAudio(user)) {
                return true;
            }
        }

        return false;
    };

    const stopRemoteUserSweep = () => {
        if(remoteUserSweepTimer) {
            window.clearInterval(remoteUserSweepTimer);
            remoteUserSweepTimer = null;
        }
    };

    const startRemoteUserSweep = () => {
        if(remoteUserSweepTimer || typeof window === 'undefined') {
            return;
        }

        remoteUserSweepTimer = window.setInterval(async () => {
            if(closing || remoteAudioTrack || ! client || ! joined) {
                stopRemoteUserSweep();

                return;
            }

            await subscribeToPublishedRemoteUsers();
        }, agoraRemoteSweepIntervalMs);
    };

    const setupClientEvents = () => {
        client.on('user-joined', async (user) => {
            if(closing) {
                return;
            }

            await subscribeToRemoteAudio(user);
            startRemoteUserSweep();
        });

        client.on('user-published', async (user, mediaType) => {
            if(mediaType === 'audio') {
                const attached = await subscribeToRemoteAudio(user);

                if(! attached) {
                    startRemoteUserSweep();
                }
            }
        });

        client.on('user-info-updated', async () => {
            if(closing || remoteAudioTrack) {
                return;
            }

            await subscribeToPublishedRemoteUsers();
            startRemoteUserSweep();
        });

        client.on('user-unpublished', (user, mediaType) => {
            if(closing) {
                return;
            }

            if(mediaType === 'audio' && normalizeAgoraUid(user.uid) === normalizeAgoraUid(remoteUid)) {
                remoteAudioTrack?.stop?.();
                remoteAudioTrack = null;
                remoteStream = null;
                remoteMediaTrackId = null;
                emit('onRemoteStream', null);
                emit('onReconnectState', 'reconnecting');
                startRemoteUserSweep();
            }
        });

        client.on('user-left', (user) => {
            if(closing) {
                return;
            }

            if(normalizeAgoraUid(user.uid) === normalizeAgoraUid(remoteUid)) {
                remoteAudioTrack?.stop?.();
                remoteAudioTrack = null;
                remoteStream = null;
                remoteMediaTrackId = null;
                emit('onRemoteStream', null);
                emit('onReconnectState', 'reconnecting');
                startRemoteUserSweep();
            }
        });

        client.on('connection-state-change', (currentState) => {
            if(closing) {
                return;
            }

            emit('onStateChange', String(currentState || '').toLowerCase());

            if(currentState === 'CONNECTED') {
                emit('onReconnectState', 'stable');
                subscribeToPublishedRemoteUsers().catch(() => {});
                startRemoteUserSweep();

                return;
            }

            if(['RECONNECTING', 'DISCONNECTED'].includes(currentState)) {
                emit('onReconnectState', 'reconnecting');
                startRemoteUserSweep();

                return;
            }

            if(['FAILED', 'CLOSED'].includes(currentState)) {
                emit('onReconnectState', 'failed');
            }
        });

        client.on('token-privilege-will-expire', renewToken);
        client.on('token-privilege-did-expire', renewToken);
        client.on('network-quality', (quality) => {
            latestNetworkQuality = quality;
        });
    };

    const ensurePeerConnection = async (mediaType = 'audio') => {
        if(joined) {
            return true;
        }

        closing = false;
        const agoraEncoderConfig = resolveAgoraEncoderConfig(currentMediaSession);

        if(mediaType !== 'audio') {
            throw new Error('Only audio calls are available.');
        }

    if(! isAgoraAudioCallSupported()) {
        throw new Error('Agora audio calls are not supported in this browser.');
    }

    try {
        const useCustomTrack = shouldUseCustomVoiceProcessing();

        if(useCustomTrack) {
            rawCaptureStream = await requestMicrophoneWarmup(agoraEncoderConfig.sampleRate);
            throwIfClosing();
            await yieldToBrowser();

            const processedCapture = await createVoiceProcessedStream(rawCaptureStream, agoraEncoderConfig.sampleRate);

            throwIfClosing();

            localCaptureStream = processedCapture?.stream || rawCaptureStream;
            voiceProcessingCleanup = typeof processedCapture?.cleanup === 'function'
                ? processedCapture.cleanup
                : null;
            await yieldToBrowser();
        }

        const AgoraRTC = await withTimeout(
            loadAgoraRTC(),
            agoraSdkLoadTimeoutMs,
                'Audio engine took too long to load.'
            );
            throwIfClosing();
            AgoraRTCModule = AgoraRTC;

            if(! AgoraRTC?.createClient) {
                throw new Error('Agora audio SDK could not be loaded.');
            }

            if(! currentMediaSession?.app_id || ! currentMediaSession?.channel) {
                throw new Error('Agora call media is not configured.');
            }

            applyAgoraRegionPreference(AgoraRTC, currentMediaSession);

            client = AgoraRTC.createClient({
                mode: 'rtc',
                codec: 'vp8'
            });
            setupClientEvents();

            localAgoraUid = await withTimeout(
                client.join(
                    currentMediaSession.app_id,
                    currentMediaSession.channel,
                    currentMediaSession.token || null,
                    currentMediaSession.uid || null
                ),
                agoraJoinTimeoutMs,
                'Joining the call took too long.'
            );
            throwIfClosing();
            await yieldToBrowser();

            const localTrackResult = await createMicrophoneTrackWithFallback(AgoraRTC, localCaptureStream, {
                preferCustomTrack: useCustomTrack,
                encoderConfig: agoraEncoderConfig
            });
            throwIfClosing();
            await yieldToBrowser();

            localAudioTrack = localTrackResult?.track || null;
            localCaptureStream = localTrackResult?.captureStream || localCaptureStream;
            applyLocalPublishVolume(localAudioTrack);
            aiDenoiserCleanup = await enableAiDenoiserForTrack(AgoraRTC, localAudioTrack);

            if(! localTrackResult?.captureStream) {
                disposeLocalCaptureStreams();
            }

            localStream = streamFromTrack(localAudioTrack);

            if(localStream) {
                emit('onLocalStream', localStream);
            }

            await applyLocalMuteState();

            await withTimeout(
                client.publish([localAudioTrack]),
                agoraPublishTimeoutMs,
                'Publishing microphone audio took too long.'
            );
            throwIfClosing();
            await yieldToBrowser();
            joined = true;
            emit('onStateChange', 'connected');
            await subscribeToPublishedRemoteUsers();
            throwIfClosing();
            startRemoteUserSweep();
            startQualityTimer();

            return true;
        }
        catch(error) {
            close();

            throw userFriendlyAgoraMediaError(error);
        }
    };

    const close = () => {
        closing = true;
        stopQualityTimer();
        stopRemoteUserSweep();
        clearRemoteOutputAecTimers();

        try {
            remoteAudioTrack?.stop?.();
        }
        catch(error) {}

        try {
            aiDenoiserCleanup?.();
            aiDenoiserCleanup = null;
        }
        catch(error) {}

        try {
            localAudioTrack?.close?.();
        }
        catch(error) {}

        disposeLocalCaptureStreams();

        const activeClient = client;

        client = null;
        localAudioTrack = null;
        remoteAudioTrack = null;
        localStream = null;
        remoteStream = null;
        remoteMediaTrackId = null;
        remoteUid = null;
        localAgoraUid = null;
        remoteOutputElement = null;
        joined = false;
        latestNetworkQuality = null;
        consecutiveWeakSamples = 0;
        consecutivePoorSamples = 0;

        if(activeClient) {
            Promise.resolve()
                .then(() => activeClient.leave?.())
                .catch(() => {});
        }

        emit('onRemoteStream', null);
    };

    return {
        ensurePeerConnection: ensurePeerConnection,
        createOffer: async () => true,
        handleOffer: async () => true,
        handleAnswer: async () => true,
        handleIce: async () => true,
        setMuted: async (muted) => {
            isMuted = Boolean(muted);
            await applyLocalMuteState();
        },
        setRemoteOutputVolume: (volume) => {
            const normalizedVolume = Number(volume);
            const maximumVolumePercentage = Math.round(maximumRemoteOutputVolume * 100);

            remoteOutputVolume = Math.max(0, Math.min(
                maximumVolumePercentage,
                Math.round((Number.isFinite(normalizedVolume) ? normalizedVolume : 1) * 100)
            ));
            applyRemoteOutputVolume();
        },
        attachRemoteOutputElement: (element) => {
            remoteOutputElement = element || null;
            refreshRemoteOutputAEC();
            applyRemoteOutputVolume();

            if(remoteAudioTrack && ! remoteStream) {
                publishRemoteStream();
            }

            if(remoteAudioTrack) {
                playRemoteAudio();
            }
        },
        refreshRemoteAudio: async () => {
            if(closing || ! client || ! joined) {
                return false;
            }

            const attached = await subscribeToPublishedRemoteUsers();

            if(! attached) {
                startRemoteUserSweep();
            }

            return attached;
        },
        close: close
    };
};

export { createAgoraAudioCallPeer, isAgoraAudioCallSupported, warmAgoraAudioCallEngine };
