import { defineStore } from 'pinia';
import { markRaw } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import BRD from '@/kernel/websockets/brd/index.js';
import { createAudioCallPeer, isAudioCallSupported } from '@/kernel/services/calls/webrtc-audio-call.js';

const finalStatuses = ['ended', 'missed', 'declined', 'busy', 'failed'];
const connectingStatuses = ['ringing', 'accepted', 'connecting'];

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
            durationSeconds: 0,
            durationTimer: null
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

            await this.setupPeer();

            const response = await colibriAPI().messenger()
                .sendTo(`calls/${this.call.call_uuid}/answer`);

            this.setCall(response.data.data.call, {
                direction: 'incoming',
                status: response.data.data.call.status || 'accepted'
            });
            this.minimized = false;
            this.attachRealtimeChannel(this.call.chat_id);
            await this.sendSignal('ready', {});
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
        endCall: async function() {
            if(! this.call?.call_uuid) {
                this.finishCall('ended');

                return false;
            }

            try {
                await colibriAPI().messenger()
                    .sendTo(`calls/${this.call.call_uuid}/end`);
            }
            finally {
                this.finishCall('ended');
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
                await this.setupPeer();
                await this.createOffer();
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
                    if(['failed', 'disconnected'].includes(state)) {
                        this.error = 'Audio connection was interrupted.';
                    }
                }
            });

            this.peer = markRaw(peer);
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
            }
        },
        markConnected: function(shouldNotify = true) {
            this.status = 'connected';
            this.startDurationTimer();

            if(shouldNotify && ! this.connectedSignalSent) {
                this.connectedSignalSent = true;
                this.sendSignal('connected', {}).catch(() => {});
            }
        },
        toggleMute: function() {
            this.isMuted = ! this.isMuted;
            this.peer?.setMuted(this.isMuted);
        },
        toggleSpeaker: function() {
            this.speakerEnabled = ! this.speakerEnabled;
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
        },
        finishCall: function(status = 'ended', resetDelay = 900) {
            this.status = status;
            this.peer?.close();
            this.peer = null;
            this.offerSent = false;
            this.localStream = null;
            this.remoteStream = null;
            this.stopDurationTimer();
            this.detachRealtimeChannel();

            window.setTimeout(() => {
                if(this.isFinal || this.status === status) {
                    this.reset();
                }
            }, resetDelay);
        },
        reset: function() {
            this.peer?.close();
            this.stopDurationTimer();
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
            this.durationSeconds = 0;
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
