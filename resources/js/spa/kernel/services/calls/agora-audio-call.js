const qualityMonitorIntervalMs = Number(import.meta.env.VITE_CALL_QUALITY_MONITOR_INTERVAL || 3000);
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

const createAgoraAudioCallPeer = (callbacks = {}, options = {}) => {
    let client = null;
    let localAudioTrack = null;
    let remoteAudioTrack = null;
    let localStream = null;
    let joined = false;
    let isMuted = false;
    let qualityTimer = null;
    let currentMediaSession = options.mediaSession || {};
    let remoteUid = null;
    let closing = false;

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
            const rtt = toNumber(pickFirstValue(remoteStats.end2EndDelay, remoteStats.receiveDelay, rtcStats.RTT));
            const jitter = toNumber(pickFirstValue(remoteStats.jitterBufferDelay, remoteStats.receiveJitterDelay, remoteStats.jitter));
            const packetLossPercent = toNumber(pickFirstValue(remoteStats.packetLossRate, remoteStats.receivePacketLossRate, remoteStats.receivePacketLossRatePercent));
            const networkQuality = classifyNetworkQuality({
                rtt: rtt,
                jitter: jitter,
                packetLossPercent: packetLossPercent
            });

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

    const classifyNetworkQuality = ({ rtt, jitter, packetLossPercent }) => {
        if(packetLossPercent >= 10 || rtt >= 700 || jitter >= 120) {
            return 'poor';
        }

        if(packetLossPercent >= 3 || rtt >= 400 || jitter >= 60) {
            return 'weak';
        }

        return 'good';
    };

    const setupClientEvents = () => {
        client.on('user-published', async (user, mediaType) => {
            if(closing) {
                return;
            }

            if(mediaType !== 'audio') {
                return;
            }

            try {
                await client.subscribe(user, mediaType);
                remoteUid = user.uid;
                remoteAudioTrack = user.audioTrack;
                remoteAudioTrack?.play?.();
                emit('onConnected');
                startQualityTimer();
            }
            catch(error) {
                emit('onReconnectState', 'failed');
            }
        });

        client.on('user-unpublished', (user, mediaType) => {
            if(closing) {
                return;
            }

            if(mediaType === 'audio' && user.uid === remoteUid) {
                remoteAudioTrack?.stop?.();
                remoteAudioTrack = null;
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
            encoderConfig: 'speech_standard'
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
        startQualityTimer();

        return true;
    };

    const close = () => {
        closing = true;
        stopQualityTimer();

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
        remoteUid = null;
        joined = false;

        if(activeClient) {
            Promise.resolve()
                .then(() => activeClient.leave?.())
                .catch(() => {});
        }
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
        close: close
    };
};

export { createAgoraAudioCallPeer, isAgoraAudioCallSupported };
