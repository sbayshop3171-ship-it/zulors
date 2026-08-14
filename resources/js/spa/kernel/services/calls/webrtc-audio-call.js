const defaultIceServers = [
    { urls: 'stun:stun.l.google.com:19302' }
];
const defaultAudioBitrate = 24000;
const defaultLowBandwidthAudioBitrate = 18000;
const defaultMinimumAudioBitrate = 16000;
const preferredSampleRate = 16000;
const preferredAudioLatency = 0.02;
const defaultQualityMonitorIntervalMs = 3000;
const defaultReconnectGraceMs = 40000;
const defaultGetUserMediaTimeoutMs = 15000;
const defaultSessionDescriptionTimeoutMs = 12000;
const defaultStatsTimeoutMs = 5000;
const defaultUiYieldDelayMs = 16;

const parseBooleanEnv = (value, defaultValue = true) => {
    if(value === undefined || value === null || value === '') {
        return defaultValue;
    }

    return ! ['false', '0', 'off', 'no'].includes(String(value).toLowerCase());
};

const parsePositiveInteger = (value, defaultValue) => {
    const parsedValue = Number.parseInt(value, 10);

    return Number.isFinite(parsedValue) && parsedValue > 0 ? parsedValue : defaultValue;
};

const clampAudioBitrate = (value, defaultValue, minimum, maximum) => {
    const bitrate = parsePositiveInteger(value, defaultValue);

    return Math.max(minimum, Math.min(maximum, bitrate));
};

const normalizePreferredSampleRate = (value, defaultValue = preferredSampleRate) => {
    const sampleRate = parsePositiveInteger(value, defaultValue);

    return [16000, 32000, 48000].includes(sampleRate)
        ? sampleRate
        : defaultValue;
};

const minimumAudioBitrate = clampAudioBitrate(
    import.meta.env.VITE_CALL_MIN_AUDIO_BITRATE,
    defaultMinimumAudioBitrate,
    12000,
    defaultMinimumAudioBitrate
);
const lowBandwidthAudioBitrate = clampAudioBitrate(
    import.meta.env.VITE_CALL_LOW_BANDWIDTH_AUDIO_BITRATE,
    defaultLowBandwidthAudioBitrate,
    minimumAudioBitrate,
    defaultLowBandwidthAudioBitrate
);
const preferredAudioBitrate = clampAudioBitrate(
    import.meta.env.VITE_CALL_AUDIO_BITRATE,
    defaultAudioBitrate,
    lowBandwidthAudioBitrate,
    defaultAudioBitrate
);
const preferredAudioSampleRate = normalizePreferredSampleRate(import.meta.env.VITE_CALL_AUDIO_SAMPLE_RATE);
const qualityMonitorIntervalMs = parsePositiveInteger(
    import.meta.env.VITE_CALL_QUALITY_MONITOR_INTERVAL,
    defaultQualityMonitorIntervalMs
);
const qualityWarningSamples = Math.max(1, parsePositiveInteger(
    import.meta.env.VITE_CALL_QUALITY_WARNING_SAMPLES,
    2
));
const reconnectGraceMs = parsePositiveInteger(
    import.meta.env.VITE_CALL_RECONNECT_GRACE_MS,
    defaultReconnectGraceMs
);
const getUserMediaTimeoutMs = parsePositiveInteger(
    import.meta.env.VITE_CALL_GET_USER_MEDIA_TIMEOUT_MS,
    defaultGetUserMediaTimeoutMs
);
const sessionDescriptionTimeoutMs = parsePositiveInteger(
    import.meta.env.VITE_CALL_SDP_TIMEOUT_MS,
    defaultSessionDescriptionTimeoutMs
);
const statsTimeoutMs = parsePositiveInteger(
    import.meta.env.VITE_CALL_STATS_TIMEOUT_MS,
    defaultStatsTimeoutMs
);
const enableVoiceProcessing = parseBooleanEnv(import.meta.env.VITE_CALL_AUDIO_PROCESSING, false);
const enableNativeAppVoiceProcessing = parseBooleanEnv(import.meta.env.VITE_CALL_NATIVE_APP_AUDIO_PROCESSING, false);

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

const preferredIceCandidatePoolSize = () => {
    if(hasNativeCallAudioBridge() || isLikelyMobileCallClient()) {
        return 0;
    }

    return 4;
};

