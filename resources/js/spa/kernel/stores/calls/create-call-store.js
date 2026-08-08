import { defineStore } from 'pinia';
import { markRaw } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import BRD from '@/kernel/websockets/brd/index.js';
import { createAudioCallPeer, isAudioCallSupported } from '@/kernel/services/calls/webrtc-audio-call.js';

const finalStatuses = ['ended', 'missed', 'declined', 'busy', 'failed'];
const connectingStatuses = ['ringing', 'accepted', 'connecting'];
const defaultRingTimeoutSeconds = 45;
const connectionTimeoutSeconds = 24;
const qualityReportThrottleMs = 10000;
const iceServerRefreshSkewMs = 60000;

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
            speakerEnabled: true,
            localStream: null,
            remoteStream: null,
            peer: null,
            activeChannel: null,
            offerSent: false,
            connectedSignalSent: false,
            isAnswering: false,
            durationSeconds: 0,
            durationTimer: null,
            ringSecondsRemaining: 0,
            ringTimeoutTimer: null,
            connectionTimeoutTimer: null,
            reconnectTimeoutTimer: null,
            ringToneContext: null,
            ringToneTimer: null,
            networkState: 'stable',
            qualityNotice: '',
            lastQualityReportAt: 0,
            lastQualitySignature: '',
            iceServers: null,
            iceServersExpiresAt: 0
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
            return isAudioCallSupported();
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
                && ! this.isVisible
            );
        },
        startCall: async function(chatData = {}, mediaType = 'audio') {
            if(! this.canStartCall(chatData)) {
                throw new Error('Audio call is not available for this chat.');
            }

            if(mediaType !== 'audio') {
                throw new Error('Video calls are not available yet.');
            }

            this.unlockAudioFeedback();

            let response = null;

            try {
                response = await colibriAPI().messenger().with({
                    chat_id: chatData.chat_id,
                    media_type: mediaType
                }).sendTo('calls/start');
            }
            catch(error) {
                throw new Error(error.response?.data?.message || error.message || 'Unable to start audio call.');
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

            this.isAnswering = true;
            this.status = 'connecting';
            this.stopRingingFeedback();
            this.enterNativeAudioMode();

            try {
                await this.setupPeer();

                const response = await colibriAPI().messenger()
                    .sendTo(`calls/${callUuid}/answer`);

                this.setCall(response.data.data.call, {
                    direction: 'incoming',
                    status: response.data.data.call.status || 'accepted'
                });
                this.minimized = false;
                this.attachRealtimeChannel(this.call.chat_id);
                await this.sendSignal('ready', {});

                return true;
            }
            catch(error) {
                this.error = error.message || 'Unable to start audio call.';

                try {
                    await colibriAPI().messenger().sendTo(`calls/${callUuid}/decline`);
                }
                catch(declineError) {}

                this.finishCall('failed', 2600);

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

            try {
                await colibriAPI().messenger()
                    .sendTo(`calls/${this.call.call_uuid}/decline`);
            }
            finally {
                this.finishCall('declined');
            }
        },
        endCall: async function(reason = 'user_ended') {
            if(! this.call?.call_uuid) {
                this.finishCall('ended');

                return false;
            }

            reason = typeof reason === 'string' && reason ? reason : 'user_ended';

            try {
                await colibriAPI().messenger().with({
                    reason: reason
                })
                    .sendTo(`calls/${this.call.call_uuid}/end`);
            }
            finally {
                this.finishCall(['connection_lost', 'connection_timeout', 'ice_failed'].includes(reason) ? 'failed' : 'ended');
            }
        },
        sendSignal: async function(signalType, signal = {}) {
            if(! this.call?.call_uuid) {
                return false;
            }

            await colibriAPI().messenger().with({
                signal_type: signalType,
                signal: signal || {}
            }).sendTo(`calls/${this.call.call_uuid}/signal`);
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
                    this.enterNativeAudioMode();
                    await this.setupPeer();
                    await this.createOffer();
                }
                catch(error) {
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
                this.finishCall(call.status || 'ended');
            }
        },
        handleBusy: function(event = {}) {
            const data = normalizeEventData(event);

            if(data.reason === 'busy' || data.call?.status === 'busy') {
                this.error = 'User is busy on another call.';
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
                if(data.signal_type === 'offer') {
                    this.enterNativeAudioMode();
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
                    this.enterNativeAudioMode();
                    await this.setupPeer();
                    await this.createOffer();
                }
            }
            catch(error) {
                this.error = error.message || 'Unable to connect audio call.';
                await this.endCall();
            }
        },
        setupPeer: async function() {
            if(this.peer) {
                return this.peer;
            }

            const iceServers = await this.resolveIceServers();
            const peer = createAudioCallPeer({
                onSignal: (signalType, signal) => {
                    this.sendSignal(signalType, signal).catch(() => {});
                },
                onLocalStream: (stream) => {
                    this.localStream = markRaw(stream);
                },
                onRemoteStream: (stream) => {
                    this.remoteStream = markRaw(stream);
                },
                onConnected: () => {
                    this.markConnected(true);
                },
                onStateChange: (state) => {
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
            }, {
                iceServers: iceServers
            });

            this.peer = markRaw(peer);
            this.enterNativeAudioMode();
            await this.peer.ensurePeerConnection(this.call?.media_type || 'audio');

            return this.peer;
        },
        createOffer: async function() {
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
            if(this.status !== 'connected') {
                this.status = 'connecting';
                this.stopRingingFeedback();
                this.startConnectionTimeoutTimer();
            }
        },
        markConnected: function(shouldNotify = true) {
            this.status = 'connected';
            this.networkState = 'stable';
            this.qualityNotice = '';
            this.stopRingingFeedback();
            this.stopRingTimeoutTimer();
            this.stopConnectionTimeoutTimer();
            this.stopReconnectTimeoutTimer();
            this.startDurationTimer();

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

                return true;
            }

            if(state === 'reconnecting') {
                this.networkState = 'reconnecting';
                this.qualityNotice = 'Reconnecting...';
                this.startReconnectTimeoutTimer();

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
            if(! this.call?.call_uuid) {
                return false;
            }

            await colibriAPI().messenger().with(stats)
                .sendTo(`calls/${this.call.call_uuid}/quality`);

            return true;
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
        toggleMute: function() {
            this.isMuted = ! this.isMuted;
            this.peer?.setMuted(this.isMuted);
        },
        toggleSpeaker: function() {
            this.speakerEnabled = ! this.speakerEnabled;
            this.setNativeSpeakerEnabled(this.speakerEnabled);
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
            this.status = status;
            this.stopRingingFeedback();
            this.stopRingTimeoutTimer();
            this.stopConnectionTimeoutTimer();
            this.stopReconnectTimeoutTimer();
            this.peer?.close();
            this.peer = null;
            this.offerSent = false;
            this.isAnswering = false;
            this.localStream = null;
            this.remoteStream = null;
            this.stopDurationTimer();
            this.exitNativeAudioMode();
            this.detachRealtimeChannel();

            window.setTimeout(() => {
                if(this.isFinal || this.status === status) {
                    this.reset();
                }
            }, resetDelay);
        },
        reset: function() {
            this.peer?.close();
            this.stopRingingFeedback();
            this.stopRingTimeoutTimer();
            this.stopConnectionTimeoutTimer();
            this.stopReconnectTimeoutTimer();
            this.stopDurationTimer();
            this.exitNativeAudioMode();
            this.detachRealtimeChannel();

            this.call = null;
            this.direction = null;
            this.status = 'idle';
            this.error = '';
            this.minimized = false;
            this.isMuted = false;
            this.speakerEnabled = true;
            this.localStream = null;
            this.remoteStream = null;
            this.peer = null;
            this.offerSent = false;
            this.connectedSignalSent = false;
            this.isAnswering = false;
            this.durationSeconds = 0;
            this.ringSecondsRemaining = 0;
            this.networkState = 'stable';
            this.qualityNotice = '';
            this.lastQualityReportAt = 0;
            this.lastQualitySignature = '';
        },
        syncCallSideEffects: function() {
            if(this.status === 'ringing') {
                this.stopConnectionTimeoutTimer();
                this.startRingTimeoutTimer();
                this.startRingingFeedback();

                return;
            }

            this.stopRingingFeedback();
            this.stopRingTimeoutTimer();

            if(['accepted', 'connecting'].includes(this.status)) {
                this.startConnectionTimeoutTimer();
            }
            else {
                this.stopConnectionTimeoutTimer();
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
            this.finishCall('missed', 1600);

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
            }, 10000);
        },
        stopReconnectTimeoutTimer: function() {
            if(this.reconnectTimeoutTimer) {
                window.clearTimeout(this.reconnectTimeoutTimer);
                this.reconnectTimeoutTimer = null;
            }
        },
        unlockAudioFeedback: function() {
            this.ensureRingToneContext();
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

            const isIncoming = this.direction === 'incoming';
            const playTone = () => {
                if(this.status !== 'ringing') {
                    this.stopRingingFeedback();

                    return;
                }

                isIncoming ? this.playIncomingRingTone() : this.playOutgoingRingTone();
            };

            playTone();
            this.ringToneTimer = window.setInterval(playTone, isIncoming ? 1700 : 2300);
        },
        stopRingingFeedback: function() {
            if(this.ringToneTimer) {
                window.clearInterval(this.ringToneTimer);
                this.ringToneTimer = null;
            }

            if(typeof navigator !== 'undefined' && navigator.vibrate) {
                navigator.vibrate(0);
            }
        },
        playOutgoingRingTone: function() {
            this.playTone(470, 0.16, 0.055);
            window.setTimeout(() => {
                if(this.status === 'ringing') {
                    this.playTone(520, 0.16, 0.055);
                }
            }, 280);
        },
        playIncomingRingTone: function() {
            this.playTone(860, 0.2, 0.075);
            window.setTimeout(() => {
                if(this.status === 'ringing') {
                    this.playTone(690, 0.25, 0.075);
                }
            }, 290);

            if(typeof navigator !== 'undefined' && navigator.vibrate) {
                navigator.vibrate([260, 90, 260]);
            }
        },
        playTone: function(frequency, duration, volume) {
            const context = this.ensureRingToneContext();

            if(! context) {
                return false;
            }

            try {
                const oscillator = context.createOscillator();
                const gain = context.createGain();
                const now = context.currentTime;

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(frequency, now);
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(volume, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start(now);
                oscillator.stop(now + duration + 0.03);
                oscillator.onended = () => {
                    oscillator.disconnect();
                    gain.disconnect();
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

            try {
                bridge?.enterCall?.();
                bridge?.setSpeakerEnabled?.(this.speakerEnabled);
            }
            catch(error) {}
        },
        exitNativeAudioMode: function() {
            const bridge = this.getNativeCallBridge();

            try {
                bridge?.leaveCall?.();
            }
            catch(error) {}
        },
        setNativeSpeakerEnabled: function(isEnabled) {
            const bridge = this.getNativeCallBridge();

            try {
                bridge?.setSpeakerEnabled?.(isEnabled);
            }
            catch(error) {}
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
