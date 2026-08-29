/**
 * Optimistic Media Manager
 * Renders media instantly using Blob URLs with background upload
 * Achieves 0ms media preview latency
 */

import { v4 as uuidv4 } from 'https://cdn.jsdelivr.net/npm/uuid@4.1.0/+esm';

class OptimisticMediaManager {
	constructor() {
		this.uploadQueue = new Map();
		this.blobCache = new Map();
	}

	/**
	 * Create optimistic media preview using Blob URL
	 * Returns 0ms local rendering with background upload
	 */
	createOptimisticMedia(file, messageId, clientUid) {
		const mediaId = uuidv4();
		const blobUrl = URL.createObjectURL(file);
		const uploadStartTime = Date.now();

		const optimisticMedia = {
			id: mediaId,
			message_id: messageId,
			client_uid: clientUid,
			file_name: file.name,
			file_size: file.size,
			file_type: file.type,
			blob_url: blobUrl,
			status: 'uploading',
			progress: 0,
			uploaded_bytes: 0,
			total_bytes: file.size,
			upload_start_time: uploadStartTime,
			local_only: true
		};

		this.uploadQueue.set(mediaId, {
			file,
			optimisticMedia,
			abort: null
		});

		this.blobCache.set(blobUrl, {
			file,
			createdAt: Date.now(),
			mediaId
		});

		return optimisticMedia;
	}

	/**
	 * Upload media to server in background
	 * Non-blocking, reports progress via callback
	 */
	async uploadMediaInBackground(optimisticMedia, uploadEndpoint, onProgress, onComplete) {
		const queueItem = this.uploadQueue.get(optimisticMedia.id);
		if (!queueItem) {
			console.error('Media not found in upload queue');
			return;
		}

		const formData = new FormData();
		formData.append('file', queueItem.file);
		formData.append('message_id', optimisticMedia.message_id);
		formData.append('client_uid', optimisticMedia.client_uid);

		const xhr = new XMLHttpRequest();
		queueItem.abort = () => xhr.abort();

		// Track upload progress
		xhr.upload.addEventListener('progress', (event) => {
			if (event.lengthComputable) {
				const progress = Math.round((event.loaded / event.total) * 100);
				optimisticMedia.progress = progress;
				optimisticMedia.uploaded_bytes = event.loaded;

				if (onProgress) {
					onProgress({
						mediaId: optimisticMedia.id,
						progress,
						uploadedBytes: event.loaded,
						totalBytes: event.total
					});
				}
			}
		});

		xhr.addEventListener('load', () => {
			if (xhr.status === 200) {
				try {
					const response = JSON.parse(xhr.responseText);
					optimisticMedia.status = 'ready';
					optimisticMedia.remote_url = response.url;
					optimisticMedia.local_only = false;

					// Replace blob URL with remote URL
					URL.revokeObjectURL(optimisticMedia.blob_url);
					optimisticMedia.blob_url = response.url;

					this.uploadQueue.delete(optimisticMedia.id);

					if (onComplete) {
						onComplete({
							mediaId: optimisticMedia.id,
							status: 'ready',
							remoteUrl: response.url,
							uploadedAt: new Date().toISOString()
						});
					}
				} catch (error) {
					console.error('Failed to parse upload response:', error);
					optimisticMedia.status = 'error';
					if (onComplete) onComplete({ mediaId: optimisticMedia.id, status: 'error', error });
				}
			} else {
				optimisticMedia.status = 'error';
				if (onComplete) onComplete({ mediaId: optimisticMedia.id, status: 'error', httpStatus: xhr.status });
			}
		});

		xhr.addEventListener('error', () => {
			optimisticMedia.status = 'error';
			if (onComplete) onComplete({ mediaId: optimisticMedia.id, status: 'error', message: 'Upload failed' });
		});

		xhr.addEventListener('abort', () => {
			optimisticMedia.status = 'cancelled';
			if (onComplete) onComplete({ mediaId: optimisticMedia.id, status: 'cancelled' });
		});

		xhr.open('POST', uploadEndpoint);
		xhr.setRequestHeader('Accept', 'application/json');
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

		// Add auth token if available
		const authToken = document.querySelector('meta[name="csrf-token"]')?.content;
		if (authToken) {
			xhr.setRequestHeader('X-CSRF-TOKEN', authToken);
		}

		xhr.send(formData);
	}

	/**
	 * Cancel media upload
	 */
	cancelUpload(mediaId) {
		const queueItem = this.uploadQueue.get(mediaId);
		if (queueItem && queueItem.abort) {
			queueItem.abort();
			this.uploadQueue.delete(mediaId);
		}
	}

	/**
	 * Revoke Blob URL and cleanup
	 */
	cleanup(blobUrl) {
		const cacheItem = this.blobCache.get(blobUrl);
		if (cacheItem) {
			URL.revokeObjectURL(blobUrl);
			this.blobCache.delete(blobUrl);
		}
	}

	/**
	 * Get optimistic media by ID
	 */
	getMedia(mediaId) {
		const queueItem = this.uploadQueue.get(mediaId);
		return queueItem ? queueItem.optimisticMedia : null;
	}

	/**
	 * Cleanup orphaned Blob URLs after 5 minutes
	 */
	scheduleCleanup() {
		setInterval(() => {
			const now = Date.now();
			for (const [blobUrl, cacheItem] of this.blobCache.entries()) {
				if (now - cacheItem.createdAt > 5 * 60 * 1000) {
					this.cleanup(blobUrl);
				}
			}
		}, 60 * 1000); // Check every minute
	}
}

export default new OptimisticMediaManager();