const createTimeoutError = (message) => {
    const error = new Error(message || 'Operation timed out.');

    error.name = 'TimeoutError';

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

const yieldToBrowser = async () => {
    if(typeof window === 'undefined') {
        return;
    }

    await new Promise((resolve) => {
        window.requestAnimationFrame?.(() => {
            window.setTimeout(resolve, defaultUiYieldDelayMs);
        }) || window.setTimeout(resolve, defaultUiYieldDelayMs);
    });
};

const normalizeIceServers = (config) => {
    if(Array.isArray(config) && config.length) {
        return config;
    }

    return null;
};

const parseIceServers = (iceServers = null) => {
    const providedServers = normalizeIceServers(iceServers);

    if(providedServers) {
        return providedServers;
    }

    const rawConfig = import.meta.env.VITE_CALL_ICE_SERVERS;

    if(! rawConfig) {
        return defaultIceServers;
    }

    try {
        const parsedConfig = JSON.parse(rawConfig);

        const parsedServers = normalizeIceServers(parsedConfig);

        if(parsedServers) {
            return parsedServers;
        }
    }
    catch(error) {}

    const urls = String(rawConfig)
        .split(',')
        .map((url) => url.trim())
        .filter(Boolean);

    return urls.length ? urls.map((url) => ({ urls: url })) : defaultIceServers;
};

const isAudioCallSupported = () => {
    return Boolean(
        window.RTCPeerConnection
        && navigator.mediaDevices
        && typeof navigator.mediaDevices.getUserMedia === 'function'
    );
};

const normalizeSessionDescription = (description) => {
    if(! description?.sdp) {
        return description;
    }

    const sdp = String(description.sdp)
        .replace(/\r\n|\r|\n/g, '\r\n')
        .replace(/(\r\n)+$/g, '');

    return {
        type: description.type,
        sdp: `${sdp}\r\n`
    };
};

const mergeFmtpParameters = (existingParams = '') => {
    const params = new Map();

    String(existingParams)
        .split(';')
        .map((param) => param.trim())
        .filter(Boolean)
        .forEach((param) => {
            const [key, ...valueParts] = param.split('=');

            if(key) {
                params.set(key, valueParts.join('=') || '1');
            }
        });

    params.set('minptime', '10');
    params.set('useinbandfec', '1');
    params.set('usedtx', '1');
    params.set('maxaveragebitrate', String(preferredAudioBitrate));
    params.set('stereo', '0');
    params.set('sprop-stereo', '0');

    return Array.from(params.entries())
        .map(([key, value]) => `${key}=${value}`)
        .join(';');
};

const tuneOpusSessionDescription = (description) => {
    const normalizedDescription = normalizeSessionDescription(description);

    if(! normalizedDescription?.sdp) {
        return normalizedDescription;
    }

    const opusMatch = normalizedDescription.sdp.match(/a=rtpmap:(\d+) opus\/48000(?:\/2)?\r\n/i);

    if(! opusMatch) {
        return normalizedDescription;
    }

    const opusPayload = opusMatch[1];
    let sdp = normalizedDescription.sdp;

    sdp = sdp.replace(/^m=audio ([^\r\n]+)/m, (line, audioLine) => {
        const parts = audioLine.trim().split(/\s+/);

        if(parts.length < 3) {
            return line;
        }

        const [port, protocol, ...payloads] = parts;
        const preferredPayloads = [
            opusPayload,
            ...payloads.filter((payload) => payload !== opusPayload)
        ];

        return `m=audio ${port} ${protocol} ${preferredPayloads.join(' ')}`;
    });

    const fmtpRegex = new RegExp(`a=fmtp:${opusPayload} ([^\\r\\n]*)\\r\\n`, 'i');

    if(fmtpRegex.test(sdp)) {
        sdp = sdp.replace(fmtpRegex, (line, params) => {
            return `a=fmtp:${opusPayload} ${mergeFmtpParameters(params)}\r\n`;
        });
    }
    else {
        sdp = sdp.replace(opusMatch[0], `${opusMatch[0]}a=fmtp:${opusPayload} ${mergeFmtpParameters()}\r\n`);
    }

    if(! /\r\na=ptime:\d+\r\n/i.test(sdp)) {
        sdp = sdp.replace(opusMatch[0], `${opusMatch[0]}a=ptime:20\r\n`);
    }

    if(! /\r\na=maxptime:\d+\r\n/i.test(sdp)) {
        sdp = sdp.replace(opusMatch[0], `${opusMatch[0]}a=maxptime:60\r\n`);
    }

    return {
        type: normalizedDescription.type,
        sdp: sdp
    };
};

const userFriendlyMediaError = (error) => {
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

    return new Error(errorMessage || 'Unable to start microphone.');
};

const requestLocalMediaStream = async (mediaType = 'audio') => {
    const wantsVideo = mediaType === 'video';
    const voiceAudioConstraints = {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
        channelCount: { ideal: 1 },
        sampleRate: { ideal: preferredAudioSampleRate },
        sampleSize: { ideal: 16 },
        latency: { ideal: preferredAudioLatency },
        voiceIsolation: { ideal: true },
        googEchoCancellation: true,
        googEchoCancellation2: true,
        googDAEchoCancellation: true,
        googAutoGainControl: true,
        googAutoGainControl2: true,
        googNoiseSuppression: true,
        googNoiseSuppression2: true,
        googHighpassFilter: true,
        googTypingNoiseDetection: true
    };
    const useLightweightConstraints = hasNativeCallAudioBridge() || isLikelyMobileCallClient();
    const attempts = useLightweightConstraints
        ? [
            {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                },
                video: wantsVideo
            },
            {
                audio: true,
                video: wantsVideo
            },
            {
                audio: true,
                video: false
            }
        ]
        : [
            {
                audio: voiceAudioConstraints,
                video: wantsVideo
            },
            {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                },
                video: wantsVideo
            },
            {
                audio: true,
                video: wantsVideo
            },
            {
                audio: true,
                video: false
            }
        ];
    let lastError = null;

    for(const constraints of attempts) {
        try {
            return await withTimeout(
                navigator.mediaDevices.getUserMedia(constraints),
                getUserMediaTimeoutMs,
                'Microphone permission request timed out.'
            );
        }
        catch(error) {
            lastError = error;

            if(['NotAllowedError', 'PermissionDeniedError', 'SecurityError', 'NotFoundError', 'DevicesNotFoundError', 'TimeoutError'].includes(error?.name || '')) {
                break;
            }
        }
    }

    throw userFriendlyMediaError(lastError);
};

