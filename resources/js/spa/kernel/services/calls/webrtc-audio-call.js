const defaultIceServers = [
    { urls: 'stun:stun.l.google.com:19302' }
];
const defaultAudioBitrate = 64000;
const defaultLowBandwidthAudioBitrate = 32000;
const defaultMinimumAudioBitrate = 24000;
const preferredSampleRate = 48000;
const preferredAudioLatency = 0.02;
const defaultQualityMonitorIntervalMs = 3000;
const defaultReconnectGraceMs = 10000;

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

const preferredAudioBitrate = parsePositiveInteger(
    import.meta.env.VITE_CALL_AUDIO_BITRATE,
    defaultAudioBitrate
);
const lowBandwidthAudioBitrate = parsePositiveInteger(
    import.meta.env.VITE_CALL_LOW_BANDWIDTH_AUDIO_BITRATE,
    defaultLowBandwidthAudioBitrate
);
const minimumAudioBitrate = parsePositiveInteger(
    import.meta.env.VITE_CALL_MIN_AUDIO_BITRATE,
    defaultMinimumAudioBitrate
);
const qualityMonitorIntervalMs = parsePositiveInteger(
    import.meta.env.VITE_CALL_QUALITY_MONITOR_INTERVAL,
    defaultQualityMonitorIntervalMs
);
const reconnectGraceMs = parsePositiveInteger(
    import.meta.env.VITE_CALL_RECONNECT_GRACE_MS,
    defaultReconnectGraceMs
);
const enableVoiceProcessing = parseBooleanEnv(import.meta.env.VITE_CALL_AUDIO_PROCESSING, true);

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
        echoCancellation: { ideal: true },
        noiseSuppression: { ideal: true },
        autoGainControl: { ideal: true },
        channelCount: { ideal: 1 },
        sampleRate: { ideal: preferredSampleRate },
        sampleSize: { ideal: 16 },
        latency: { ideal: preferredAudioLatency }
    };
    const attempts = [
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
            return await navigator.mediaDevices.getUserMedia(constraints);
        }
        catch(error) {
            lastError = error;
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

const createInteractiveAudioContext = () => {
    const AudioContext = window.AudioContext || window.webkitAudioContext;

    if(! AudioContext) {
        return null;
    }

    try {
        return new AudioContext({
            latencyHint: 'interactive',
            sampleRate: preferredSampleRate
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

    if(! enableVoiceProcessing || ! audioTracks.length) {
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
    let connectedNotified = false;

    const emit = (name, ...args) => {
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
                await peerConnection.addIceCandidate(candidate);
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
            const report = await peerConnection.getStats();

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

            stats.network_quality = networkQuality.quality;
            stats.issue = networkQuality.issue;

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

    const ensurePeerConnection = async (mediaType = 'audio') => {
        if(peerConnection) {
            return peerConnection;
        }

        if(! isAudioCallSupported()) {
            throw new Error('Audio call is not supported in this browser.');
        }

        rawLocalStream = await requestLocalMediaStream(mediaType);
        applySpeechTrackHints(rawLocalStream);

        const processedStream = await createVoiceProcessedStream(rawLocalStream);

        localStream = processedStream.stream;
        voiceProcessingCleanup = processedStream.cleanup;

        remoteStream = new MediaStream();
        peerConnection = new RTCPeerConnection({
            iceServers: parseIceServers(options.iceServers),
            bundlePolicy: 'max-bundle',
            rtcpMuxPolicy: 'require',
            iceCandidatePoolSize: 4
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
            if(event.candidate) {
                emit('onSignal', 'ice', {
                    candidate: event.candidate.toJSON()
                });
            }
        };

        peerConnection.ontrack = (event) => {
            event.streams?.[0]?.getTracks()?.forEach((track) => {
                if(! remoteStream.getTracks().some((remoteTrack) => remoteTrack.id === track.id)) {
                    remoteStream.addTrack(track);
                }
            });

            emit('onRemoteStream', remoteStream);
        };

        peerConnection.onconnectionstatechange = () => {
            emit('onStateChange', peerConnection.connectionState);
            handleReconnectState();
        };

        peerConnection.oniceconnectionstatechange = () => {
            handleReconnectState();
        };

        emit('onLocalStream', localStream);
        startQualityMonitor();

        return peerConnection;
    };

    const createOffer = async (mediaType = 'audio') => {
        const pc = await ensurePeerConnection(mediaType);
        const offer = await pc.createOffer({
            offerToReceiveAudio: true,
            offerToReceiveVideo: mediaType === 'video'
        });

        await pc.setLocalDescription(new RTCSessionDescription(tuneOpusSessionDescription(offer)));
        emit('onSignal', 'offer', normalizeSessionDescription(pc.localDescription.toJSON()));
    };

    const handleOffer = async (offer, mediaType = 'audio') => {
        const pc = await ensurePeerConnection(mediaType);

        await pc.setRemoteDescription(new RTCSessionDescription(normalizeSessionDescription(offer)));
        await flushPendingIceCandidates();

        const answer = await pc.createAnswer();

        await pc.setLocalDescription(new RTCSessionDescription(tuneOpusSessionDescription(answer)));
        emit('onSignal', 'answer', normalizeSessionDescription(pc.localDescription.toJSON()));
    };

    const handleAnswer = async (answer) => {
        if(! peerConnection || peerConnection.signalingState === 'stable') {
            return;
        }

        await peerConnection.setRemoteDescription(new RTCSessionDescription(normalizeSessionDescription(answer)));
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
            await peerConnection.addIceCandidate(candidate);
        }
        catch(error) {}
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
        try {
            peerConnection?.getSenders()?.forEach((sender) => {
                sender.track?.stop();
            });

            peerConnection?.close();
        }
        catch(error) {}

        voiceProcessingCleanup?.();
        stopQualityMonitor();
        clearReconnectTimer();
        localStream?.getTracks()?.forEach((track) => track.stop());
        rawLocalStream?.getTracks()?.forEach((track) => track.stop());
        remoteStream?.getTracks()?.forEach((track) => track.stop());

        peerConnection = null;
        localStream = null;
        rawLocalStream = null;
        remoteStream = null;
        voiceProcessingCleanup = null;
        pendingIceCandidates = [];
        audioSenders = [];
        currentNetworkQuality = 'unknown';
        connectedNotified = false;
    };

    return {
        createOffer,
        handleOffer,
        handleAnswer,
        handleIce,
        ensurePeerConnection,
        setMuted,
        close,
        isSupported: isAudioCallSupported
    };
};

export { createAudioCallPeer, isAudioCallSupported };
