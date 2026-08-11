import { defineStore } from 'pinia';
import { markRaw } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import BRD from '@/kernel/websockets/brd/index.js';
import { createAgoraAudioCallPeer, isAgoraAudioCallSupported } from '@/kernel/services/calls/agora-audio-call.js';
import { createAudioCallPeer, isAudioCallSupported } from '@/kernel/services/calls/webrtc-audio-call.js';

const finalStatuses = ['ended', 'missed', 'declined', 'busy', 'failed'];
const connectingStatuses = ['ringing', 'accepted', 'connecting'];
const defaultRingTimeoutSeconds = 40;
const connectionTimeoutSeconds = 40;
const degradedConnectionTimeoutSeconds = 40;
const heartbeatIntervalMs = 10000;
const qualityReportThrottleMs = 10000;
const iceServerRefreshSkewMs = 60000;
const callStartCooldownMs = 3500;
const rateLimitedCallStartCooldownMs = 15000;
const busyCallMessage = 'User is busy on another call.';
const incomingRingIntervalMs = 3200;
const outgoingRingIntervalMs = 4200;
const speakerRouteSettleMs = 260;
const nativeAudioPermissionEventName = 'zulors:audio-permission';
const nativeAudioPermissionTimeoutMs = 15000;
const nativeAudioPermissionPollIntervalMs = 300;
const connectingSyncIntervalMs = 4000;
const uiFrameYieldDelayMs = 16;

const finalStatusForReason = (reason) => {
    if(reason === 'no_answer') {
        return 'missed';
    }

    if(['connection_lost', 'connection_timeout', 'ice_failed'].includes(reason)) {
        return 'failed';
    }

    return 'ended';
};

const getErrorStatus = (error) => {
    return Number(error?.response?.status || 0);
};

const getErrorData = (error) => {
    return error?.response?.data || {};
};

const makeSilentCallError = (message = '') => {
    const error = new Error(message || 'Call request ignored.');

    error.__zulorsSilentCallToast = true;

    return error;
};

const isBusyCallError = (error) => {
    const data = getErrorData(error);

    return getErrorStatus(error) === 409
        || data?.reason === 'busy'
        || data?.status === 'busy'
        || data?.data?.reason === 'busy'
        || data?.data?.status === 'busy';
};

const normalizeCallPayload = (payload = {}) => {
    if(payload?.data?.call) {
        return payload.data.call;
    }

    if(payload?.call) {
        return payload.call;
    }

    return payload;
};

const normalizeEventData = (event = {}) => {
    return event?.data || event || {};
};

