const defaultIceServers = [
    { urls: 'stun:stun.l.google.com:19302' }
];

const parseIceServers = () => {
    const rawConfig = import.meta.env.VITE_CALL_ICE_SERVERS;

    if(! rawConfig) {
        return defaultIceServers;
    }

    try {
        const parsedConfig = JSON.parse(rawConfig);

        if(Array.isArray(parsedConfig) && parsedConfig.length) {
            return parsedConfig;
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

const createAudioCallPeer = (callbacks = {}) => {
    let peerConnection = null;
    let localStream = null;
    let remoteStream = null;
    let pendingIceCandidates = [];
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

    const ensurePeerConnection = async (mediaType = 'audio') => {
        if(peerConnection) {
            return peerConnection;
        }

        if(! isAudioCallSupported()) {
            throw new Error('Audio call is not supported in this browser.');
        }

        localStream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            },
            video: mediaType === 'video'
        });

        remoteStream = new MediaStream();
        peerConnection = new RTCPeerConnection({
            iceServers: parseIceServers()
        });

        localStream.getTracks().forEach((track) => {
            peerConnection.addTrack(track, localStream);
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

            if(['connected', 'completed'].includes(peerConnection.connectionState)) {
                notifyConnected();
            }
        };

        peerConnection.oniceconnectionstatechange = () => {
            if(['connected', 'completed'].includes(peerConnection.iceConnectionState)) {
                notifyConnected();
            }
        };

        emit('onLocalStream', localStream);

        return peerConnection;
    };

    const createOffer = async (mediaType = 'audio') => {
        const pc = await ensurePeerConnection(mediaType);
        const offer = await pc.createOffer({
            offerToReceiveAudio: true,
            offerToReceiveVideo: mediaType === 'video'
        });

        await pc.setLocalDescription(offer);
        emit('onSignal', 'offer', normalizeSessionDescription(pc.localDescription.toJSON()));
    };

    const handleOffer = async (offer, mediaType = 'audio') => {
        const pc = await ensurePeerConnection(mediaType);

        await pc.setRemoteDescription(new RTCSessionDescription(normalizeSessionDescription(offer)));
        await flushPendingIceCandidates();

        const answer = await pc.createAnswer();

        await pc.setLocalDescription(answer);
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
    };

    const close = () => {
        try {
            peerConnection?.getSenders()?.forEach((sender) => {
                sender.track?.stop();
            });

            peerConnection?.close();
        }
        catch(error) {}

        localStream?.getTracks()?.forEach((track) => track.stop());
        remoteStream?.getTracks()?.forEach((track) => track.stop());

        peerConnection = null;
        localStream = null;
        remoteStream = null;
        pendingIceCandidates = [];
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
