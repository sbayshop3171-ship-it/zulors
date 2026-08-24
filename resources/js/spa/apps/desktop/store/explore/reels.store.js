import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { defaultFeedMeta, extractFeedMeta, mergeProtectedTail } from '@/kernel/services/feed-session/index.js';
import { prioritizeReelsByRecentSignals, recordReelInteractionSignal } from '@/kernel/services/feed-session/reels-session-signals.js';
import { prefetchReelsPlaybackWindow } from '@/kernel/services/media-prefetch/index.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';

const reelsCacheLimit = 20;
const reelsPerPage = 30;
const warmRequests = new Map();

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

const getExploreReelsViewerKey = function() {
	const authStore = useAuthStore();

	return authStore.userData?.id ? `user:${authStore.userData.id}` : 'guest';
};

const prioritizeIncomingPosts = function(posts = [], seedHashId = '') {
	return prioritizeReelsByRecentSignals(posts, {
		viewerKey: getExploreReelsViewerKey(),
		seedHashId: normalizeSeedHash(seedHashId),
		minimumFresh: 14
	});
};

const buildWarmRequestFilter = function(seedHashId = '', refreshReason = 'warm') {
	return {
		page: 1,
		type: 'reels',
		session_id: createFeedSessionId(),
		refresh_reason: refreshReason,
		seed_hash_id: normalizeSeedHash(seedHashId)
	};
};

