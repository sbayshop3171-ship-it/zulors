/**
 * WebRTC Calling Signaling Manager
 * Handles P2P call setup, ICE candidates, and SDP negotiation
 * Low-latency signaling for instant voice/video calls
 */

import { ref, computed } from 'vue';

const CONNECTION_STATES = {
	IDLE: 'idle',
	CONNECTING: 'connecting',
	CONNECTED: 'connected',
	FAILED: 'failed',
	DISCONNECTED: 'disconnected'
};

const ICE_SERVERS = [
	{ urls: ['stun:stun.l.google.com:19302'] },
	{ urls: ['stun:stun1.l.google.com:19302'] },
	{ urls: ['stun:stun2.l.google.com:19302'] }
];

export class WebRTCSignalingManager {
	constructor(userId, websocketConnection) {
		this.userId = userId;
		this.websocketConnection = websocketConnection;
		this.peerConnections = new Map();
		this.iceQueues = new Map();
		this.callState = new Map();
		this.signallingStartTime = new Map();
	}

	/**
	 * Initiate call with signaling
	 * Handles SDP offer/answer negotiation
	 */
	async initiateCall(remoteUserId, mediaConstraints = { audio: true, video: false }) {
		try {
			const callId = `call_${Date.now()}_${Math.random()}`;
			this.signallingStartTime.set(callId, Date.now());

			// Create peer connection
			const peerConnection = new RTCPeerConnection({
				iceServers: ICE_SERVERS,
				iceCandidatePoolSize: 10
			});

			// Add local stream
			const localStream = await navigator.mediaDevices.getUserMedia(mediaConstraints);

			for (const track of localStream.getTracks()) {
				peerConnection.addTrack(track, localStream);
			}

			// Setup ICE candidate handling
			this.setupICEHandling(callId, peerConnection, remoteUserId);

			// Setup connection state monitoring
			this.setupConnectionStateMonitoring(callId, peerConnection);

			// Create and send SDP offer
			const offer = await peerConnection.createOffer();
			await peerConnection.setLocalDescription(offer);

			// Store connection
			this.peerConnections.set(callId, {
				peerConnection,
				remoteUserId,
				callId,
				localStream,
				state: CONNECTION_STATES.CONNECTING
			});

			// Send offer through signaling channel
			this.websocketConnection.emit('webrtc.call.offer', {
				call_id: callId,
				caller_id: this.userId,
				callee_id: remoteUserId,
				sdp_offer: offer.sdp,
				media_constraints: mediaConstraints,
				initiated_at: new Date().toISOString()
			});

			return callId;
		} catch (error) {
			console.error('Failed to initiate call:', error);
			throw error;
		}
	}

	/**
	 * Handle incoming call
	 * Send SDP answer and candidates
	 */
	async answerCall(callData) {
		try {
			const { call_id, caller_id, sdp_offer, media_constraints } = callData;

			// Create peer connection
			const peerConnection = new RTCPeerConnection({
				iceServers: ICE_SERVERS,
				iceCandidatePoolSize: 10
			});

			// Set remote description
			const remoteOffer = new RTCSessionDescription({
				type: 'offer',
				sdp: sdp_offer
			});

			await peerConnection.setRemoteDescription(remoteOffer);

			// Add local stream
			const localStream = await navigator.mediaDevices.getUserMedia(media_constraints);

			for (const track of localStream.getTracks()) {
				peerConnection.addTrack(track, localStream);
			}

			// Setup handlers
			this.setupICEHandling(call_id, peerConnection, caller_id);
			this.setupConnectionStateMonitoring(call_id, peerConnection);

			// Create and send SDP answer
			const answer = await peerConnection.createAnswer();
			await peerConnection.setLocalDescription(answer);

			// Store connection
			this.peerConnections.set(call_id, {
				peerConnection,
				remoteUserId: caller_id,
				callId: call_id,
				localStream,
				state: CONNECTION_STATES.CONNECTING
			});

			// Send answer through signaling
			this.websocketConnection.emit('webrtc.call.answer', {
				call_id,
				answerer_id: this.userId,
				caller_id,
				sdp_answer: answer.sdp,
				answered_at: new Date().toISOString()
			});

			return call_id;
		} catch (error) {
			console.error('Failed to answer call:', error);
			throw error;
		}
	}

	/**
	 * Setup ICE candidate handling
	 * Batch candidates for efficient delivery
	 */
	setupICEHandling(callId, peerConnection, remoteUserId) {
		const iceQueue = [];
		let iceFlushTimeout = null;

		peerConnection.addEventListener('icecandidate', (event) => {
			if (event.candidate) {
				iceQueue.push(event.candidate);

				// Batch ICE candidates for efficiency
				if (!iceFlushTimeout) {
					iceFlushTimeout = setTimeout(() => {
						this.flushICECandidates(callId, iceQueue, remoteUserId);
						iceQueue.length = 0;
						iceFlushTimeout = null;
					}, 10); // Batch every 10ms
				}
			} else {
				// All ICE candidates have been sent
				if (iceQueue.length > 0) {
					this.flushICECandidates(callId, iceQueue, remoteUserId);
					iceQueue.length = 0;
				}
			}
		});

		this.iceQueues.set(callId, iceQueue);
	}

