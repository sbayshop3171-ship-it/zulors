const nativeCallEventName = 'zulors:native-call';

const getNativeAgoraBridge = () => {
    if(typeof window === 'undefined') {
        return null;
    }

    return window.ZulorsCallAudio || null;
};

const isNativeAgoraAudioCallSupported = () => {
    const bridge = getNativeAgoraBridge();

    if(! bridge) {
        return false;
    }

    try {
        if(typeof bridge.nativeRtcSupported === 'function') {
            return bridge.nativeRtcSupported() === true;
        }
    }
    catch(error) {}

    return typeof bridge.startNativeAgoraCall === 'function';
};

const warmNativeAgoraAudioCallEngine = async () => {
    return isNativeAgoraAudioCallSupported();
};

const createNativeAgoraAudioCallPeer = (callbacks = {}, options = {}) => {
    let isClosed = false;
    let remoteAudioConnected = false;
    let currentMediaSession = options.mediaSession || null;
    let nativeEventListener = null;

    const emit = (name, ...args) => {
        try {
            callbacks?.[name]?.(...args);
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

    const updateToken = async () => {
        try {
            const nextMediaSession = await refreshMediaSession();

            if(nextMediaSession?.token) {
                getNativeAgoraBridge()?.updateNativeAgoraToken?.(nextMediaSession.token);
            }
        }
        catch(error) {
            emit('onReconnectState', 'failed');
        }
    };

    const setRemoteAudioConnected = (connected) => {
        const nextValue = Boolean(connected);

        if(remoteAudioConnected === nextValue) {
            return;
        }

        remoteAudioConnected = nextValue;
        emit('onNativeRemoteAudioState', nextValue);
    };

    const handleNativeEvent = (event) => {
        const detail = event?.detail || {};
        const type = String(detail.type || '').toLowerCase();

        if(type === 'state') {
            const state = String(detail.state || '').toLowerCase();

            if(['connecting', 'joining'].includes(state)) {
                emit('onStateChange', 'connecting');

                return;
            }

            if(state === 'connected') {
                emit('onStateChange', 'connected');
                emit('onReconnectState', 'stable');
                emit('onConnected');

                return;
            }

            if(state === 'reconnecting') {
                emit('onStateChange', 'connecting');
                emit('onReconnectState', 'reconnecting');

                return;
            }

            if(['failed', 'disconnected'].includes(state)) {
                emit('onStateChange', 'failed');
                emit('onReconnectState', state === 'disconnected' ? 'reconnecting' : 'failed');
            }

            return;
        }

        if(type === 'remote-audio') {
            setRemoteAudioConnected(detail.connected === true);

            if(detail.connected === true) {
                emit('onReconnectState', 'stable');
                emit('onConnected');
            }

            return;
        }

        if(type === 'quality') {
            emit('onQualityStats', {
                network_quality: detail.network_quality || 'unknown',
                issue: detail.issue || null,
                connection_state: detail.connection_state || 'unknown',
                round_trip_time_ms: detail.round_trip_time_ms,
                jitter_ms: detail.jitter_ms,
                packets_lost: detail.packets_lost,
                packets_received: detail.packets_received,
                packet_loss_percent: detail.packet_loss_percent,
                audio_level: detail.audio_level,
            });

            return;
        }

        if(type === 'route') {
            emit('onNativeRouteChange', detail.route || 'unknown');

            return;
        }

        if(type === 'token-expiring' || type === 'token-expired') {
            updateToken();

            return;
        }

        if(type === 'error') {
            emit('onStateChange', 'failed');
            emit('onReconnectState', 'failed');
        }
    };

    const attachNativeEventListener = () => {
        if(typeof window === 'undefined' || nativeEventListener) {
            return;
        }

        nativeEventListener = handleNativeEvent;
        window.addEventListener(nativeCallEventName, nativeEventListener);
    };

    const detachNativeEventListener = () => {
        if(typeof window === 'undefined' || ! nativeEventListener) {
            nativeEventListener = null;

            return;
        }

        window.removeEventListener(nativeCallEventName, nativeEventListener);
        nativeEventListener = null;
    };

    const ensurePeerConnection = async (mediaType = 'audio') => {
        if(isClosed) {
            throw new Error('Call already ended.');
        }

        if(! isNativeAgoraAudioCallSupported()) {
            throw new Error('Native audio calling is not available in this app.');
        }

        attachNativeEventListener();

        const mediaSession = await refreshMediaSession();

        if(mediaSession?.provider !== 'agora' || ! mediaSession?.app_id || ! mediaSession?.channel) {
            throw new Error('Agora call media is not configured.');
        }

        currentMediaSession = mediaSession;

        const payload = JSON.stringify({
            app_id: mediaSession.app_id,
            channel: mediaSession.channel,
            token: mediaSession.token || null,
            uid: mediaSession.uid || 0,
            area_code: mediaSession.area_code || null,
            excluded_area: mediaSession.excluded_area || null,
            audio_encoder_profile: mediaSession.audio_encoder_profile || null,
            audio_bitrate_kbps: mediaSession.audio_bitrate_kbps || null,
            audio_bitrate_floor_kbps: mediaSession.audio_bitrate_floor_kbps || null,
            audio_sample_rate: mediaSession.audio_sample_rate || null,
            media_type: mediaType || 'audio',
        });

        const result = getNativeAgoraBridge()?.startNativeAgoraCall?.(payload);

        if(result === false) {
            throw new Error('Unable to start native audio engine.');
        }

        return true;
    };

    const close = () => {
        isClosed = true;
        setRemoteAudioConnected(false);
        detachNativeEventListener();

        try {
            getNativeAgoraBridge()?.endNativeAgoraCall?.();
        }
        catch(error) {}

        try {
            getNativeAgoraBridge()?.leaveCall?.();
        }
        catch(error) {}
    };

    return {
        ensurePeerConnection: ensurePeerConnection,
        createOffer: async () => true,
        handleOffer: async () => true,
        handleAnswer: async () => true,
        handleIce: async () => true,
        handleIceBatch: async () => true,
        setMuted: async (muted) => {
            try {
                getNativeAgoraBridge()?.setMutedNative?.(Boolean(muted));
            }
            catch(error) {}
        },
        setRemoteOutputVolume: () => {},
        attachRemoteOutputElement: () => {},
        refreshRemoteAudio: async () => {
            try {
                const result = getNativeAgoraBridge()?.refreshNativeAgoraCall?.();

                return result !== false;
            }
            catch(error) {
                return false;
            }
        },
        close: close,
    };
};

export { createNativeAgoraAudioCallPeer, isNativeAgoraAudioCallSupported, warmNativeAgoraAudioCallEngine };
