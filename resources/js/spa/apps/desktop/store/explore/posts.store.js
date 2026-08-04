import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { prefetchTimelineMedia } from '@/kernel/services/media-prefetch/index.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';

const normalizeQuery = function(query = '') {
	return String(query || '').trim().toLowerCase().slice(0, 80);
};

const getExplorePostsCacheKey = function(query = '') {
	const authStore = useAuthStore();

	return `colibri.desktop.explore.posts.first_page.v2.${authStore.userData?.id || 'guest'}.${normalizeQuery(query) || 'default'}`;
};

const explorePostsCacheLimit = 30;

const createFeedSessionId = function() {
	return `explore-posts-${Date.now()}-${Math.random().toString(36).slice(2)}`;
};

const useExplorePostsStore = defineStore('explore_posts_store', {
	deleteAware: true,
    state: function() {
		const cachedPosts = readCache(getExplorePostsCacheKey(), []);

		prefetchTimelineMedia(cachedPosts);

		return {
			updateAttempts: 0,
			posts: cachedPosts,
			update: [],
			warmPromise: null,
			feedType: 'for_you',
			feedSessionId: createFeedSessionId(),
			refreshReason: 'initial',
			filter: {
				page: 1,
				query: ''
			}
		}
	},
	actions: {
		updateFeed: async function() {
			if(! this.posts.length) {
				await this.refreshFirstPage();

				return this.posts.length > 0;
			}

            await colibriAPI().explore().params({
                filter: {
                    onset: this.posts.at(0).id,
					query: this.filter.query,
					type: this.feedType,
					session_id: this.feedSessionId,
					refresh_reason: 'update'
                }
            }).sendTo('posts').then((response) => {
                this.update = response.data.data;
            }).catch((error) => {
                if(error.response) {
                    this.update = [];
                }
            });
        },
		applyUpdate: function() {
			const existingIds = new Set(this.posts.map((post) => post.id));

            this.update.forEach((postItem) => {
                const exists = existingIds.has(postItem.id);

                if (! exists) {
					prefetchTimelineMedia([postItem]);
                    this.posts.unshift(postItem);
					existingIds.add(postItem.id);
                }
            });

            this.update = [];
			this.persistFirstPage();
        },
		makeLoadRequest: async function (filter = this.filter) {
			return await colibriAPI().explore().with({
				filter: this.requestFilter(filter)
			}).sendTo('posts');
		},
		fetchPosts: async function() {
			await this.makeLoadRequest().then((response) => {
				this.posts = response.data.data;
				prefetchTimelineMedia(this.posts);
				this.persistFirstPage();
			});
		},
		refreshFirstPage: async function(options = {}) {
			let pageNumber = this.filter.page;

			this.startFeedSession(options.refreshReason || 'refresh');
			this.filter.page = 1;

			await this.fetchPosts();

			this.filter.page = pageNumber;
		},
		loadMorePosts: async function() {
			return await this.makeLoadRequest().then((response) => {
				let posts = response.data.data;
				const existingIds = new Set(this.posts.map((postData) => postData.id));
				const nextPosts = posts.filter((postData) => ! existingIds.has(postData.id));
				
				if (nextPosts.length) {
					this.posts = this.posts.concat(nextPosts);
					prefetchTimelineMedia(nextPosts);
					this.persistFirstPage();
					return true;
				}

				return false;
			}).catch(() => {
				return false;
			});
		},
		getLastPostId: function() {
			return this.posts.at(-1).id;
		},
		resetFilter: function() {
			this.filter = {
				page: 1,
				query: ''
			};
			this.startFeedSession('open');

			if(! this.hydrateCachedFirstPage()) {
				this.posts = [];
			}
		},
		hydrateCachedFirstPage: function(query = this.filter.query) {
			const cachedPosts = readCache(getExplorePostsCacheKey(query), []);

			if(cachedPosts.length) {
				this.posts = cachedPosts;
				prefetchTimelineMedia(cachedPosts);

				return true;
			}

			return false;
		},
		warmFirstPage: async function() {
			if(this.posts.length) {
				prefetchTimelineMedia(this.posts);
			}

			if(this.warmPromise) {
				return this.warmPromise;
			}

			this.startFeedSession('warm');

			this.warmPromise = this.makeLoadRequest({
				page: 1,
				query: ''
			}).then((response) => {
				const posts = response.data.data;

				prefetchTimelineMedia(posts);
				writeCache(getExplorePostsCacheKey(), posts.slice(0, explorePostsCacheLimit));

				if(! this.filter.query && this.filter.page === 1) {
					this.posts = posts;
				}

				return posts;
			}).finally(() => {
				this.warmPromise = null;
			});

			return this.warmPromise;
		},
		persistFirstPage: function() {
			if(this.filter.page !== 1) {
				return;
			}

			writeCache(getExplorePostsCacheKey(this.filter.query), this.posts.slice(0, explorePostsCacheLimit));
		},
		startFeedSession: function(refreshReason = 'refresh') {
			this.feedSessionId = createFeedSessionId();
			this.refreshReason = refreshReason;
		},
		requestFilter: function(filter = this.filter) {
			return {
				...filter,
				type: this.feedType,
				session_id: this.feedSessionId,
				refresh_reason: this.refreshReason
			};
		}
    }
});

export { useExplorePostsStore };
