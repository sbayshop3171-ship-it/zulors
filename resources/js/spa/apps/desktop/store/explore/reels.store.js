import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { prefetchTimelineMedia } from '@/kernel/services/media-prefetch/index.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';

const reelsCacheLimit = 20;
const reelsPerPage = 30;

const normalizeSeedHash = function(seedHashId = '') {
	return String(seedHashId || '').trim().slice(0, 80);
};

const createFeedSessionId = function() {
	return `reels-${Date.now()}-${Math.random().toString(36).slice(2)}`;
};

const getExploreReelsCacheKey = function(seedHashId = '') {
	const authStore = useAuthStore();
	const seed = normalizeSeedHash(seedHashId) || 'default';

	return `colibri.desktop.explore.reels.first_page.v1.${authStore.userData?.id || 'guest'}.${seed}`;
};

const useExploreReelsStore = defineStore('desktop_explore_reels_store', {
	deleteAware: true,
	state: function() {
		const cachedPosts = readCache(getExploreReelsCacheKey(), []);

		prefetchTimelineMedia(cachedPosts);

		return {
			posts: cachedPosts,
			feedSessionId: createFeedSessionId(),
			refreshReason: 'initial',
			warmPromise: null,
			filter: {
				page: 1,
				seed_hash_id: ''
			}
		};
	},
	actions: {
		initialLoad: async function(seedHashId = '') {
			const nextSeedHashId = normalizeSeedHash(seedHashId);

			if(this.filter.seed_hash_id !== nextSeedHashId) {
				this.reset(nextSeedHashId);
			}

			if(! this.posts.length) {
				await this.refreshFirstPage({
					refreshReason: 'initial'
				});
			}
			else {
				prefetchTimelineMedia(this.posts);
				await this.refreshFirstPage({
					refreshReason: 'resume'
				});
			}
		},
		reset: function(seedHashId = '') {
			this.filter = {
				page: 1,
				seed_hash_id: normalizeSeedHash(seedHashId)
			};

			this.startFeedSession('initial');

			if(! this.hydrateCachedFirstPage()) {
				this.posts = [];
			}
		},
		refreshFirstPage: async function(options = {}) {
			this.startFeedSession(options.refreshReason || 'refresh');

			const pageNumber = this.filter.page;
			this.filter.page = 1;

			return await this.load().then((response) => {
				const posts = response.data.data;

				prefetchTimelineMedia(posts);
				this.posts = posts;
				this.persistFirstPage();

				return response;
			}).finally(() => {
				this.filter.page = pageNumber;
			});
		},
		loadNextPage: async function() {
			this.filter.page += 1;

			return await this.load();
		},
		load: async function() {
			return await colibriAPI().userTimeline().params({
				filter: this.requestFilter()
			}).getFrom('feed');
		},
		appendPosts: function(posts) {
			const existingIds = new Set(this.posts.map((postData) => postData.id));
			const nextPosts = posts.filter((postData) => ! existingIds.has(postData.id));

			prefetchTimelineMedia(nextPosts);
			this.posts = this.posts.concat(nextPosts);
			this.persistFirstPage();

			return nextPosts.length > 0;
		},
		removePost: function(postId) {
			const postIndex = this.posts.findIndex((item) => {
				return item.id == postId;
			});

			if(postIndex === -1) {
				return null;
			}

			const removedPost = this.posts[postIndex];

			this.posts.splice(postIndex, 1);
			this.persistFirstPage();

			return {
				index: postIndex,
				postData: removedPost
			};
		},
		restorePost: function(snapshot) {
			if(! snapshot?.postData) {
				return;
			}

			if(this.posts.some((item) => item.id == snapshot.postData.id)) {
				return;
			}

			this.posts.splice(Math.max(0, Math.min(snapshot.index ?? 0, this.posts.length)), 0, snapshot.postData);
			this.persistFirstPage();
		},
		applyFeedbackSuppression: function(postId, refreshReason = 'feedback') {
			const removed = this.removePost(postId);
			const snapshot = {
				...(removed || {}),
				feedSessionId: this.feedSessionId,
				refreshReason: this.refreshReason,
				page: this.filter.page
			};

			this.startFeedSession(refreshReason);
			this.filter.page = Math.max(1, Math.ceil(this.posts.length / reelsPerPage));

			return snapshot;
		},
		rollbackFeedbackSuppression: function(snapshot) {
			if(! snapshot) {
				return;
			}

			this.feedSessionId = snapshot.feedSessionId || this.feedSessionId;
			this.refreshReason = snapshot.refreshReason || this.refreshReason;
			this.filter.page = snapshot.page || 1;
			this.restorePost(snapshot);
		},
		updatePost: function(postData) {
			const postIndex = this.posts.findIndex((item) => {
				return item.id == postData.id;
			});

			if(postIndex !== -1) {
				this.posts.splice(postIndex, 1, postData);
				this.persistFirstPage();
			}
		},
		updateCommentCount: function(postId, count) {
			const postItem = this.posts.find((item) => {
				return item.id == postId;
			});

			if(postItem) {
				postItem.comments_count = count;
				this.persistFirstPage();
			}
		},
		hydrateCachedFirstPage: function() {
			const cachedPosts = readCache(getExploreReelsCacheKey(this.filter.seed_hash_id), []);

			if(cachedPosts.length) {
				this.posts = cachedPosts;
				prefetchTimelineMedia(cachedPosts);

				return true;
			}

			return false;
		},
		persistFirstPage: function() {
			if(this.filter.page !== 1) {
				return;
			}

			writeCache(getExploreReelsCacheKey(this.filter.seed_hash_id), this.posts.slice(0, reelsCacheLimit));
		},
		startFeedSession: function(refreshReason = 'refresh') {
			this.feedSessionId = createFeedSessionId();
			this.refreshReason = refreshReason;
		},
		requestFilter: function() {
			return {
				page: this.filter.page,
				type: 'reels',
				session_id: this.feedSessionId,
				refresh_reason: this.refreshReason,
				seed_hash_id: this.filter.seed_hash_id
			};
		}
	}
});

export { useExploreReelsStore };
