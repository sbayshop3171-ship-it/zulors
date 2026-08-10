const parsePositiveNumber = (value, defaultValue) => {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? number : defaultValue;
};

const qualityMonitorIntervalMs = parsePositiveNumber(import.meta.env.VITE_CALL_QUALITY_MONITOR_INTERVAL, 3000);
const qualityWarningSamples = Math.max(1, parsePositiveNumber(import.meta.env.VITE_CALL_QUALITY_WARNING_SAMPLES, 2));
const agoraSpeechEncoderConfig = {
    sampleRate: parsePositiveNumber(import.meta.env.VITE_AGORA_CALL_AUDIO_SAMPLE_RATE, 48000),
    stereo: false,
    bitrate: parsePositiveNumber(import.meta.env.VITE_AGORA_CALL_AUDIO_BITRATE, 32)
};
let agoraRtcPromise = null;

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

const toNumber = (value, fallback = 0) => {
    const number = Number(value);

    return Number.isFinite(number) ? number : fallback;
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
    let remoteAudioTrack = null;
    let localStream = null;
    let remoteStream = null;
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
    let remoteUserSweepAttempts = 0;

    const emit = (event, ...payload) => {
        try {
            callbacks[event]?.(...payload);
        }
        catch(error) {}
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
            const remoteStats = remoteStatsMap[remoteUid] || Object.values(remoteStatsMap)[0] || {};
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
        try {
            remoteAudioTrack?.setVolume?.(remoteOutputVolume);
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

    const stopRemoteAudioTrackPlayback = () => {
        try {
            remoteAudioTrack?.stop?.();
        }
        catch(error) {}
    };

    const publishRemoteStream = () => {
        remoteStream = streamFromTrack(remoteAudioTrack);

        if(remoteStream) {
            emit('onRemoteStream', remoteStream);
        }
    };

    const playRemoteAudio = () => {
        applyRemoteOutputVolume();
        publishRemoteStream();
        processRemoteOutputAEC();

        if(remoteOutputElement && remoteStream) {
            stopRemoteAudioTrackPlayback();

            return;
        }

        try {
            remoteAudioTrack?.play?.();
        }
        catch(error) {}
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
        if(closing || ! user || user.uid === currentMediaSession?.uid) {
            return false;
        }

        if(! user.audioTrack && user.hasAudio !== true) {
            return false;
        }

        try {
            if(! user.audioTrack) {
                await client.subscribe(user, 'audio');
            }
            else if(user.hasAudio === true) {
                await client.subscribe(user, 'audio').catch(() => {});
            }

            if(! user.audioTrack) {
                return false;
            }

            remoteUid = user.uid;
            stopRemoteAudioTrackPlayback();
            remoteAudioTrack = user.audioTrack;
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

        remoteUserSweepAttempts = 0;
    };

    const startRemoteUserSweep = () => {
        stopRemoteUserSweep();

        if(typeof window === 'undefined') {
            return;
        }

        remoteUserSweepTimer = window.setInterval(async () => {
            if(closing || remoteAudioTrack) {
                stopRemoteUserSweep();

                return;
            }

            remoteUserSweepAttempts += 1;
            await subscribeToPublishedRemoteUsers();

            if(remoteAudioTrack || remoteUserSweepAttempts >= 15) {
                stopRemoteUserSweep();
            }
        }, 1000);
    };

    const setupClientEvents = () => {
        client.on('user-published', async (user, mediaType) => {
            if(mediaType === 'audio') {
                await subscribeToRemoteAudio(user);
            }
        });

        client.on('user-unpublished', (user, mediaType) => {
            if(closing) {
                return;
            }

            if(mediaType === 'audio' && user.uid === remoteUid) {
                remoteAudioTrack?.stop?.();
                remoteAudioTrack = null;
                remoteStream = null;
                emit('onRemoteStream', null);
                emit('onReconnectState', 'reconnecting');
            }
        });

        client.on('user-left', (user) => {
            if(closing) {
                return;
            }

            if(user.uid === remoteUid) {
                remoteAudioTrack?.stop?.();
                remoteAudioTrack = null;
                remoteStream = null;
                emit('onRemoteStream', null);
                emit('onReconnectState', 'reconnecting');
            }
        });

        client.on('connection-state-change', (currentState) => {
            if(closing) {
                return;
            }

            emit('onStateChange', String(currentState || '').toLowerCase());

            if(currentState === 'CONNECTED') {
                emit('onReconnectState', 'stable');

                return;
            }

            if(['RECONNECTING', 'DISCONNECTED'].includes(currentState)) {
                emit('onReconnectState', 'reconnecting');

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

        if(mediaType !== 'audio') {
            throw new Error('Only audio calls are available.');
        }

        if(! isAgoraAudioCallSupported()) {
            throw new Error('Agora audio calls are not supported in this browser.');
        }

        const AgoraRTC = await loadAgoraRTC();
        AgoraRTCModule = AgoraRTC;

        if(! AgoraRTC?.createClient) {
            throw new Error('Agora audio SDK could not be loaded.');
        }

        if(! currentMediaSession?.app_id || ! currentMediaSession?.channel) {
            throw new Error('Agora call media is not configured.');
        }

        client = AgoraRTC.createClient({
            mode: 'rtc',
            codec: 'vp8'
        });
        setupClientEvents();

        await client.join(
            currentMediaSession.app_id,
            currentMediaSession.channel,
            currentMediaSession.token || null,
            currentMediaSession.uid || null
        );

        localAudioTrack = await AgoraRTC.createMicrophoneAudioTrack({
            AEC: true,
            AGC: true,
            ANS: true,
            encoderConfig: agoraSpeechEncoderConfig
        });

        if(isMuted) {
            await localAudioTrack.setEnabled(false);
        }

        localStream = streamFromTrack(localAudioTrack);

        if(localStream) {
            emit('onLocalStream', localStream);
        }

        await client.publish([localAudioTrack]);
        joined = true;
        emit('onStateChange', 'connected');
        await subscribeToPublishedRemoteUsers();
        startRemoteUserSweep();
        startQualityTimer();

        return true;
    };

    const close = () => {
        closing = true;
        stopQualityTimer();
        stopRemoteUserSweep();

        try {
            remoteAudioTrack?.stop?.();
        }
        catch(error) {}

        try {
            localAudioTrack?.close?.();
        }
        catch(error) {}

        const activeClient = client;

        client = null;
        localAudioTrack = null;
        remoteAudioTrack = null;
        localStream = null;
        remoteStream = null;
        remoteUid = null;
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
            await localAudioTrack?.setEnabled?.(! isMuted);
        },
        setRemoteOutputVolume: (volume) => {
            const normalizedVolume = Number(volume);

            remoteOutputVolume = Math.max(0, Math.min(100, Math.round((Number.isFinite(normalizedVolume) ? normalizedVolume : 1) * 100)));
            applyRemoteOutputVolume();
        },
        attachRemoteOutputElement: (element) => {
            remoteOutputElement = element || null;
            processRemoteOutputAEC();

            if(remoteAudioTrack) {
                playRemoteAudio();
            }
        },
        close: close
    };
};

export { createAgoraAudioCallPeer, isAgoraAudioCallSupported };
