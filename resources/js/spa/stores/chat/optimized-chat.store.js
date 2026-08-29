/**
 * Sub-100ms Real-Time Messaging Pipeline
 * Integration of all optimization components
 * Achieves instant message/media delivery with optimistic UI
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { v4 as uuidv4 } from 'https://cdn.jsdelivr.net/npm/uuid@4.1.0/+esm';

// Import optimization modules
import OptimisticMediaManager from '@/kernel/media/optimistic-media-manager.js';
import DeltaSyncManager from '@/kernel/sync/delta-sync-manager.js';
import { 
	getMessages, 
	upsertMessage as idbUpsertMessage,
	deleteMessage as idbDeleteMessage,
	getSyncState,
	updateSyncState
} from '@/kernel/cache/idb-manager.js';

export const useOptimizedChatStore = defineStore('optimized-chat', () => {
	// State
	const chatId = ref(null);
	const chatMessages = ref([]);
	const pendingMessages = ref(new Map());
	const mediaUploads = ref(new Map());
	const syncState = ref(null);
	const isLoadingHistory = ref(false);

	// Timeline tracking for latency monitoring
	const messageTimings = ref(new Map());

	/**
	 * Send message with INSTANT optimistic rendering (0ms latency)
	 * 1. Create optimistic message immediately
	 * 2. Render in UI instantly with local state
	 * 3. Send to server asynchronously
	 * 4. Sync when server response arrives
	 */
	const sendMessage = async (content, mediaFiles = [], options = {}) => {
		try {
			const clientUid = uuidv4();
			const messageStartTime = performance.now();

			// 1. Create optimistic message (instant, 0ms)
			const optimisticMessage = {
				id: `local_${clientUid}`,
				client_uid: clientUid,
				chat_id: chatId.value,
				content: content.trim(),
				type: 'text',
				user_id: options.userId,
				user: options.user || {},
				created_at: new Date().toISOString(),
				updated_at: new Date().toISOString(),
				status: 'sending', // Key: shows pending state to sender
				is_pending: true,
				relations: {
					media: [],
					reactions: []
				},
				meta: {
					client_uid: clientUid,
					local_only: true,
					sent_at_ms: messageStartTime
				}
			};

			// Add media to optimistic message
			const optimisticMedias = [];
			for (const file of mediaFiles) {
				const optimisticMedia = OptimisticMediaManager.createOptimisticMedia(
					file,
					optimisticMessage.id,
					clientUid
				);
				optimisticMedias.push(optimisticMedia);

				// Start background upload (non-blocking)
				OptimisticMediaManager.uploadMediaInBackground(
					optimisticMedia,
					`/api/v1/chats/${chatId.value}/media/upload`,
					(progress) => {
						// Update media progress in UI
						const mediaInMessage = optimisticMessage.relations.media.find(
							m => m.id === optimisticMedia.id
						);
						if (mediaInMessage) {
							mediaInMessage.progress = progress.progress;
						}
					},
					(result) => {
						// Media upload complete
						if (result.status === 'ready') {
							const mediaInMessage = optimisticMessage.relations.media.find(
								m => m.id === optimisticMedia.id
							);
							if (mediaInMessage) {
								mediaInMessage.blob_url = result.remoteUrl;
								mediaInMessage.status = 'ready';
							}
						}
					}
				);
			}

			optimisticMessage.relations.media = optimisticMedias;

			// 2. Render message immediately in UI
			chatMessages.value.push(optimisticMessage);
			pendingMessages.value.set(clientUid, optimisticMessage);

			// Store in IndexedDB for persistence
			await idbUpsertMessage(optimisticMessage);

			// Record timing
			const renderTime = performance.now() - messageStartTime;
			messageTimings.value.set(clientUid, {
				optimisticRenderMs: renderTime,
				serverAckMs: 0,
				totalMs: 0
			});

			console.log(`✅ Optimistic message rendered in ${renderTime.toFixed(2)}ms (0ms network latency)`);

			// 3. Send to server asynchronously (non-blocking)
			const serverPromise = sendMessageToServer(optimisticMessage, mediaFiles, clientUid);

			// Don't await - let server sync happen in background
			serverPromise.catch(error => {
				console.error('Server message sync failed:', error);
				optimisticMessage.status = 'error';
			});

			return {
				clientUid,
				optimisticMessage,
				serverSync: serverPromise // Non-blocking promise
			};
		} catch (error) {
			console.error('Failed to send message:', error);
			throw error;
		}
	};

	/**
	 * Send message to server (async, non-blocking)
	 */
	const sendMessageToServer = async (optimisticMessage, mediaFiles, clientUid) => {
		try {
			const serverStartTime = performance.now();

			const formData = new FormData();
			formData.append('content', optimisticMessage.content);
			formData.append('chat_id', chatId.value);
			formData.append('client_uid', clientUid);

			// Add media files
			mediaFiles.forEach(file => {
				formData.append('media[]', file);
			});

			const response = await fetch(`/api/v1/chats/${chatId.value}/messages`, {
				method: 'POST',
				body: formData,
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'Accept': 'application/json'
				}
			});

			if (!response.ok) {
				throw new Error(`Server error: ${response.status}`);
			}

			const data = await response.json();

			// Update timing
			const serverTime = performance.now() - serverStartTime;
			const timings = messageTimings.value.get(clientUid);
			if (timings) {
				timings.serverAckMs = serverTime;
				timings.totalMs = performance.now() - timings.clientSendMs;
			}

			// Remove from pending
			pendingMessages.value.delete(clientUid);

			// Replace optimistic with real message
			const messageIndex = chatMessages.value.findIndex(
				m => m.client_uid === clientUid
			);

			if (messageIndex !== -1) {
				// Preserve local optimistic state while updating with server data
				chatMessages.value[messageIndex] = {
					...data.data,
					status: 'sent',
					is_pending: false
				};

				await idbUpsertMessage(chatMessages.value[messageIndex]);
			}

			console.log(`✅ Message synced with server in ${serverTime.toFixed(2)}ms`);

			return data.data;
		} catch (error) {
			console.error('Message server sync failed:', error);
			optimisticMessage.status = 'error';
			throw error;
		}
	};

	/**
	 * Load chat history with delta sync
	 * Fetches only new messages since last sync
	 */
	const loadChatHistory = async (apiClient, limit = 50) => {
		try {
			if (isLoadingHistory.value) return;
			isLoadingHistory.value = true;

			// Load from IndexedDB first (instant)
			const cachedMessages = await getMessages(chatId.value, limit);
			if (cachedMessages.length > 0) {
				chatMessages.value = cachedMessages;
			}

			// Load sync state
			syncState.value = await getSyncState(chatId.value);

			// Perform delta sync with server
			const deltaResult = await DeltaSyncManager.handleReconnection(
				chatId.value,
				{ chatMessages },
				apiClient
			);

			console.log(`✅ Chat history loaded: ${chatMessages.value.length} messages (${deltaResult.applied} synced)`);

			return chatMessages.value;
		} catch (error) {
			console.error('Failed to load chat history:', error);
		} finally {
			isLoadingHistory.value = false;
		}
	};

	/**
	 * Handle incoming message from WebSocket
	 * Check if it's a server ack for pending message
	 */
	const handleIncomingMessage = async (serverMessage) => {
		// Check if this is ack for pending message
		const clientUid = serverMessage.meta?.client_uid;

		if (clientUid && pendingMessages.value.has(clientUid)) {
			// This is server ack for our message
			const optimisticMessage = pendingMessages.value.get(clientUid);

			// Update with server data
			optimisticMessage.id = serverMessage.id;
			optimisticMessage.status = 'sent';
			optimisticMessage.is_pending = false;
			optimisticMessage.created_at = serverMessage.created_at;
			optimisticMessage.updated_at = serverMessage.updated_at;

			pendingMessages.value.delete(clientUid);

			await idbUpsertMessage(optimisticMessage);
		} else {
			// New message from someone else
			const existingIndex = chatMessages.value.findIndex(m => m.id === serverMessage.id);

			if (existingIndex === -1) {
				chatMessages.value.push(serverMessage);
				await idbUpsertMessage(serverMessage);
			}
		}
	};

	/**
	 * Mark message as read
	 * Instant local update, async server sync
	 */
	const markMessageAsRead = async (messageId) => {
		const message = chatMessages.value.find(m => m.id === messageId);
		if (message) {
			message.read_at = new Date().toISOString();
			await idbUpsertMessage(message);
		}

		// Sync to server async
		fetch(`/api/v1/chats/${chatId.value}/messages/${messageId}/read`, {
			method: 'PUT',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).catch(e => console.error('Failed to sync read status:', e));
	};

	/**
	 * Get latency metrics
	 */
	const getLatencyMetrics = () => {
		const metrics = {
			averageOptimisticRender: 0,
			averageServerAck: 0,
			averageTotal: 0,
			messagesTracked: messageTimings.value.size
		};

		let totalOptimistic = 0;
		let totalServer = 0;
		let totalAll = 0;

		for (const timing of messageTimings.value.values()) {
			totalOptimistic += timing.optimisticRenderMs;
			totalServer += timing.serverAckMs;
			totalAll += timing.totalMs;
		}

		if (messageTimings.value.size > 0) {
			metrics.averageOptimisticRender = (totalOptimistic / messageTimings.value.size).toFixed(2);
			metrics.averageServerAck = (totalServer / messageTimings.value.size).toFixed(2);
			metrics.averageTotal = (totalAll / messageTimings.value.size).toFixed(2);
		}

		return metrics;
	};

	return {
		chatId,
		chatMessages,
		pendingMessages,
		mediaUploads,
		syncState,
		isLoadingHistory,
		sendMessage,
		loadChatHistory,
		handleIncomingMessage,
		markMessageAsRead,
		getLatencyMetrics
	};
});

export default useOptimizedChatStore;
