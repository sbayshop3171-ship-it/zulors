/**
 * Delta Sync Manager
 * Efficiently syncs only new/updated messages after reconnection
 * Reduces payload size and network latency on reconnect
 */

import { getSyncState, updateSyncState } from '../cache/idb-manager.js';

class DeltaSyncManager {
	constructor() {
		this.syncInProgress = new Map();
		this.pendingUpdates = new Map();
	}

	/**
	 * Build delta sync request
	 * Fetch only new messages since last sync
	 */
	async buildDeltaSyncRequest(chatId, chatStore) {
		const syncState = await getSyncState(chatId);
		const lastMessageId = syncState.last_message_id;
		const lastSync = syncState.last_sync ? new Date(syncState.last_sync) : null;

		return {
			chat_id: chatId,
			since_message_id: lastMessageId,
			since_timestamp: lastSync?.toISOString(),
			include_reactions: true,
			include_deletes: true
		};
	}

	/**
	 * Apply delta sync response
	 * Merge new messages into local state efficiently
	 */
	async applyDeltaSync(chatId, deltaResponse, chatStore) {
		if (!deltaResponse || !Array.isArray(deltaResponse.messages)) {
			return { applied: 0, conflicts: 0 };
		}

		let applied = 0;
		let conflicts = 0;

		// Apply new messages
		for (const message of deltaResponse.messages) {
			const existingMessage = chatStore.chatMessages.find(m => m.id === message.id);

			if (!existingMessage) {
				chatStore.appendMessage(message);
				applied++;
			} else if (existingMessage.updated_at !== message.updated_at) {
				chatStore.upsertMessage(message);
				conflicts++;
			}
		}

		// Apply deleted messages
		if (Array.isArray(deltaResponse.deleted_ids)) {
			for (const messageId of deltaResponse.deleted_ids) {
				chatStore.markMessageAsDeleted(messageId);
				applied++;
			}
		}

		// Update sync state
		const lastMessage = deltaResponse.messages[deltaResponse.messages.length - 1];
		if (lastMessage) {
			await updateSyncState(chatId, {
				last_sync: new Date().toISOString(),
				last_message_id: lastMessage.id,
				last_sync_count: applied
			});
		}

		return { applied, conflicts };
	}

	/**
	 * Handle reconnection with delta sync
	 * Automatically fetch and merge missed messages
	 */
	async handleReconnection(chatId, chatStore, apiClient) {
		if (this.syncInProgress.has(chatId)) {
			return; // Sync already in progress
		}

		this.syncInProgress.set(chatId, true);

		try {
			const deltaRequest = await this.buildDeltaSyncRequest(chatId, chatStore);

			// Fetch delta from server
			const response = await apiClient.post(
				`/api/v1/chats/${chatId}/sync/delta`,
				deltaRequest
			);

			// Apply changes locally
			const result = await this.applyDeltaSync(chatId, response.data, chatStore);

			console.log(`✅ Delta sync: Applied ${result.applied} messages, ${result.conflicts} conflicts`);

			return result;
		} catch (error) {
			console.error('Delta sync failed:', error);
			return { applied: 0, conflicts: 0, error };
		} finally {
			this.syncInProgress.delete(chatId);
		}
	}

	/**
	 * Queue pending update for batching
	 */
	queueUpdate(chatId, messageId, update) {
		if (!this.pendingUpdates.has(chatId)) {
			this.pendingUpdates.set(chatId, []);
		}

		this.pendingUpdates.get(chatId).push({
			message_id: messageId,
			update,
			timestamp: Date.now()
		});
	}

	/**
	 * Flush pending updates in batch
	 */
	async flushPendingUpdates(chatId, apiClient) {
		const updates = this.pendingUpdates.get(chatId);
		if (!updates || updates.length === 0) {
			return;
		}

		try {
			await apiClient.post(`/api/v1/chats/${chatId}/sync/batch`, {
				updates: updates
			});

			this.pendingUpdates.delete(chatId);
		} catch (error) {
			console.error('Failed to flush pending updates:', error);
		}
	}
}

export default new DeltaSyncManager();