const createCallStore = ({ storeId, useAuthStore }) => defineStore(storeId, {
    state: () => {
        return {
            call: null,
            direction: null,
            status: 'idle',
            error: '',
            minimized: false,
            isMuted: false,
            speakerEnabled: false,
            localStream: null,
            remoteStream: null,
            peer: null,
            peerSetupPromise: null,
            mediaProvider: 'webrtc',
            mediaSession: null,
            activeChannel: null,
            offerSent: false,
            mediaReadySent: false,
            connectedSignalSent: false,
            isAnswering: false,
            durationSeconds: 0,
            durationTimer: null,
            ringSecondsRemaining: 0,
            ringTimeoutTimer: null,
            connectionTimeoutTimer: null,
            remoteAudioWatchdogTimer: null,
            degradedConnectionTimeoutTimer: null,
            reconnectTimeoutTimer: null,
            heartbeatTimer: null,
            ringToneContext: null,
            ringToneTimer: null,
            ringToneTimeouts: [],
            ringToneNodes: [],
            nativeRingtoneActive: false,
            audioRouteSettling: false,
            audioRouteSettleTimer: null,
            networkState: 'stable',
            qualityNotice: '',
            lastQualityReportAt: 0,
            lastQualitySignature: '',
            iceServers: null,
            iceServersExpiresAt: 0,
            isStarting: false,
            isStartCoolingDown: false,
            startCooldownTimer: null,
            finalizingCallUuid: null,
            resetTimer: null,
            mediaSetupAttempt: 0,
            heartbeatInFlight: false,
            microphonePermissionPromise: null,
            connectingSyncTimer: null,
            syncingCurrentCall: false
        };
    },
    getters: {
        isVisible: function() {
            return Boolean(this.call && this.status !== 'idle');
        },
        isIncoming: function() {
            return this.direction === 'incoming' && this.status === 'ringing';
        },
        isOutgoing: function() {
            return this.direction === 'outgoing' && this.status === 'ringing';
        },
        isActive: function() {
            return ['accepted', 'connecting', 'connected'].includes(this.status);
        },
        isFinal: function() {
            return finalStatuses.includes(this.status);
        },
        currentUserId: function() {
            return useAuthStore().userData?.id;
        },
        otherUser: function() {
            const authUserId = this.currentUserId;

            if(! this.call) {
                return {};
            }

            if(this.call.initiator_id === authUserId) {
                return this.call.relations?.receiver || {};
            }

            return this.call.relations?.initiator || {};
        },
        title: function() {
            return this.otherUser?.name || 'Zulors call';
        },
        avatarUrl: function() {
            return this.otherUser?.avatar_url || null;
        },
        canUseAudio: function() {
            return isAudioCallSupported() || isAgoraAudioCallSupported();
        },
        hasNativeAudioBridge: function() {
            return typeof window !== 'undefined' && Boolean(window.ZulorsCallAudio);
        },
        isFinalizing: function() {
            return Boolean(this.finalizingCallUuid);
        }
    },
    actions: {
        canStartCall: function(chatData = {}) {
            return Boolean(
                this.canUseAudio
                && chatData?.chat_id
                && ! chatData?.is_group
                && chatData?.type !== 'group'
                && ! chatData?.chat_info?.meta?.relationship?.block?.blocking
                && ! this.isStarting
                && ! this.isStartCoolingDown
                && ! this.isVisible
            );
        },
        startCall: async function(chatData = {}, mediaType = 'audio') {
            if(this.isStarting || this.isStartCoolingDown) {
                throw makeSilentCallError();
            }

            if(! this.canStartCall(chatData)) {
                throw new Error('Audio call is not available for this chat.');
            }

            if(mediaType !== 'audio') {
                throw new Error('Video calls are not available yet.');
            }

            this.unlockAudioFeedback();
            this.isStarting = true;
            this.startCallCooldown(callStartCooldownMs);

            let response = null;

            try {
                response = await colibriAPI().messenger().with({
                    chat_id: chatData.chat_id,
                    media_type: mediaType
                }).sendTo('calls/start');
            }
            catch(error) {
                if(isBusyCallError(error)) {
                    this.error = busyCallMessage;
                    throw new Error(busyCallMessage);
                }

                if(getErrorStatus(error) === 429) {
                    this.startCallCooldown(rateLimitedCallStartCooldownMs);
                    throw makeSilentCallError();
                }

                throw new Error(error.response?.data?.message || error.message || 'Unable to start audio call.');
            }
            finally {
                this.isStarting = false;
            }

            this.setCall(response.data.data.call, {
                direction: 'outgoing',
                status: 'ringing'
            });
            this.attachRealtimeChannel(this.call.chat_id);
        },
        fetchCall: async function(callUuid, options = {}) {
            if(! callUuid) {
                return false;
            }

            const response = await colibriAPI().messenger().getFrom(`calls/${callUuid}`);
            const call = response.data.data.call;
            const direction = call.receiver_id === this.currentUserId ? 'incoming' : 'outgoing';

            this.setCall(call, {
                direction: direction,
                status: call.status || 'ringing'
            });
            this.attachRealtimeChannel(call.chat_id);

            if(options.action === 'answer' && direction === 'incoming' && call.status === 'ringing') {
                await this.answerCall();
            }

            return call;
        },
        answerCall: async function() {
            if(! this.call?.call_uuid) {
                return false;
            }

            if(this.isAnswering) {
                return false;
            }

            const callUuid = this.call.call_uuid;
            let answerAccepted = false;

            this.isAnswering = true;
            this.status = 'connecting';
            this.stopRingingFeedback();
            await this.yieldToUiFrame();

            try {
                const response = await colibriAPI().messenger()
                    .sendTo(`calls/${callUuid}/answer`);
                answerAccepted = true;

                this.setCall(response.data.data.call, {
                    direction: 'incoming',
                    status: response.data.data.call.status || 'accepted'
                });
                this.minimized = false;
                this.attachRealtimeChannel(this.call.chat_id);
                await this.yieldToUiFrame();
                await this.setupPeer();

                if(this.mediaProvider === 'agora') {
                    this.markConnecting();
                }
                else {
                    await this.sendSignal('ready', {});
                }

                return true;
            }
            catch(error) {
                if(error.__zulorsSilentCallToast) {
                    return false;
                }

                this.error = error.message || 'Unable to start audio call.';
                this.finishCall('failed', 2600);

                try {
                    if(answerAccepted) {
                        await colibriAPI().messenger().with({
                            reason: 'connection_lost'
                        }).sendTo(`calls/${callUuid}/end`);
                    }
                    else {
                        await colibriAPI().messenger().sendTo(`calls/${callUuid}/decline`);
                    }
                }
                catch(declineError) {}

                return false;
            }
            finally {
                this.isAnswering = false;
            }
        },
        declineCall: async function() {
            if(! this.call?.call_uuid) {
                return false;
            }

            if(this.finalizingCallUuid === this.call.call_uuid) {
                this.finishCall('declined', 0);

                return true;
            }

            const callUuid = this.call.call_uuid;
            this.finalizingCallUuid = callUuid;
            this.finishCall('declined');

            try {
                await colibriAPI().messenger()
                    .sendTo(`calls/${callUuid}/decline`);
            }
            catch(error) {
                // The local UI still closes. The server may already have finalized the call.
            }

            return true;
        },
        endCall: async function(reason = 'user_ended') {
            if(! this.call?.call_uuid) {
                this.finishCall('ended', 0);

                return false;
            }

            reason = typeof reason === 'string' && reason ? reason : 'user_ended';

            const callUuid = this.call.call_uuid;
            const finalStatus = finalStatusForReason(reason);

            if(this.finalizingCallUuid === callUuid) {
                this.finishCall(finalStatus, 0);

                return true;
            }

            this.finalizingCallUuid = callUuid;

            this.finishCall(finalStatus, reason === 'user_ended' ? 120 : 900);

            try {
                await colibriAPI().messenger().with({
                    reason: reason
                })
                    .sendTo(`calls/${callUuid}/end`);
            }
            catch(error) {
                // Finalization requests can race with the remote side or timeout jobs.
                // Keep the local call UI deterministic and let realtime/history catch up.
            }

            return true;
        },
        sendSignal: async function(signalType, signal = {}) {
            if(! this.call?.call_uuid || this.isFinal || this.finalizingCallUuid === this.call.call_uuid) {
                return false;
            }

            const response = await colibriAPI().messenger().with({
                signal_type: signalType,
                signal: signal || {}
            }).sendTo(`calls/${this.call.call_uuid}/signal`);

            const call = response.data?.data?.call;

            if(call?.call_uuid === this.call?.call_uuid) {
                this.setCall(call, {
                    status: call.status || this.status
                });

                if(call.status === 'connected') {
                    this.markConnected(false);
                }
            }

            return response;
        },
        handleNotification: function(payload = {}) {
            const data = payload?.data || payload || {};
            const type = data.type || payload.type;
            const call = normalizeCallPayload(data);

            if(! call?.call_uuid) {
                return false;
            }

            if(type === 'call.missed') {
                if(this.call?.call_uuid === call.call_uuid) {
                    this.finishCall('missed');
                }

                return true;
            }

            if(call.receiver_id !== this.currentUserId) {
                return false;
            }

            this.handleIncoming({ data: { call: call } });

            return true;
        },
        handleIncoming: function(event = {}) {
            const call = normalizeCallPayload(normalizeEventData(event));

            if(! call?.call_uuid || call.receiver_id !== this.currentUserId) {
                return false;
            }

            if(this.isVisible && this.call?.call_uuid !== call.call_uuid) {
                this.sendBusyNotice(call);

                return false;
            }

            this.setCall(call, {
                direction: 'incoming',
                status: call.status || 'ringing'
            });
            this.attachRealtimeChannel(call.chat_id);
        },
        handleAnswered: async function(event = {}) {
            const call = normalizeCallPayload(normalizeEventData(event));

            if(! this.isCurrentCall(call)) {
                return false;
            }

            this.setCall(call, {
                status: call.status || 'accepted'
            });

            if(call.initiator_id === this.currentUserId) {
                try {
                    await this.yieldToUiFrame();
                    await this.setupPeer();

                    if(this.mediaProvider === 'agora') {
                        this.markConnecting();
                    }
                    else {
                        await this.createOffer();
                    }
                }
                catch(error) {
                    if(error.__zulorsSilentCallToast) {
                        return false;
                    }

                    this.error = error.message || 'Unable to connect audio call.';
                    await this.endCall();
                }
            }
        },
        handleDeclined: function(event = {}) {
            const call = normalizeCallPayload(normalizeEventData(event));

            if(this.isCurrentCall(call)) {
                this.finishCall('declined');
            }
        },
        handleEnded: function(event = {}) {
            const call = normalizeCallPayload(normalizeEventData(event));

            if(this.isCurrentCall(call)) {
                this.finishCall(finalStatuses.includes(call.status) ? call.status : 'ended');
            }
        },
        handleBusy: function(event = {}) {
            const data = normalizeEventData(event);

            if(data.reason === 'busy' || data.call?.status === 'busy') {
                this.error = busyCallMessage;
                this.finishCall('busy', 1800);
            }
        },
        handleSignal: async function(event = {}) {
            const data = normalizeEventData(event);
            const call = normalizeCallPayload(data);

            if(! this.isCurrentCall(call) || data.sender_user_id === this.currentUserId) {
                return false;
            }

            try {
                if(this.mediaProvider === 'agora' && ['offer', 'answer', 'ice', 'candidate', 'ready'].includes(data.signal_type)) {
                    return false;
                }

                if(data.signal_type === 'offer') {
                    await this.setupPeer();
                    await this.peer.handleOffer(data.signal || {}, this.call.media_type || 'audio');
                    this.markConnecting();
                }
                else if(data.signal_type === 'answer') {
                    await this.peer?.handleAnswer(data.signal || {});
                    this.markConnecting();
                }
                else if(['ice', 'candidate'].includes(data.signal_type)) {
                    await this.peer?.handleIce(data.signal || {});
                }
                else if(data.signal_type === 'connected') {
                    this.markConnected(false);
                }
                else if(data.signal_type === 'ready' && this.call?.initiator_id === this.currentUserId) {
                    await this.setupPeer();

                    if(this.mediaProvider !== 'agora') {
                        await this.createOffer();
                    }
                }
            }
            catch(error) {
                if(error.__zulorsSilentCallToast) {
                    return false;
                }

                this.error = error.message || 'Unable to connect audio call.';
                await this.endCall();
            }
        },
        setupPeer: async function() {
            if(this.peer) {
                return this.peer;
            }

            if(this.peerSetupPromise) {
                return this.peerSetupPromise;
            }

            this.peerSetupPromise = this.createPeerSession();

            try {
                return await this.peerSetupPromise;
            }
            finally {
                this.peerSetupPromise = null;
            }
        },
        createPeerSession: async function() {
            const callUuid = this.call?.call_uuid;
            const setupAttempt = this.mediaSetupAttempt + 1;
            let peer = null;
            const isCurrentSetup = () => {
                return Boolean(
                    callUuid
                    && this.call?.call_uuid === callUuid
                    && this.mediaSetupAttempt === setupAttempt
                    && ! this.isFinal
                    && this.finalizingCallUuid !== callUuid
                );
            };
            const assertCurrentSetup = () => {
                if(isCurrentSetup()) {
                    return;
                }

                try {
                    peer?.close?.();
                }
                catch(error) {}

                throw makeSilentCallError('Call setup was cancelled.');
            };

            this.mediaSetupAttempt = setupAttempt;
            await this.yieldToUiFrame();
            await this.ensureMicrophonePermission();
            assertCurrentSetup();

            const mediaSession = await this.resolveMediaSession();
            assertCurrentSetup();
            await this.yieldToUiFrame();

            const isAgora = mediaSession?.provider === 'agora';
            const callbacks = {
                onSignal: (signalType, signal) => {
                    if(this.mediaProvider !== 'agora') {
                        this.sendSignal(signalType, signal).catch(() => {});
                    }
                },
                onLocalStream: (stream) => {
                    this.localStream = markRaw(stream);
                },
                onRemoteStream: (stream) => {
                    this.remoteStream = markRaw(stream);
                    this.syncRemoteAudioWatchdog();
                    this.syncDegradedConnectionTimeout();
                },
                onConnected: () => {
                    this.markConnected(true);
                },
                onStateChange: (state) => {
                    if(state === 'connected' && this.mediaProvider === 'agora') {
                        this.sendMediaReadySignal().catch(() => {});
                        this.reconcileActiveCall({
                            setupIfNeeded: false
                        }).catch(() => {});

                        return;
                    }

                    if(['failed'].includes(state)) {
                        this.error = 'Audio connection was interrupted.';
                    }
                },
                onQualityStats: (stats) => {
                    this.handleQualityStats(stats);
                },
                onReconnectState: (state) => {
                    this.handleReconnectState(state);
                }
            };

            this.mediaSession = mediaSession;
            this.mediaProvider = isAgora ? 'agora' : 'webrtc';

            if(isAgora) {
                peer = createAgoraAudioCallPeer(callbacks, {
                    mediaSession: mediaSession,
                    refreshMediaSession: () => this.resolveMediaSession(true)
                });
            }
            else {
                const iceServers = await this.resolveIceServers();

                peer = createAudioCallPeer(callbacks, {
                    iceServers: iceServers
                });
            }

            this.peer = markRaw(peer);

            try {
                await this.yieldToUiFrame();
                await this.peer.ensurePeerConnection(this.call?.media_type || 'audio');
                assertCurrentSetup();
                this.enterNativeAudioMode();

                if(isAgora) {
                    await this.sendMediaReadySignal();
                }
            }
            catch(error) {
                try {
                    peer?.close?.();
                }
                catch(closeError) {}

                if(this.peer === peer) {
                    this.peer = null;
                }

                throw error;
            }

            return this.peer;
        },
        sendMediaReadySignal: async function() {
            if(this.mediaReadySent || ! this.call?.call_uuid || this.mediaProvider !== 'agora') {
                return false;
            }

            this.mediaReadySent = true;

            try {
                await this.sendSignal('ready', {
                    provider: 'agora'
                });

                return true;
            }
            catch(error) {
                this.mediaReadySent = false;

                return false;
            }
        },
        createOffer: async function() {
            if(this.mediaProvider === 'agora') {
                this.markConnecting();

                return true;
            }

            if(this.offerSent) {
                return false;
            }

            try {
                this.offerSent = true;
                await this.peer?.createOffer(this.call?.media_type || 'audio');
                this.markConnecting();
            }
            catch(error) {
                this.offerSent = false;
                throw error;
            }
        },
        markConnecting: function() {
            if(this.status === 'connected' || this.isFinal || ! this.call?.call_uuid) {
                return;
            }

            this.status = 'connecting';
            this.stopRingingFeedback();
            this.stopRemoteAudioWatchdog();
            this.stopDegradedConnectionTimeoutTimer();
            this.startConnectionTimeoutTimer();
            this.startConnectingSyncTimer();
        },
        markConnected: function(shouldNotify = true) {
            if(! this.call?.call_uuid || this.isFinal || this.finalizingCallUuid === this.call.call_uuid) {
                return;
            }

            this.status = 'connected';
            this.networkState = 'stable';
            this.qualityNotice = '';
            this.stopRingingFeedback();
            this.stopRingTimeoutTimer();
            this.stopConnectionTimeoutTimer();
            this.stopConnectingSyncTimer();
            this.stopReconnectTimeoutTimer();
            this.startHeartbeatTimer();
            this.startDurationTimer();
            this.syncRemoteAudioWatchdog();
            this.syncDegradedConnectionTimeout();

            if(shouldNotify && ! this.connectedSignalSent) {
                this.connectedSignalSent = true;
                this.sendSignal('connected', {}).catch(() => {});
            }
        },
        handleQualityStats: function(stats = {}) {
            if(! this.call?.call_uuid || ! this.isActive) {
                return false;
            }

            const networkQuality = stats.network_quality || 'unknown';

            this.networkState = networkQuality === 'good' ? 'stable' : networkQuality;
            this.qualityNotice = this.qualityNoticeFor(networkQuality);
            this.syncRemoteAudioWatchdog();
            this.syncDegradedConnectionTimeout();

            const now = Date.now();
            const signature = [
                networkQuality,
                stats.issue || '',
                stats.connection_state || '',
                stats.ice_connection_state || ''
            ].join(':');
            const urgent = ['poor', 'reconnecting'].includes(networkQuality) || stats.issue;

            if(! urgent && now - this.lastQualityReportAt < qualityReportThrottleMs) {
                return true;
            }

            if(urgent && signature === this.lastQualitySignature && now - this.lastQualityReportAt < qualityReportThrottleMs) {
                return true;
            }

            this.lastQualityReportAt = now;
            this.lastQualitySignature = signature;
            this.sendQualityReport(stats).catch(() => {});

            return true;
        },
        handleReconnectState: function(state = 'stable') {
            if(state === 'stable') {
                this.stopReconnectTimeoutTimer();
                this.networkState = 'stable';
                this.qualityNotice = '';
                this.syncRemoteAudioWatchdog();
                this.syncDegradedConnectionTimeout();

                return true;
            }

            if(state === 'reconnecting') {
                this.networkState = 'reconnecting';
                this.qualityNotice = 'Reconnecting...';
                this.startReconnectTimeoutTimer();
                this.syncRemoteAudioWatchdog();
                this.syncDegradedConnectionTimeout();

                return true;
            }

            if(state === 'failed') {
                this.stopReconnectTimeoutTimer();

                if(this.isActive) {
                    this.error = 'Audio connection was lost.';
                    this.endCall('connection_lost');
                }
            }

            return true;
        },
        qualityNoticeFor: function(networkQuality) {
            if(networkQuality === 'weak') {
                return 'Weak network';
            }

            if(networkQuality === 'poor') {
                return 'Poor connection';
            }

            if(networkQuality === 'reconnecting') {
                return 'Reconnecting...';
            }

            return '';
        },
        sendQualityReport: async function(stats = {}) {
            if(! this.call?.call_uuid || this.isFinal || this.finalizingCallUuid === this.call.call_uuid) {
                return false;
            }

            try {
                await colibriAPI().messenger().with(stats)
                    .sendTo(`calls/${this.call.call_uuid}/quality`);

                return true;
            }
            catch(error) {
                if([404, 410].includes(getErrorStatus(error))) {
                    this.finishCall('failed', 0);
                }

                return false;
            }
        },
        sendHeartbeat: async function() {
            if(this.heartbeatInFlight || ! this.call?.call_uuid || this.status !== 'connected' || ! this.isActive || this.isFinal || this.finalizingCallUuid === this.call.call_uuid) {
                return false;
            }

            this.heartbeatInFlight = true;

            try {
                const response = await colibriAPI().messenger().with({
                    status: this.status,
                    media_provider: this.mediaProvider,
                    network_state: this.networkState
                }).sendTo(`calls/${this.call.call_uuid}/heartbeat`);
                const call = response.data?.data?.call;

                if(call?.call_uuid === this.call?.call_uuid) {
                    this.setCall(call, {
                        status: call.status || this.status
                    });

                    if(call.status === 'connected' && this.status !== 'connected') {
                        this.markConnected(false);
                    }
                }

                return true;
            }
            catch(error) {
                if([404, 410].includes(getErrorStatus(error))) {
                    this.finishCall('failed');
                }

                return false;
            }
            finally {
                this.heartbeatInFlight = false;
            }
        },
        resolveIceServers: async function() {
            const now = Date.now();

            if(Array.isArray(this.iceServers) && this.iceServers.length && (! this.iceServersExpiresAt || now < this.iceServersExpiresAt - iceServerRefreshSkewMs)) {
                return this.iceServers;
            }

            try {
                const response = await colibriAPI().messenger().getFrom('calls/ice-servers');
                const payload = response.data?.data || {};
                const servers = Array.isArray(payload.ice_servers) ? payload.ice_servers : [];
                const expiresAt = Date.parse(payload.expires_at || '');

                if(servers.length) {
                    this.iceServers = servers;
                    this.iceServersExpiresAt = Number.isFinite(expiresAt) ? expiresAt : 0;

                    return servers;
                }
            }
            catch(error) {}

            return null;
        },
        resolveMediaSession: async function(force = false) {
            if(! this.call?.call_uuid) {
                return {
                    provider: 'webrtc'
                };
            }

            const expiresAt = Date.parse(this.mediaSession?.expires_at || '');

            if(! force && this.mediaSession?.provider === 'agora' && Number.isFinite(expiresAt) && expiresAt > Date.now() + iceServerRefreshSkewMs) {
                return this.mediaSession;
            }

            try {
                const response = await colibriAPI().messenger().getFrom(`calls/${this.call.call_uuid}/media-token`);
                const mediaSession = response.data?.data?.media || {};

                if(mediaSession?.provider === 'agora') {
                    this.mediaSession = mediaSession;

                    return mediaSession;
                }
            }
            catch(error) {
                if([409, 410, 422].includes(getErrorStatus(error))) {
                    throw error;
                }
            }

            this.mediaSession = {
                provider: 'webrtc'
            };

            return this.mediaSession;
        },
        ensureMicrophonePermission: async function() {
            const bridge = this.getNativeCallBridge();

            if(! bridge) {
                return true;
            }

            if(typeof bridge.hasAudioPermission === 'function') {
                try {
                    if(bridge.hasAudioPermission()) {
                        return true;
                    }
                }
                catch(error) {}
            }

            if(typeof bridge.requestAudioPermission !== 'function' || typeof window === 'undefined') {
                return true;
            }

            if(this.microphonePermissionPromise) {
                return this.microphonePermissionPromise;
            }

            this.microphonePermissionPromise = new Promise((resolve, reject) => {
                let settled = false;
                let timeout = null;
                let permissionPoll = null;

                const cleanup = () => {
                    settled = true;

                    if(timeout) {
                        window.clearTimeout(timeout);
                        timeout = null;
                    }

                    if(permissionPoll) {
                        window.clearInterval(permissionPoll);
                        permissionPoll = null;
                    }

                    window.removeEventListener(nativeAudioPermissionEventName, handlePermissionResult);
                    this.microphonePermissionPromise = null;
                };

                const hasPermissionNow = () => {
                    try {
                        return typeof bridge.hasAudioPermission === 'function' && bridge.hasAudioPermission();
                    }
                    catch(error) {
                        return false;
                    }
                };

                const resolveIfGranted = () => {
                    if(settled) {
                        return false;
                    }

                    if(! hasPermissionNow()) {
                        return false;
                    }

                    cleanup();
                    resolve(true);

                    return true;
                };

                const handlePermissionResult = (event) => {
                    if(settled) {
                        return;
                    }

                    const granted = event?.detail?.granted === true;

                    if(granted || hasPermissionNow()) {
                        cleanup();
                        resolve(true);

                        return;
                    }

                    cleanup();
                    reject(new Error('Microphone permission is blocked. Allow microphone access and try again.'));
                };

                window.addEventListener(nativeAudioPermissionEventName, handlePermissionResult);
                permissionPoll = window.setInterval(() => {
                    resolveIfGranted();
                }, nativeAudioPermissionPollIntervalMs);

                window.setTimeout(() => {
                    try {
                        if(Boolean(bridge.requestAudioPermission()) || resolveIfGranted()) {
                            cleanup();
                            resolve(true);
                        }
                    }
                    catch(error) {
                        cleanup();
                        reject(new Error('Unable to request microphone permission right now.'));
                    }
                }, 0);

                timeout = window.setTimeout(() => {
                    if(resolveIfGranted()) {
                        return;
                    }

                    cleanup();
                    reject(new Error('Microphone permission request timed out. Allow microphone access and try again.'));
                }, nativeAudioPermissionTimeoutMs);
            });

            return this.microphonePermissionPromise;
        },
        reconcileActiveCall: async function(options = {}) {
            if(! this.call?.call_uuid || this.isFinal || this.finalizingCallUuid === this.call.call_uuid || this.syncingCurrentCall) {
                return false;
            }

            this.syncingCurrentCall = true;

            try {
                const response = await colibriAPI().messenger().getFrom(`calls/${this.call.call_uuid}`);
                const call = response.data?.data?.call;

                if(! call?.call_uuid || ! this.isCurrentCall(call)) {
                    return false;
                }

                this.setCall(call, {
                    direction: this.direction || (call.receiver_id === this.currentUserId ? 'incoming' : 'outgoing'),
                    status: call.status || this.status
                });
                this.attachRealtimeChannel(call.chat_id);

                if(finalStatuses.includes(call.status)) {
                    this.finishCall(call.status, 0);

                    return true;
                }

                if(call.status === 'connected') {
                    this.markConnected(false);
                }
                else if(['accepted', 'connecting'].includes(call.status)) {
                    this.markConnecting();
                }

                if(options.setupIfNeeded && this.peer && this.mediaProvider === 'agora' && ! this.remoteStream && ['accepted', 'connecting', 'connected'].includes(call.status)) {
                    try {
                        await this.peer.refreshRemoteAudio?.();
                    }
                    catch(error) {}
                }

                if(options.setupIfNeeded && ['accepted', 'connecting', 'connected'].includes(call.status) && ! this.peer && ! this.peerSetupPromise) {
                    try {
                        await this.setupPeer();

                        if(call.status === 'connected') {
                            this.markConnected(false);
                        }
                        else if(this.mediaProvider === 'agora') {
                            this.markConnecting();
                        }
                    }
                    catch(error) {
                        if(! error?.__zulorsSilentCallToast) {
                            this.error = error.message || 'Unable to recover audio call.';
                            await this.endCall('connection_lost');
                        }
                    }
                }

                return true;
            }
            catch(error) {
                if([404, 410].includes(getErrorStatus(error))) {
                    this.finishCall('failed', 0);
                }

                return false;
            }
            finally {
                this.syncingCurrentCall = false;
            }
        },
        toggleMute: function() {
            this.isMuted = ! this.isMuted;

            try {
                Promise.resolve(this.peer?.setMuted?.(this.isMuted)).catch(() => {});
            }
            catch(error) {}
        },
        toggleSpeaker: function() {
            this.speakerEnabled = ! this.speakerEnabled;

            try {
                this.quietRemoteOutputForRouteChange();
                this.setNativeSpeakerEnabled(this.speakerEnabled);
            }
            catch(error) {}
        },
        minimize: function() {
            this.minimized = true;
        },
        expand: function() {
            this.minimized = false;
        },
        setCall: function(call, options = {}) {
            const normalizedCall = normalizeCallPayload(call);
            const sameCall = Boolean(this.call?.call_uuid && normalizedCall?.call_uuid === this.call.call_uuid);
            const nextStatus = options.status || normalizedCall.status || this.status;

            this.call = normalizedCall;
            this.direction = options.direction || this.direction;
            this.status = sameCall && this.status === 'connected' && connectingStatuses.includes(nextStatus)
                ? 'connected'
                : nextStatus;
            this.error = '';
            this.syncCallSideEffects();
        },
        finishCall: function(status = 'ended', resetDelay = 900) {
            if(this.resetTimer) {
                window.clearTimeout(this.resetTimer);
                this.resetTimer = null;
            }

            this.status = status;
            this.stopRingingFeedback();
            this.stopRingTimeoutTimer();
            this.stopConnectionTimeoutTimer();
            this.stopRemoteAudioWatchdog();
            this.stopDegradedConnectionTimeoutTimer();
            this.stopReconnectTimeoutTimer();
            this.stopHeartbeatTimer();
            this.stopConnectingSyncTimer();
            this.isAnswering = false;
            this.cleanupMediaSession();
            this.stopDurationTimer();
            this.detachRealtimeChannel();

            this.resetTimer = window.setTimeout(() => {
                if(this.isFinal || this.status === status) {
                    this.reset();
                }
            }, resetDelay);
        },
        reset: function() {
            if(this.resetTimer) {
                window.clearTimeout(this.resetTimer);
                this.resetTimer = null;
            }

            this.stopRingingFeedback();
            this.stopRingTimeoutTimer();
            this.stopConnectionTimeoutTimer();
            this.stopRemoteAudioWatchdog();
            this.stopDegradedConnectionTimeoutTimer();
            this.stopReconnectTimeoutTimer();
            this.stopHeartbeatTimer();
            this.stopConnectingSyncTimer();
            this.stopDurationTimer();
            this.stopStartCooldown();
            this.stopAudioRouteSettling();
            this.cleanupMediaSession();
            this.detachRealtimeChannel();

            this.call = null;
            this.direction = null;
            this.status = 'idle';
            this.error = '';
            this.minimized = false;
            this.isMuted = false;
            this.speakerEnabled = false;
            this.localStream = null;
            this.remoteStream = null;
            this.peer = null;
            this.peerSetupPromise = null;
            this.mediaProvider = 'webrtc';
            this.mediaSession = null;
            this.offerSent = false;
            this.mediaReadySent = false;
            this.connectedSignalSent = false;
            this.isAnswering = false;
            this.durationSeconds = 0;
            this.ringSecondsRemaining = 0;
            this.networkState = 'stable';
            this.qualityNotice = '';
            this.lastQualityReportAt = 0;
            this.lastQualitySignature = '';
            this.isStarting = false;
            this.isStartCoolingDown = false;
            this.finalizingCallUuid = null;
            this.mediaSetupAttempt += 1;
            this.heartbeatInFlight = false;
            this.microphonePermissionPromise = null;
            this.syncingCurrentCall = false;
        },
        cleanupMediaSession: function() {
            const peer = this.peer;

            this.mediaSetupAttempt += 1;
            this.stopAudioRouteSettling();
            this.stopRemoteAudioWatchdog();
            this.stopDegradedConnectionTimeoutTimer();
            this.peer = null;
            this.peerSetupPromise = null;

            try {
                peer?.close?.();
            }
            catch(error) {}

            this.releaseMediaStream(this.localStream);
            this.releaseMediaStream(this.remoteStream);

            this.localStream = null;
            this.remoteStream = null;
            this.mediaProvider = 'webrtc';
            this.mediaSession = null;
            this.offerSent = false;
            this.mediaReadySent = false;
            this.connectedSignalSent = false;
            this.exitNativeAudioMode();
            this.closeRingToneContext();
        },
        releaseMediaStream: function(stream) {
            stream?.getTracks?.().forEach((track) => {
                try {
                    track.enabled = false;
                    track.stop();
                }
                catch(error) {}
            });
        },
        closeRingToneContext: function() {
            const context = this.ringToneContext;

            this.ringToneContext = null;

            try {
                if(context && context.state !== 'closed') {
                    context.close?.().catch(() => {});
                }
            }
            catch(error) {}
        },
        startCallCooldown: function(durationMs = callStartCooldownMs) {
            this.stopStartCooldown();
            this.isStartCoolingDown = true;
            this.startCooldownTimer = window.setTimeout(() => {
                this.isStartCoolingDown = false;
                this.startCooldownTimer = null;
            }, durationMs);
        },
        stopStartCooldown: function() {
            if(this.startCooldownTimer) {
                window.clearTimeout(this.startCooldownTimer);
                this.startCooldownTimer = null;
            }
        },
        syncCallSideEffects: function() {
            if(this.status === 'ringing') {
                this.stopConnectionTimeoutTimer();
                this.stopConnectingSyncTimer();
                this.stopRemoteAudioWatchdog();
                this.stopDegradedConnectionTimeoutTimer();
                this.startRingTimeoutTimer();
                this.startRingingFeedback();

                return;
            }

            this.stopRingingFeedback();
            this.stopRingTimeoutTimer();

            if(['accepted', 'connecting'].includes(this.status)) {
                this.startConnectionTimeoutTimer();
                this.startConnectingSyncTimer();
                this.stopRemoteAudioWatchdog();
                this.stopDegradedConnectionTimeoutTimer();
            }
            else {
                this.stopConnectionTimeoutTimer();
                this.stopConnectingSyncTimer();
                this.syncRemoteAudioWatchdog();
                this.syncDegradedConnectionTimeout();
            }
        },
        startDurationTimer: function() {
            if(this.durationTimer) {
                return;
            }

            this.durationTimer = window.setInterval(() => {
                this.durationSeconds += 1;
            }, 1000);
        },
        stopDurationTimer: function() {
            if(this.durationTimer) {
                window.clearInterval(this.durationTimer);
                this.durationTimer = null;
            }
        },
        startRingTimeoutTimer: function() {
            this.stopRingTimeoutTimer();
            this.updateRingSecondsRemaining();

            if(this.ringSecondsRemaining <= 0) {
                this.handleRingingTimeout();

                return;
            }

            this.ringTimeoutTimer = window.setInterval(() => {
                this.updateRingSecondsRemaining();

                if(this.ringSecondsRemaining <= 0) {
                    this.handleRingingTimeout();
                }
            }, 1000);
        },
        stopRingTimeoutTimer: function() {
            if(this.ringTimeoutTimer) {
                window.clearInterval(this.ringTimeoutTimer);
                this.ringTimeoutTimer = null;
            }
        },
        updateRingSecondsRemaining: function() {
            const expiresAt = Date.parse(this.call?.expires_at || '');

            if(Number.isFinite(expiresAt)) {
                this.ringSecondsRemaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));

                return;
            }

            const startedAt = Date.parse(this.call?.timestamps?.started_at || '');

            if(Number.isFinite(startedAt)) {
                const elapsed = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
                this.ringSecondsRemaining = Math.max(0, defaultRingTimeoutSeconds - elapsed);

                return;
            }

            this.ringSecondsRemaining = defaultRingTimeoutSeconds;
        },
        handleRingingTimeout: function() {
            if(this.status !== 'ringing') {
                return false;
            }

            this.error = this.direction === 'outgoing' ? 'No answer.' : 'Missed call.';
            this.endCall('no_answer').catch(() => {
                this.finishCall('missed', 1600);
            });

            return true;
        },
        startConnectionTimeoutTimer: function() {
            if(this.connectionTimeoutTimer || this.status === 'connected') {
                return;
            }

            this.connectionTimeoutTimer = window.setTimeout(async () => {
                this.connectionTimeoutTimer = null;

                if(! ['accepted', 'connecting'].includes(this.status)) {
                    return;
                }

                this.error = 'Audio connection timed out.';
                await this.endCall('connection_timeout');
            }, connectionTimeoutSeconds * 1000);
        },
        stopConnectionTimeoutTimer: function() {
            if(this.connectionTimeoutTimer) {
                window.clearTimeout(this.connectionTimeoutTimer);
                this.connectionTimeoutTimer = null;
            }
        },
        hasLiveRemoteAudio: function() {
            const tracks = this.remoteStream?.getAudioTracks?.() || [];

            return tracks.some((track) => {
                if(! track) {
                    return false;
                }

                if(track.readyState && track.readyState !== 'live') {
                    return false;
                }

                if(track.enabled === false) {
                    return false;
                }

                return track.muted !== true;
            });
        },
        startRemoteAudioWatchdog: function() {
            if(this.remoteAudioWatchdogTimer || ! this.call?.call_uuid || this.status !== 'connected' || this.hasLiveRemoteAudio()) {
                return;
            }

            this.remoteAudioWatchdogTimer = window.setTimeout(async () => {
                this.remoteAudioWatchdogTimer = null;

                if(this.status !== 'connected' || this.hasLiveRemoteAudio() || this.isFinal) {
                    return;
                }

                this.error = 'Remote audio did not connect.';
                await this.endCall('connection_timeout');
            }, connectionTimeoutSeconds * 1000);
        },
        stopRemoteAudioWatchdog: function() {
            if(this.remoteAudioWatchdogTimer) {
                window.clearTimeout(this.remoteAudioWatchdogTimer);
                this.remoteAudioWatchdogTimer = null;
            }
        },
        syncRemoteAudioWatchdog: function() {
            if(this.status === 'connected' && ! this.hasLiveRemoteAudio()) {
                this.startRemoteAudioWatchdog();
            }
            else {
                this.stopRemoteAudioWatchdog();
            }
        },
        shouldWatchDegradedConnection: function() {
            return this.status === 'connected'
                && this.hasLiveRemoteAudio()
                && ['poor', 'reconnecting'].includes(this.networkState);
        },
        startDegradedConnectionTimeoutTimer: function() {
            if(this.degradedConnectionTimeoutTimer || ! this.call?.call_uuid || ! this.shouldWatchDegradedConnection()) {
                return;
            }

            this.degradedConnectionTimeoutTimer = window.setTimeout(async () => {
                this.degradedConnectionTimeoutTimer = null;

                if(! this.shouldWatchDegradedConnection() || this.isFinal) {
                    return;
                }

                if(this.networkState === 'reconnecting') {
                    this.error = 'Audio connection was lost.';
                    await this.endCall('connection_lost');

                    return;
                }

                this.error = 'Poor connection ended the call.';
                await this.endCall('connection_timeout');
            }, degradedConnectionTimeoutSeconds * 1000);
        },
        stopDegradedConnectionTimeoutTimer: function() {
            if(this.degradedConnectionTimeoutTimer) {
                window.clearTimeout(this.degradedConnectionTimeoutTimer);
                this.degradedConnectionTimeoutTimer = null;
            }
        },
        syncDegradedConnectionTimeout: function() {
            if(this.shouldWatchDegradedConnection()) {
                this.startDegradedConnectionTimeoutTimer();

                return;
            }

            this.stopDegradedConnectionTimeoutTimer();
        },
        startConnectingSyncTimer: function() {
            if(this.connectingSyncTimer || ! this.call?.call_uuid || ! ['accepted', 'connecting'].includes(this.status)) {
                return;
            }

            this.connectingSyncTimer = window.setInterval(() => {
                this.reconcileActiveCall({
                    setupIfNeeded: true
                }).catch(() => {});
            }, connectingSyncIntervalMs);

            window.setTimeout(() => {
                this.reconcileActiveCall({
                    setupIfNeeded: true
                }).catch(() => {});
            }, 0);
        },
        stopConnectingSyncTimer: function() {
            if(this.connectingSyncTimer) {
                window.clearInterval(this.connectingSyncTimer);
                this.connectingSyncTimer = null;
            }
        },
        startReconnectTimeoutTimer: function() {
            if(this.reconnectTimeoutTimer || ! this.isActive) {
                return;
            }

            this.reconnectTimeoutTimer = window.setTimeout(async () => {
                this.reconnectTimeoutTimer = null;

                if(! this.isActive || this.networkState !== 'reconnecting') {
                    return;
                }

                this.error = 'Audio connection was lost.';
                await this.endCall('connection_lost');
            }, connectionTimeoutSeconds * 1000);
        },
        stopReconnectTimeoutTimer: function() {
            if(this.reconnectTimeoutTimer) {
                window.clearTimeout(this.reconnectTimeoutTimer);
                this.reconnectTimeoutTimer = null;
            }
        },
        startHeartbeatTimer: function() {
            if(this.heartbeatTimer || ! this.call?.call_uuid || this.status !== 'connected') {
                return;
            }

            this.heartbeatTimer = window.setInterval(() => {
                this.sendHeartbeat().catch(() => {});
            }, heartbeatIntervalMs);
            window.setTimeout(() => {
                this.sendHeartbeat().catch(() => {});
            }, 0);
        },
        stopHeartbeatTimer: function() {
            if(this.heartbeatTimer) {
                window.clearInterval(this.heartbeatTimer);
                this.heartbeatTimer = null;
            }

            this.heartbeatInFlight = false;
        },
        quietRemoteOutputForRouteChange: function() {
            if(typeof window === 'undefined') {
                return;
            }

            this.audioRouteSettling = true;
            this.setRemoteOutputVolume(0);

            if(this.audioRouteSettleTimer) {
                window.clearTimeout(this.audioRouteSettleTimer);
            }

            this.audioRouteSettleTimer = window.setTimeout(() => {
                this.audioRouteSettleTimer = null;
                this.audioRouteSettling = false;
                this.setRemoteOutputVolume(1);
            }, speakerRouteSettleMs);
        },
        stopAudioRouteSettling: function() {
            if(this.audioRouteSettleTimer) {
                window.clearTimeout(this.audioRouteSettleTimer);
                this.audioRouteSettleTimer = null;
            }

            this.audioRouteSettling = false;
            this.setRemoteOutputVolume(1);
        },
        setRemoteOutputVolume: function(volume) {
            try {
                this.peer?.setRemoteOutputVolume?.(volume);
            }
            catch(error) {}
        },
        attachRemoteOutputElement: function(element) {
            try {
                this.peer?.attachRemoteOutputElement?.(element);
            }
            catch(error) {}
        },
        unlockAudioFeedback: function() {
            this.ensureRingToneContext();
        },
        yieldToUiFrame: async function() {
            if(typeof window === 'undefined') {
                return;
            }

            await new Promise((resolve) => {
                window.requestAnimationFrame?.(() => {
                    window.setTimeout(resolve, uiFrameYieldDelayMs);
                }) || window.setTimeout(resolve, uiFrameYieldDelayMs);
            });
        },
        queueNativeBridgeTask: function(callback, delayMs = 0) {
            if(typeof window === 'undefined') {
                return false;
            }

            window.setTimeout(() => {
                try {
                    callback?.();
                }
                catch(error) {}
            }, Math.max(0, Number(delayMs || 0)));

            return true;
        },
        ensureRingToneContext: function() {
            if(typeof window === 'undefined') {
                return null;
            }

            const AudioContext = window.AudioContext || window.webkitAudioContext;

            if(! AudioContext) {
                return null;
            }

            if(! this.ringToneContext) {
                this.ringToneContext = markRaw(new AudioContext());
            }

            if(this.ringToneContext.state === 'suspended') {
                this.ringToneContext.resume().catch(() => {});
            }

            return this.ringToneContext;
        },
        startRingingFeedback: function() {
            if(this.ringToneTimer || this.status !== 'ringing') {
                return;
            }

            const playTone = () => {
                if(this.status !== 'ringing') {
                    this.stopRingingFeedback();

                    return;
                }

                this.direction === 'incoming' ? this.playIncomingRingTone() : this.playOutgoingRingTone();
            };
            const nativeBridgeHandled = this.startNativeRingtone(this.direction);

            if(! nativeBridgeHandled) {
                playTone();
            }

            this.ringToneTimer = window.setInterval(() => {
                if(! this.nativeRingtoneActive) {
                    playTone();
                }
            }, this.direction === 'incoming' ? incomingRingIntervalMs : outgoingRingIntervalMs);
        },
        stopRingingFeedback: function() {
            if(this.ringToneTimer) {
                window.clearInterval(this.ringToneTimer);
                this.ringToneTimer = null;
            }

            this.stopNativeRingtone();
            this.clearRingToneTimeouts();
            this.stopActiveRingToneNodes();

            if(typeof navigator !== 'undefined' && navigator.vibrate) {
                navigator.vibrate(0);
            }
        },
        playOutgoingRingTone: function() {
            this.playDualTone([440, 480], 1.05, 0.07, 'sine');
        },
        playIncomingRingTone: function() {
            this.queueRingTone(0, () => this.playDualTone([880, 660], 0.36, 0.15, 'triangle'));
            this.queueRingTone(520, () => this.playDualTone([980, 740], 0.36, 0.16, 'triangle'));
            this.queueRingTone(1100, () => this.playDualTone([880, 660], 0.42, 0.15, 'triangle'));

            if(typeof navigator !== 'undefined' && navigator.vibrate) {
                navigator.vibrate([420, 140, 420, 520]);
            }
        },
        queueRingTone: function(delayMs, callback) {
            if(! delayMs) {
                callback();

                return;
            }

            const timeout = window.setTimeout(() => {
                this.ringToneTimeouts = this.ringToneTimeouts.filter((item) => item !== timeout);

                if(this.status === 'ringing') {
                    callback();
                }
            }, delayMs);

            this.ringToneTimeouts.push(timeout);
        },
        clearRingToneTimeouts: function() {
            this.ringToneTimeouts.forEach((timeout) => window.clearTimeout(timeout));
            this.ringToneTimeouts = [];
        },
        stopActiveRingToneNodes: function() {
            this.ringToneNodes.forEach((node) => {
                try {
                    node.stop?.();
                }
                catch(error) {}

                try {
                    node.disconnect?.();
                }
                catch(error) {}
            });

            this.ringToneNodes = [];
        },
        playDualTone: function(frequencies, duration, volume, oscillatorType = 'sine') {
            return frequencies
                .map((frequency) => this.playTone(frequency, duration, volume / frequencies.length, oscillatorType))
                .some(Boolean);
        },
        playTone: function(frequency, duration, volume, oscillatorType = 'sine') {
            const context = this.ensureRingToneContext();

            if(! context) {
                return false;
            }

            try {
                const oscillator = context.createOscillator();
                const gain = context.createGain();
                const now = context.currentTime;

                oscillator.type = oscillatorType;
                oscillator.frequency.setValueAtTime(frequency, now);
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(volume, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

                oscillator.connect(gain);
                gain.connect(context.destination);
                this.ringToneNodes.push(oscillator, gain);
                oscillator.start(now);
                oscillator.stop(now + duration + 0.03);
                oscillator.onended = () => {
                    try {
                        oscillator.disconnect();
                        gain.disconnect();
                    }
                    catch(error) {}

                    this.ringToneNodes = this.ringToneNodes.filter((node) => node !== oscillator && node !== gain);
                };

                return true;
            }
            catch(error) {
                return false;
            }
        },
        getNativeCallBridge: function() {
            if(typeof window === 'undefined') {
                return null;
            }

            return window.ZulorsCallAudio || null;
        },
        enterNativeAudioMode: function() {
            const bridge = this.getNativeCallBridge();

            this.queueNativeBridgeTask(() => {
                bridge?.enterCall?.();
                bridge?.setSpeakerEnabled?.(this.speakerEnabled);
            });
        },
        exitNativeAudioMode: function() {
            const bridge = this.getNativeCallBridge();

            this.queueNativeBridgeTask(() => {
                bridge?.leaveCall?.();
            });
        },
        startNativeRingtone: function(direction) {
            const bridge = this.getNativeCallBridge();

            if(! bridge?.startRingtone) {
                this.nativeRingtoneActive = false;

                return false;
            }

            try {
                this.nativeRingtoneActive = Boolean(bridge.startRingtone(direction || 'incoming'));

                return this.nativeRingtoneActive;
            }
            catch(error) {
                this.nativeRingtoneActive = false;

                return false;
            }
        },
        stopNativeRingtone: function() {
            const bridge = this.getNativeCallBridge();

            this.nativeRingtoneActive = false;

            try {
                bridge?.stopRingtone?.();
            }
            catch(error) {}
        },
        setNativeSpeakerEnabled: function(isEnabled) {
            const bridge = this.getNativeCallBridge();

            this.queueNativeBridgeTask(() => {
                bridge?.setSpeakerEnabled?.(isEnabled);
            });
        },
        attachRealtimeChannel: function(chatId) {
            if(! chatId || ! window.ColibriBRD) {
                return false;
            }

            const channel = BRD.getChannel('CHAT', [chatId]);

            if(this.activeChannel === channel) {
                return true;
            }

            this.detachRealtimeChannel();
            this.activeChannel = channel;

            ColibriBRD.private(channel)
                .listen(BRD.getEvent('CALL_INCOMING'), this.handleIncoming)
                .listen(BRD.getEvent('CALL_ANSWERED'), this.handleAnswered)
                .listen(BRD.getEvent('CALL_DECLINED'), this.handleDeclined)
                .listen(BRD.getEvent('CALL_ENDED'), this.handleEnded)
                .listen(BRD.getEvent('CALL_BUSY'), this.handleBusy)
                .listen(BRD.getEvent('CALL_SIGNAL'), this.handleSignal);

            return true;
        },
        detachRealtimeChannel: function() {
            if(! this.activeChannel || ! window.ColibriBRD) {
                this.activeChannel = null;

                return false;
            }

            ColibriBRD.private(this.activeChannel)
                .stopListening(BRD.getEvent('CALL_INCOMING'))
                .stopListening(BRD.getEvent('CALL_ANSWERED'))
                .stopListening(BRD.getEvent('CALL_DECLINED'))
                .stopListening(BRD.getEvent('CALL_ENDED'))
                .stopListening(BRD.getEvent('CALL_BUSY'))
                .stopListening(BRD.getEvent('CALL_SIGNAL'));

            this.activeChannel = null;

            return true;
        },
        isCurrentCall: function(call = {}) {
            return Boolean(this.call?.call_uuid && call?.call_uuid === this.call.call_uuid);
        },
        sendBusyNotice: function() {
            return false;
        }
    }
});

export { createCallStore };