	/**
	 * Flush batched ICE candidates
	 */
	flushICECandidates(callId, candidates, remoteUserId) {
		if (candidates.length === 0) return;

		this.websocketConnection.emit('webrtc.ice.candidates', {
			call_id: callId,
			candidates: candidates.map(c => ({
				candidate: c.candidate,
				sdpMLineIndex: c.sdpMLineIndex,
				sdpMid: c.sdpMid
			})),
			sent_at: new Date().toISOString()
		});
	}

	/**
	 * Handle incoming ICE candidates
	 */
	async addICECandidate(callData) {
		try {
			const connection = this.peerConnections.get(callData.call_id);
			if (!connection) {
				console.warn(`Connection not found for call: ${callData.call_id}`);
				return;
			}

			for (const candidateData of callData.candidates || []) {
				const candidate = new RTCIceCandidate(candidateData);
				await connection.peerConnection.addIceCandidate(candidate);
			}
		} catch (error) {
			console.error('Failed to add ICE candidate:', error);
		}
	}

	/**
	 * Handle incoming SDP answer
	 */
	async handleRemoteAnswer(callData) {
		try {
			const { call_id, sdp_answer } = callData;
			const connection = this.peerConnections.get(call_id);

			if (!connection) {
				console.warn(`Connection not found for call: ${call_id}`);
				return;
			}

			const remoteAnswer = new RTCSessionDescription({
				type: 'answer',
				sdp: sdp_answer
			});

			await connection.peerConnection.setRemoteDescription(remoteAnswer);
		} catch (error) {
			console.error('Failed to handle remote answer:', error);
		}
	}

	/**
	 * Setup connection state monitoring
	 */
	setupConnectionStateMonitoring(callId, peerConnection) {
		const states = {
			connectionState: CONNECTION_STATES.CONNECTING,
			iceConnectionState: CONNECTION_STATES.CONNECTING,
			iceGatheringState: 'new'
		};

		peerConnection.addEventListener('connectionstatechange', () => {
			const newState = peerConnection.connectionState;
			console.log(`Call ${callId} connection state: ${newState}`);

			if (newState === 'connected') {
				const signallingDuration = Date.now() - this.signallingStartTime.get(callId);
				console.log(`✅ Call signalling completed in ${signallingDuration}ms`);
			}

			if (newState === 'failed') {
				this.handleCallFailed(callId);
			}
		});

		peerConnection.addEventListener('iceconnectionstatechange', () => {
			console.log(`Call ${callId} ICE state: ${peerConnection.iceConnectionState}`);
		});
	}

	/**
	 * End call and cleanup
	 */
	endCall(callId) {
		const connection = this.peerConnections.get(callId);
		if (!connection) return;

		// Stop all tracks
		connection.localStream?.getTracks().forEach(track => track.stop());

		// Close peer connection
		connection.peerConnection.close();

		// Cleanup
		this.peerConnections.delete(callId);
		this.iceQueues.delete(callId);
		this.callState.delete(callId);
		this.signallingStartTime.delete(callId);

		// Notify remote
		this.websocketConnection.emit('webrtc.call.ended', {
			call_id: callId,
			ended_by: this.userId,
			ended_at: new Date().toISOString()
		});
	}

	/**
	 * Handle call failure
	 */
	handleCallFailed(callId) {
		const connection = this.peerConnections.get(callId);
		if (connection) {
			connection.state = CONNECTION_STATES.FAILED;
			this.endCall(callId);
		}
	}

	/**
	 * Get call statistics
	 */
	async getCallStats(callId) {
		const connection = this.peerConnections.get(callId);
		if (!connection) return null;

		const stats = {
			callId,
			audio: {},
			video: {},
			connection: {}
		};

		const report = await connection.peerConnection.getStats();

		for (const stat of report.values()) {
			if (stat.type === 'inbound-rtp') {
				const mediaType = stat.kind === 'audio' ? 'audio' : 'video';
				stats[mediaType].inbound = {
					bytesReceived: stat.bytesReceived,
					packetsLost: stat.packetsLost,
					jitter: stat.jitter,
					fractionLost: stat.fractionLost
				};
			} else if (stat.type === 'outbound-rtp') {
				const mediaType = stat.kind === 'audio' ? 'audio' : 'video';
				stats[mediaType].outbound = {
					bytesSent: stat.bytesSent,
					framesEncoded: stat.framesEncoded,
					frameHeight: stat.frameHeight,
					frameWidth: stat.frameWidth
				};
			} else if (stat.type === 'candidate-pair' && stat.state === 'succeeded') {
				stats.connection = {
					availableOutgoingBitrate: stat.availableOutgoingBitrate,
					currentRoundTripTime: stat.currentRoundTripTime,
					availableIncomingBitrate: stat.availableIncomingBitrate
				};
			}
		}

		return stats;
	}
}

export default WebRTCSignalingManager;
