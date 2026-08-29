/**
 * IndexedDB Manager for instant message/media caching
 * Enables 0ms local state rendering for chat messages and media
 */

let dbInstance = null;

const DB_NAME = 'ZulorsChatDB';
const DB_VERSION = 1;
const STORES = {
	MESSAGES: 'messages',
	MEDIA: 'media',
	REACTIONS: 'reactions',
	SYNC_STATE: 'sync_state'
};

const initializeDB = async () => {
	if (dbInstance) {
		return dbInstance;
	}

	return new Promise((resolve, reject) => {
		const request = indexedDB.open(DB_NAME, DB_VERSION);

		request.onerror = () => reject(request.error);
		request.onsuccess = () => {
			dbInstance = request.result;
			resolve(dbInstance);
		};

		request.onupgradeneeded = (event) => {
			const db = event.target.result;

			// Messages store
			if (!db.objectStoreNames.contains(STORES.MESSAGES)) {
				const messageStore = db.createObjectStore(STORES.MESSAGES, { keyPath: 'id' });
				messageStore.createIndex('chat_id', 'chat_id', { unique: false });
				messageStore.createIndex('created_at', 'created_at', { unique: false });
				messageStore.createIndex('status', 'status', { unique: false });
			}

			// Media store
			if (!db.objectStoreNames.contains(STORES.MEDIA)) {
				const mediaStore = db.createObjectStore(STORES.MEDIA, { keyPath: 'id' });
				mediaStore.createIndex('message_id', 'message_id', { unique: false });
				mediaStore.createIndex('status', 'status', { unique: false });
			}

			// Reactions store
			if (!db.objectStoreNames.contains(STORES.REACTIONS)) {
				db.createObjectStore(STORES.REACTIONS, { keyPath: 'id' });
			}

			// Sync state store
			if (!db.objectStoreNames.contains(STORES.SYNC_STATE)) {
				db.createObjectStore(STORES.SYNC_STATE, { keyPath: 'chat_id' });
			}
		};
	});
};

const getMessages = async (chatId, limit = 50) => {
	const db = await initializeDB();
	return new Promise((resolve, reject) => {
		const transaction = db.transaction([STORES.MESSAGES], 'readonly');
		const store = transaction.objectStore(STORES.MESSAGES);
		const index = store.index('chat_id');
		const range = IDBKeyRange.only(chatId);
		const request = index.getAll(range);

		request.onerror = () => reject(request.error);
		request.onsuccess = () => {
			// Sort by created_at descending and limit
			const messages = request.result
				.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
				.slice(0, limit);
			resolve(messages);
		};
	});
};

const upsertMessage = async (message) => {
	const db = await initializeDB();
	return new Promise((resolve, reject) => {
		const transaction = db.transaction([STORES.MESSAGES], 'readwrite');
		const store = transaction.objectStore(STORES.MESSAGES);
		const request = store.put(message);

		request.onerror = () => reject(request.error);
		request.onsuccess = () => resolve(request.result);
	});
};

const deleteMessage = async (messageId) => {
	const db = await initializeDB();
	return new Promise((resolve, reject) => {
		const transaction = db.transaction([STORES.MESSAGES], 'readwrite');
		const store = transaction.objectStore(STORES.MESSAGES);
		const request = store.delete(messageId);

		request.onerror = () => reject(request.error);
		request.onsuccess = () => resolve(request.result);
	});
};

const upsertMedia = async (media) => {
	const db = await initializeDB();
	return new Promise((resolve, reject) => {
		const transaction = db.transaction([STORES.MEDIA], 'readwrite');
		const store = transaction.objectStore(STORES.MEDIA);
		const request = store.put(media);

		request.onerror = () => reject(request.error);
		request.onsuccess = () => resolve(request.result);
	});
};

const getMediaByMessageId = async (messageId) => {
	const db = await initializeDB();
	return new Promise((resolve, reject) => {
		const transaction = db.transaction([STORES.MEDIA], 'readonly');
		const store = transaction.objectStore(STORES.MEDIA);
		const index = store.index('message_id');
		const request = index.getAll(messageId);

		request.onerror = () => reject(request.error);
		request.onsuccess = () => resolve(request.result);
	});
};

const clearMessagesForChat = async (chatId) => {
	const db = await initializeDB();
	return new Promise((resolve, reject) => {
		const transaction = db.transaction([STORES.MESSAGES], 'readwrite');
		const store = transaction.objectStore(STORES.MESSAGES);
		const index = store.index('chat_id');
		const range = IDBKeyRange.only(chatId);
		const request = index.openCursor(range);
		let count = 0;

		request.onsuccess = (event) => {
			const cursor = event.target.result;
			if (cursor) {
				cursor.delete();
				count++;
				cursor.continue();
			} else {
				resolve(count);
			}
		};

		request.onerror = () => reject(request.error);
	});
};

const getSyncState = async (chatId) => {
	const db = await initializeDB();
	return new Promise((resolve, reject) => {
		const transaction = db.transaction([STORES.SYNC_STATE], 'readonly');
		const store = transaction.objectStore(STORES.SYNC_STATE);
		const request = store.get(chatId);

		request.onerror = () => reject(request.error);
		request.onsuccess = () => resolve(request.result || { chat_id: chatId, last_sync: null, last_message_id: null });
	});
};

const updateSyncState = async (chatId, syncData) => {
	const db = await initializeDB();
	return new Promise((resolve, reject) => {
		const transaction = db.transaction([STORES.SYNC_STATE], 'readwrite');
		const store = transaction.objectStore(STORES.SYNC_STATE);
		const request = store.put({ chat_id: chatId, ...syncData });

		request.onerror = () => reject(request.error);
		request.onsuccess = () => resolve(request.result);
	});
};

export {
	initializeDB,
	getMessages,
	upsertMessage,
	deleteMessage,
	upsertMedia,
	getMediaByMessageId,
	clearMessagesForChat,
	getSyncState,
	updateSyncState,
	STORES
};
