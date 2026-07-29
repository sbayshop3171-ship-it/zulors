import { colibriEventBus } from '@/kernel/events/bus/index.js';

function replacePostInCollection(collection, postData) {
	const idx = collection.findIndex(p => p.id === postData.id);

	if (idx !== -1) {
		collection.splice(idx, 1, postData);

		return true;
	}

	return false;
}

export function postDeleteListener ({ store, options }) {
	if (! options?.deleteAware) {
		return false;
	}
  
	// HMR guard so we don’t attach twice.
	if (store._postDeleteListenerAttached) {
		return false;
	}

	store._postDeleteListenerAttached = true;
  
	// Listen once for every post-aware store.
	colibriEventBus.on('timeline:post-deleted', (postId) => {
		const idx = store.posts.findIndex(p => p.id === postId);

		if (idx !== -1) {
			store.posts.splice(idx, 1);
		}
	});

	colibriEventBus.on('timeline:post-updated', (postData) => {
		if(replacePostInCollection(store.posts, postData) && typeof store.persistFirstPage === 'function') {
			store.persistFirstPage();
		}
	});
}