const useExploreReelsStore = defineStore('desktop_explore_reels_store', {
	deleteAware: true,
	state: function() {
		const cachedPosts = readCache(getExploreReelsCacheKey(), []);

		return {
			posts: cachedPosts,
			feedSessionId: createFeedSessionId(),
			refreshReason: 'initial',
			feedMeta: {
				...defaultFeedMeta,
				feedFamily: 'reels'
			},
			swipeCount: 0,
			lastTailRerankAt: 0,
			isTailReranking: false,
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
				const warmedPosts = await this.prefetchFirstPage(nextSeedHashId, {
					refreshReason: 'initial'
				}).catch(() => []);

				if(warmedPosts.length) {
					this.posts = warmedPosts;
					this.persistFirstPage();

					return;
				}

				await this.refreshFirstPage({
					refreshReason: 'initial'
				});
			}
			else {
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
			this.swipeCount = 0;
			this.lastTailRerankAt = 0;
			this.isTailReranking = false;

			if(! this.hydrateCachedFirstPage()) {
				this.posts = [];
			}
		},
		refreshFirstPage: async function(options = {}) {
			this.startFeedSession(options.refreshReason || 'refresh');

			const pageNumber = this.filter.page;
			this.filter.page = 1;

			return await this.load().then((response) => {
				const posts = prioritizeIncomingPosts(response.data.data || [], this.filter.seed_hash_id);

				this.posts = posts;
				this.applyFeedMeta(response?.data?.meta);
				prefetchReelsPlaybackWindow(this.posts, 0);
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
		prefetchFirstPage: async function(seedHashId = '', options = {}) {
			const normalizedSeedHashId = normalizeSeedHash(seedHashId);
			const cacheKey = getExploreReelsCacheKey(normalizedSeedHashId);
			const cachedPosts = readCache(cacheKey, []);

			if(cachedPosts.length && ! options.force) {
				const prioritizedCachedPosts = prioritizeIncomingPosts(cachedPosts, normalizedSeedHashId);

				prefetchReelsPlaybackWindow(prioritizedCachedPosts, 0);

				if(this.filter.seed_hash_id === normalizedSeedHashId && ! this.posts.length) {
					this.posts = prioritizedCachedPosts;
				}

				return prioritizedCachedPosts;
			}

			if(warmRequests.has(cacheKey)) {
				return warmRequests.get(cacheKey);
			}

			const warmPromise = colibriAPI().userTimeline().params({
				filter: buildWarmRequestFilter(normalizedSeedHashId, options.refreshReason || 'warm')
			}).getFrom('feed').then((response) => {
				const posts = prioritizeIncomingPosts(response.data.data || [], normalizedSeedHashId);

				this.applyFeedMeta(response?.data?.meta, false);
				writeCache(cacheKey, posts.slice(0, reelsCacheLimit));
				prefetchReelsPlaybackWindow(posts, 0);

				if(this.filter.seed_hash_id === normalizedSeedHashId && ! this.posts.length) {
					this.posts = posts;
				}

				return posts;
			}).catch(() => {
				return [];
			}).finally(() => {
				warmRequests.delete(cacheKey);
			});

			warmRequests.set(cacheKey, warmPromise);

			return warmPromise;
		},
		appendPosts: function(posts) {
			const prioritizedPosts = prioritizeIncomingPosts(posts, this.filter.seed_hash_id);
			const existingIds = new Set(this.posts.map((postData) => postData.id));
			const nextPosts = prioritizedPosts.filter((postData) => ! existingIds.has(postData.id));

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
			const cachedPosts = prioritizeIncomingPosts(
				readCache(getExploreReelsCacheKey(this.filter.seed_hash_id), []),
				this.filter.seed_hash_id
			);

			if(cachedPosts.length) {
				this.posts = cachedPosts;
				prefetchReelsPlaybackWindow(cachedPosts, 0);

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
		recordSwipe: function() {
			this.swipeCount += 1;
		},
		recordInteractionSignal: function(signal) {
			const normalizedSignal = recordReelInteractionSignal(signal, {
				viewerKey: getExploreReelsViewerKey()
			});

			if(! normalizedSignal) {
				return null;
			}

			this.swipeCount += Math.max(0.5, Number(normalizedSignal.rerankWeight || 0));

			return normalizedSignal;
		},
		maybeRerankTail: async function(options = {}) {
			const protectedRadius = Math.max(0, Number(options.protectedRadius ?? 2));
			const activeIndex = Math.max(0, Number(options.activeIndex || 0));
			const threshold = Math.max(1, Number(options.threshold || 4));
			const minIntervalMs = Math.max(1000, Number(options.minIntervalMs || 2500));
			const now = Date.now();

			if(
				! this.feedMeta.reRankAllowed
				|| this.isTailReranking
				|| this.swipeCount < threshold
				|| (now - this.lastTailRerankAt) < minIntervalMs
				|| this.posts.length <= (activeIndex + protectedRadius + 1)
			) {
				return this.posts;
			}

			this.isTailReranking = true;
			this.lastTailRerankAt = now;
			this.swipeCount = 0;

			try {
				const response = await colibriAPI().userTimeline().params({
					filter: {
						page: 1,
						type: 'reels',
						session_id: createFeedSessionId(),
						refresh_reason: 'rerank',
						seed_hash_id: this.filter.seed_hash_id
					}
				}).getFrom('feed');
				const incomingPosts = prioritizeIncomingPosts(
					Array.isArray(response?.data?.data) ? response.data.data : [],
					this.filter.seed_hash_id
				);

				if(! incomingPosts.length) {
					return this.posts;
				}

				this.applyFeedMeta(response?.data?.meta, false);
				this.posts = mergeProtectedTail(this.posts, incomingPosts, {
					protectedUntilIndex: activeIndex + protectedRadius,
					maxItems: Math.max(this.posts.length, incomingPosts.length)
				});
				prefetchReelsPlaybackWindow(this.posts, activeIndex);
				this.persistFirstPage();

				return this.posts;
			}
			catch (error) {
				return this.posts;
			}
			finally {
				this.isTailReranking = false;
			}
		},
		startFeedSession: function(refreshReason = 'refresh') {
			this.feedSessionId = createFeedSessionId();
			this.refreshReason = refreshReason;
		},
		applyFeedMeta: function(meta, replaceSessionId = true) {
			this.feedMeta = extractFeedMeta(meta, this.feedMeta);

			if(replaceSessionId && this.feedMeta.sessionId) {
				this.feedSessionId = this.feedMeta.sessionId;
			}

			return this.feedMeta;
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