const applySpeechTrackHints = (stream) => {
    stream?.getAudioTracks()?.forEach((track) => {
        try {
            track.contentHint = 'speech';
        }
        catch(error) {}
    });
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

const createInteractiveAudioContext = () => {
    const AudioContext = window.AudioContext || window.webkitAudioContext;

    if(! AudioContext) {
        return null;
    }

    try {
        return new AudioContext({
            latencyHint: 'interactive',
            sampleRate: preferredAudioSampleRate
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

const createVoiceProcessedStream = async (rawStream) => {
    const audioTracks = rawStream?.getAudioTracks?.() || [];

    if(
        ! enableVoiceProcessing
        || ! audioTracks.length
        || isLikelyMobileCallClient()
        || (hasNativeCallAudioBridge() && ! enableNativeAppVoiceProcessing)
    ) {
        return {
            stream: rawStream,
            cleanup: () => {}
        };
    }

    const context = createInteractiveAudioContext();

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

        gain.gain.value = 1;

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

const safeNumber = (value, precision = 3) => {
    const numericValue = Number(value);

    if(! Number.isFinite(numericValue)) {
        return null;
    }

    const multiplier = 10 ** precision;

    return Math.round(numericValue * multiplier) / multiplier;
};

const clampBitrate = (bitrate) => {
    return Math.max(minimumAudioBitrate, Math.min(preferredAudioBitrate, Number(bitrate || preferredAudioBitrate)));
};

const configureAudioSender = async (sender, bitrate = preferredAudioBitrate) => {
    if(sender?.track?.kind !== 'audio' || typeof sender.getParameters !== 'function') {
        return;
    }

    try {
        const parameters = sender.getParameters();

        parameters.encodings = parameters.encodings?.length ? parameters.encodings : [{}];
        parameters.encodings[0].maxBitrate = clampBitrate(bitrate);

        if('priority' in parameters.encodings[0]) {
            parameters.encodings[0].priority = 'high';
        }

        await sender.setParameters(parameters);
    }
    catch(error) {}
};

const classifyNetworkQuality = (stats = {}) => {
    if(['failed', 'closed'].includes(stats.connection_state) || ['failed', 'closed'].includes(stats.ice_connection_state)) {
        return {
            quality: 'poor',
            issue: 'connection_failed'
        };
    }

    if(['disconnected'].includes(stats.connection_state) || ['disconnected'].includes(stats.ice_connection_state)) {
        return {
            quality: 'reconnecting',
            issue: 'reconnecting'
        };
    }

    const packetLoss = Number(stats.packet_loss_percent || 0);
    const jitterMs = Number(stats.jitter_ms || 0);
    const roundTripTimeMs = Number(stats.round_trip_time_ms || 0);
    const availableBitrate = Number(stats.available_outgoing_bitrate || 0);

    if(packetLoss >= 8 || jitterMs >= 90 || roundTripTimeMs >= 800) {
        return {
            quality: 'poor',
            issue: 'media_quality_poor'
        };
    }

    if(packetLoss >= 3 || jitterMs >= 45 || roundTripTimeMs >= 350 || (availableBitrate > 0 && availableBitrate < 42000)) {
        return {
            quality: 'weak',
            issue: 'media_quality_weak'
        };
    }

    return {
        quality: 'good',
        issue: null
    };
};

const createAudioCallPeer = (callbacks = {}, options = {}) => {
    let peerConnection = null;
    let localStream = null;
    let rawLocalStream = null;
    let remoteStream = null;
    let voiceProcessingCleanup = null;
    let pendingIceCandidates = [];
    let audioSenders = [];
    let qualityMonitorTimer = null;
    let reconnectTimer = null;
    let currentNetworkQuality = 'unknown';
    let consecutiveWeakSamples = 0;
    let consecutivePoorSamples = 0;
    let connectedNotified = false;
    let isClosed = false;
    let activeRemoteAudioTrackId = null;

    const emit = (name, ...args) => {
        if(isClosed) {
            return;
        }

        if(typeof callbacks[name] === 'function') {
            callbacks[name](...args);
        }
    };

    const flushPendingIceCandidates = async () => {
        if(! peerConnection?.remoteDescription || ! pendingIceCandidates.length) {
            return;
        }

        const candidates = [...pendingIceCandidates];
        pendingIceCandidates = [];

        for(const candidate of candidates) {
            try {
                await withTimeout(
                    peerConnection.addIceCandidate(candidate),
                    sessionDescriptionTimeoutMs,
                    'Applying buffered network candidates took too long.'
                );
            }
            catch(error) {}
        }
    };

    const notifyConnected = () => {
        if(connectedNotified) {
            return;
        }

        connectedNotified = true;
        emit('onConnected');
    };

    const applyAdaptiveAudioBitrate = (networkQuality) => {
        const bitrate = networkQuality === 'poor' || networkQuality === 'reconnecting'
            ? minimumAudioBitrate
            : (networkQuality === 'weak' ? lowBandwidthAudioBitrate : preferredAudioBitrate);

        audioSenders.forEach((sender) => {
            configureAudioSender(sender, bitrate);
        });
    };
    const stabilizeNetworkQuality = (networkQuality) => {
        if(networkQuality === 'poor') {
            consecutivePoorSamples += 1;
            consecutiveWeakSamples += 1;

            return consecutivePoorSamples >= qualityWarningSamples ? 'poor' : 'good';
        }

        if(networkQuality === 'weak') {
            consecutiveWeakSamples += 1;
            consecutivePoorSamples = 0;

            return consecutiveWeakSamples >= qualityWarningSamples ? 'weak' : 'good';
        }

        consecutiveWeakSamples = 0;
        consecutivePoorSamples = 0;

        return 'good';
    };

    const collectQualityStats = async () => {
        if(! peerConnection || typeof peerConnection.getStats !== 'function') {
            return null;
        }

        const stats = {
            connection_state: peerConnection.connectionState || 'unknown',
            ice_connection_state: peerConnection.iceConnectionState || 'unknown',
            round_trip_time_ms: null,
            jitter_ms: null,
            packets_lost: 0,
            packets_received: 0,
            packet_loss_percent: 0,
            bytes_sent: 0,
            bytes_received: 0,
            available_outgoing_bitrate: null,
            audio_level: null
        };

        try {
            const report = await withTimeout(
                peerConnection.getStats(),
                statsTimeoutMs,
                'Collecting call stats took too long.'
            );

            report.forEach((item) => {
                if(item.type === 'candidate-pair' && item.state === 'succeeded' && (item.selected || item.nominated)) {
                    stats.round_trip_time_ms = safeNumber((item.currentRoundTripTime || item.totalRoundTripTime || 0) * 1000, 1);
                    stats.available_outgoing_bitrate = safeNumber(item.availableOutgoingBitrate, 0);
                }

                if(item.type === 'inbound-rtp' && (item.kind === 'audio' || item.mediaType === 'audio')) {
                    stats.packets_lost += Number(item.packetsLost || 0);
                    stats.packets_received += Number(item.packetsReceived || 0);
                    stats.bytes_received += Number(item.bytesReceived || 0);

                    if(item.jitter !== undefined) {
                        stats.jitter_ms = safeNumber(Number(item.jitter || 0) * 1000, 1);
                    }
                }

                if(item.type === 'outbound-rtp' && (item.kind === 'audio' || item.mediaType === 'audio')) {
                    stats.bytes_sent += Number(item.bytesSent || 0);
                }

                if(['media-source', 'track'].includes(item.type) && (item.kind === 'audio' || item.mediaType === 'audio') && item.audioLevel !== undefined) {
                    stats.audio_level = safeNumber(item.audioLevel, 3);
                }
            });

            const totalPackets = stats.packets_lost + stats.packets_received;

            if(totalPackets > 0) {
                stats.packet_loss_percent = safeNumber((stats.packets_lost / totalPackets) * 100, 2);
            }

            const networkQuality = classifyNetworkQuality(stats);
            const stabilizedQuality = stabilizeNetworkQuality(networkQuality.quality);

            stats.network_quality = stabilizedQuality;
            stats.issue = stabilizedQuality === 'good' ? null : networkQuality.issue;

            if(currentNetworkQuality !== stats.network_quality) {
                currentNetworkQuality = stats.network_quality;
                applyAdaptiveAudioBitrate(stats.network_quality);
            }

            emit('onQualityStats', stats);

            return stats;
        }
        catch(error) {
            return null;
        }
    };

    const startQualityMonitor = () => {
        if(qualityMonitorTimer || qualityMonitorIntervalMs <= 0) {
            return;
        }

        collectQualityStats();
        qualityMonitorTimer = window.setInterval(collectQualityStats, qualityMonitorIntervalMs);
    };

    const stopQualityMonitor = () => {
        if(qualityMonitorTimer) {
            window.clearInterval(qualityMonitorTimer);
            qualityMonitorTimer = null;
        }
    };

    const clearReconnectTimer = () => {
        if(reconnectTimer) {
            window.clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
    };

    const handleReconnectState = () => {
        const connectionState = peerConnection?.connectionState || '';
        const iceConnectionState = peerConnection?.iceConnectionState || '';
        const isConnected = ['connected', 'completed'].includes(connectionState) || ['connected', 'completed'].includes(iceConnectionState);
        const isReconnecting = ['disconnected'].includes(connectionState) || ['disconnected'].includes(iceConnectionState);
        const isFailed = ['failed', 'closed'].includes(connectionState) || ['failed', 'closed'].includes(iceConnectionState);

        if(isConnected) {
            clearReconnectTimer();
            emit('onReconnectState', 'stable');
            notifyConnected();

            return;
        }

        if(isFailed) {
            clearReconnectTimer();
            emit('onReconnectState', 'failed');

            return;
        }

        if(isReconnecting) {
            emit('onReconnectState', 'reconnecting');

            if(! reconnectTimer) {
                reconnectTimer = window.setTimeout(() => {
                    reconnectTimer = null;
                    emit('onReconnectState', 'failed');
                }, reconnectGraceMs);
            }
        }
    };

    const replaceRemoteAudioTrack = (track) => {
        if(! track || track.kind !== 'audio' || ! remoteStream) {
            return;
        }

        if(activeRemoteAudioTrackId === track.id && remoteStream.getAudioTracks().some((remoteTrack) => remoteTrack.id === track.id)) {
            return;
        }

        remoteStream.getAudioTracks().forEach((remoteTrack) => {
            if(remoteTrack.id === track.id) {
                return;
            }

            try {
                remoteStream.removeTrack(remoteTrack);
            }
            catch(error) {}

            try {
                remoteTrack.stop?.();
            }
            catch(error) {}
        });

        if(! remoteStream.getAudioTracks().some((remoteTrack) => remoteTrack.id === track.id)) {
            remoteStream.addTrack(track);
        }

        activeRemoteAudioTrackId = track.id;

        track.onmute = () => {
            if(isClosed || activeRemoteAudioTrackId !== track.id) {
                return;
            }

            emit('onReconnectState', 'reconnecting');
        };

        track.onended = () => {
            if(isClosed || activeRemoteAudioTrackId !== track.id) {
                return;
            }

            emit('onReconnectState', 'reconnecting');
        };

        track.onunmute = () => {
            if(isClosed || activeRemoteAudioTrackId !== track.id) {
                return;
            }

            clearReconnectTimer();
            emit('onReconnectState', 'stable');
        };
    };

    const ensurePeerConnection = async (mediaType = 'audio') => {
        if(peerConnection) {
            return peerConnection;
        }

        isClosed = false;

        if(! isAudioCallSupported()) {
            throw new Error('Audio call is not supported in this browser.');
        }

        rawLocalStream = await requestLocalMediaStream(mediaType);
        await yieldToBrowser();

        if(isClosed) {
            stopMediaStream(rawLocalStream);
            rawLocalStream = null;

            throw new Error('Call already ended.');
        }

        applySpeechTrackHints(rawLocalStream);

        const processedStream = await createVoiceProcessedStream(rawLocalStream);
        await yieldToBrowser();

        localStream = processedStream.stream;
        voiceProcessingCleanup = processedStream.cleanup;

        if(isClosed) {
            voiceProcessingCleanup?.();
            stopMediaStream(localStream);
            stopMediaStream(rawLocalStream);
            localStream = null;
            rawLocalStream = null;
            voiceProcessingCleanup = null;

            throw new Error('Call already ended.');
        }

        remoteStream = new MediaStream();
        peerConnection = new RTCPeerConnection({
            iceServers: parseIceServers(options.iceServers),
            bundlePolicy: 'max-bundle',
            rtcpMuxPolicy: 'require',
            iceCandidatePoolSize: preferredIceCandidatePoolSize()
        });

        localStream.getTracks().forEach((track) => {
            if(track.kind === 'audio') {
                try {
                    track.contentHint = 'speech';
                }
                catch(error) {}
            }

            const sender = peerConnection.addTrack(track, localStream);

            if(track.kind === 'audio') {
                audioSenders.push(sender);
            }

            configureAudioSender(sender);
        });

        peerConnection.onicecandidate = (event) => {
            if(isClosed) {
                return;
            }

            if(event.candidate) {
                emit('onSignal', 'ice', {
                    candidate: event.candidate.toJSON()
                });
            }
        };

        peerConnection.ontrack = (event) => {
            if(isClosed) {
                return;
            }

            const tracks = event.streams?.[0]?.getTracks?.()?.length
                ? event.streams[0].getTracks()
                : [event.track].filter(Boolean);

            tracks.forEach((track) => {
                if(track.kind === 'audio') {
                    replaceRemoteAudioTrack(track);

                    return;
                }

                if(! remoteStream.getTracks().some((remoteTrack) => remoteTrack.id === track.id)) {
                    remoteStream.addTrack(track);
                }
            });

            emit('onRemoteStream', remoteStream);
        };

        peerConnection.onconnectionstatechange = () => {
            if(isClosed) {
                return;
            }

            emit('onStateChange', peerConnection.connectionState);
            handleReconnectState();
        };

        peerConnection.oniceconnectionstatechange = () => {
            if(isClosed) {
                return;
            }

            handleReconnectState();
        };

        emit('onLocalStream', localStream);
        startQualityMonitor();

        return peerConnection;
    };

    const createOffer = async (mediaType = 'audio') => {
        const pc = await ensurePeerConnection(mediaType);
        const offer = await withTimeout(pc.createOffer({
            offerToReceiveAudio: true,
            offerToReceiveVideo: mediaType === 'video'
        }), sessionDescriptionTimeoutMs, 'Creating the call offer took too long.');

        await withTimeout(
            pc.setLocalDescription(new RTCSessionDescription(tuneOpusSessionDescription(offer))),
            sessionDescriptionTimeoutMs,
            'Preparing the local call offer took too long.'
        );
        emit('onSignal', 'offer', normalizeSessionDescription(pc.localDescription.toJSON()));
    };

    const handleOffer = async (offer, mediaType = 'audio') => {
        const pc = await ensurePeerConnection(mediaType);

        await withTimeout(
            pc.setRemoteDescription(new RTCSessionDescription(normalizeSessionDescription(offer))),
            sessionDescriptionTimeoutMs,
            'Receiving the incoming call offer took too long.'
        );
        await flushPendingIceCandidates();

        const answer = await withTimeout(
            pc.createAnswer(),
            sessionDescriptionTimeoutMs,
            'Creating the call answer took too long.'
        );

        await withTimeout(
            pc.setLocalDescription(new RTCSessionDescription(tuneOpusSessionDescription(answer))),
            sessionDescriptionTimeoutMs,
            'Preparing the local call answer took too long.'
        );
        emit('onSignal', 'answer', normalizeSessionDescription(pc.localDescription.toJSON()));
    };

    const handleAnswer = async (answer) => {
        if(! peerConnection || peerConnection.signalingState === 'stable') {
            return;
        }

        await withTimeout(
            peerConnection.setRemoteDescription(new RTCSessionDescription(normalizeSessionDescription(answer))),
            sessionDescriptionTimeoutMs,
            'Applying the remote call answer took too long.'
        );
        await flushPendingIceCandidates();
    };

    const handleIce = async (payload) => {
        const candidatePayload = payload?.candidate || payload;

        if(! candidatePayload) {
            return;
        }

        const candidate = new RTCIceCandidate(candidatePayload);

        if(! peerConnection?.remoteDescription) {
            pendingIceCandidates.push(candidate);

            return;
        }

        try {
            await withTimeout(
                peerConnection.addIceCandidate(candidate),
                sessionDescriptionTimeoutMs,
                'Applying a network candidate took too long.'
            );
        }
        catch(error) {}
    };

    const handleIceBatch = async (signals = []) => {
        const safeSignals = Array.isArray(signals) ? signals : [signals];

        for(const signal of safeSignals) {
            if(isClosed) {
                break;
            }

            await handleIce(signal);

            if(safeSignals.length > 1) {
                await yieldToBrowser(0);
            }
        }

        return true;
    };

    const setMuted = (isMuted) => {
        localStream?.getAudioTracks()?.forEach((track) => {
            track.enabled = ! isMuted;
        });
        rawLocalStream?.getAudioTracks()?.forEach((track) => {
            track.enabled = ! isMuted;
        });
    };

    const close = () => {
        isClosed = true;
        stopQualityMonitor();
        clearReconnectTimer();

        const pc = peerConnection;
        peerConnection = null;

        try {
            pc?.getSenders?.()?.forEach((sender) => {
                try {
                    const replacement = sender.replaceTrack?.(null);

                    replacement?.catch?.(() => {});
                }
                catch(error) {}

                sender.track?.stop();
            });

            pc?.getReceivers?.()?.forEach((receiver) => {
                receiver.track?.stop();
            });

            pc?.getTransceivers?.()?.forEach((transceiver) => {
                try {
                    transceiver.stop?.();
                }
                catch(error) {}
            });

            if(pc) {
                pc.onicecandidate = null;
                pc.ontrack = null;
                pc.onconnectionstatechange = null;
                pc.oniceconnectionstatechange = null;
                pc.close();
            }
        }
        catch(error) {}

        voiceProcessingCleanup?.();
        stopMediaStream(localStream);
        stopMediaStream(rawLocalStream);
        stopMediaStream(remoteStream);

        localStream = null;
        rawLocalStream = null;
        remoteStream = null;
        voiceProcessingCleanup = null;
        pendingIceCandidates = [];
        audioSenders = [];
        currentNetworkQuality = 'unknown';
        consecutiveWeakSamples = 0;
        consecutivePoorSamples = 0;
        connectedNotified = false;
        activeRemoteAudioTrackId = null;
    };

    return {
        createOffer,
        handleOffer,
        handleAnswer,
        handleIce,
        handleIceBatch,
        ensurePeerConnection,
        setMuted,
        close,
        isSupported: isAudioCallSupported
    };
};

export { createAudioCallPeer, isAudioCallSupported };
