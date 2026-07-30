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
	const deletedPosts = new Map();

	const persistFirstPage = () => {
		if(typeof store.persistFirstPage === 'function') {
			store.persistFirstPage();
		}
	};
  
	// Listen once for every post-aware store.
	colibriEventBus.on('timeline:post-deleted', (postId) => {
		const idx = store.posts.findIndex(p => p.id === postId);

		if (idx !== -1) {
			deletedPosts.set(postId, {
				index: idx,
				postData: store.posts[idx]
			});

			store.posts.splice(idx, 1);
			persistFirstPage();
		}

		if(Array.isArray(store.update)) {
			const updateIdx = store.update.findIndex(p => p.id === postId);

			if(updateIdx !== -1) {
				store.update.splice(updateIdx, 1);
			}
		}
	});

	colibriEventBus.on('timeline:post-delete-confirmed', (postId) => {
		deletedPosts.delete(postId);
	});

	colibriEventBus.on('timeline:post-delete-failed', ({ postId, postData }) => {
		const deletedPost = deletedPosts.get(postId) || {
			index: 0,
			postData: postData
		};

		if(deletedPost.postData && ! store.posts.some(p => p.id === postId)) {
			store.posts.splice(Math.min(deletedPost.index, store.posts.length), 0, deletedPost.postData);
			persistFirstPage();
		}

		deletedPosts.delete(postId);
	});

	colibriEventBus.on('timeline:post-updated', (postData) => {
		if(replacePostInCollection(store.posts, postData)) {
			persistFirstPage();
		}
	});
}
