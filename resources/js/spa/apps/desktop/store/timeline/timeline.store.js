import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { prefetchTimelineMedia } from '@/kernel/services/media-prefetch/index.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';

const getTimelineCacheKey = function() {
    const authStore = useAuthStore();

    return `colibri.desktop.timeline.public_feed.first_page.v2.${authStore.userData?.id || 'guest'}`;
};

const getPublicFeedCacheKey = function() {
    return 'colibri.desktop.timeline.public_feed.first_page.shared.v1';
};

const timelineCacheLimit = 30;

const createFeedSessionId = function() {
    return `feed-${Date.now()}-${Math.random().toString(36).slice(2)}`;
};

const wait = function(timeout) {
    return new Promise((resolve) => {
        setTimeout(resolve, timeout);
    });
};

const useTimelineStore = defineStore('timeline_store', {
    // This is used to tell the postDeleteListener to listen to this store
    // This is used only for timeline stores, on desktop and mobile with the same logic.
    deleteAware: true,
    state: function() {
        const sharedCachedPosts = readCache(getPublicFeedCacheKey(), [], (1000 * 60 * 20));
        const cachedPosts = readCache(getTimelineCacheKey(), sharedCachedPosts);

        prefetchTimelineMedia(cachedPosts);

		return {
			posts: cachedPosts,
            update: [],
            warmPromise: null,
            feedType: 'for_you',
            feedSessionId: createFeedSessionId(),
            refreshReason: 'initial',
			filter: {
				page: 1,
                onset: null
			}
		}
	},
    actions: {
        updateFeed: async function() {
            let state = this;

            if(! state.posts.length) {
                await state.refreshFirstPage();

                return false;
            }

            await colibriAPI().userTimeline().params({
                filter: {
                    onset: state.posts.at(0).id,
                    type: state.feedType,
                    session_id: state.feedSessionId,
                    refresh_reason: 'update'
                }
            }).getFrom('feed').then((response) => {
                state.update = response.data.data;
            }).catch((error) => {
                if(error.response) {
                    state.update = [];
                }
            });
        },
        applyUpdate: function() {
            // Check if post already exists before adding
            // Otherwise, add the post to the beginning of the posts array.

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
        initialLoad: async function() {
            if (! this.posts.length) {
                if(this.warmPromise) {
                    await this.warmPromise;
                }

                if(this.posts.length) {
                    return;
                }

                await this.refreshFirstPage({
                    refreshReason: 'initial',
                    attempts: 2
                });
            }
        },
        refreshFirstPage: async function(options = {}) {
            this.startFeedSession(options.refreshReason || 'refresh');

            return await this.fetchFirstPageWithRetry(options.attempts || 1);
        },
        warmFirstPage: function() {
            if(this.posts.length) {
                prefetchTimelineMedia(this.posts);

                return Promise.resolve(this.posts);
            }

            if(this.warmPromise) {
                return this.warmPromise;
            }

            this.startFeedSession('warm');

            this.warmPromise = this.fetchFirstPageWithRetry(2)
                .then((response) => {
                    return response?.data?.data ?? this.posts;
                })
                .catch(() => {
                    return this.posts;
                })
                .finally(() => {
                    this.warmPromise = null;
                });

            return this.warmPromise;
        },
        fetchFirstPageWithRetry: async function(attempts = 1) {
            const requestAttempts = Math.max(1, attempts);
            let lastError = null;

            for(let attempt = 1; attempt <= requestAttempts; attempt++) {
                try {
                    return await this.fetchFirstPage();
                } catch (error) {
                    lastError = error;

                    if(attempt < requestAttempts) {
                        await wait(350 * attempt);
                    }
                }
            }

            if(! this.posts.length) {
                this.posts = [];
            }

            throw lastError;
        },
        fetchFirstPage: async function() {
            return await colibriAPI().userTimeline().params({
                filter: {
                    page: 1,
                    onset: null,
                    type: this.feedType,
                    session_id: this.feedSessionId,
                    refresh_reason: this.refreshReason,
                    fast_start: true
                }
            }).getFrom('feed').then((response) => {
                const posts = response.data.data;

                prefetchTimelineMedia(posts);

                this.filter.page = 1;
                this.filter.onset = null;
                this.posts = posts;
                this.persistFirstPage();

                return response;
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
        prependPost: function(postData) {
            this.posts = this.posts.filter((item) => {
                return item.id != postData.id;
            });

            prefetchTimelineMedia([postData]);
            this.posts.unshift(postData);
            this.persistFirstPage();

            return this.posts;
        },
        removePost: function(postId) {
            let postIndex = this.posts.findIndex((item) => {
                return item.id == postId;
            });

            if(postIndex !== -1) {
                this.posts.splice(postIndex, 1);
                this.persistFirstPage();
            }
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
        setPostMedia: function(mediaData) {
            const postItem = this.posts.find((item) => {
                return item.id == mediaData.mediaable_id;
            });

            if(postItem?.relations?.media?.length) {
                let mediaIndex = postItem.relations.media.findIndex((item) => {
                    return item.id == mediaData.id;
                });

                if(mediaIndex !== -1) {
                    postItem.relations.media.splice(mediaIndex, 1, mediaData);
                    this.persistFirstPage();
                }
            }
        },
        setPostPollData: function(pollData) {
            const postItem = this.posts.find((item) => {
                return item.id == pollData.post_id;
            });

            if(postItem?.relations?.poll) {
                postItem.relations.poll = pollData;
                this.persistFirstPage();
            }
        },
        persistFirstPage: function() {
            const posts = this.posts.slice(0, timelineCacheLimit);

            writeCache(getTimelineCacheKey(), posts);
            writeCache(getPublicFeedCacheKey(), posts);
        },
        startFeedSession: function(refreshReason = 'refresh') {
            this.feedSessionId = createFeedSessionId();
            this.refreshReason = refreshReason;
        },
        requestFilter: function() {
            return {
                ...this.filter,
                type: this.feedType,
                session_id: this.feedSessionId,
                refresh_reason: this.refreshReason
            };
        }
    }
});

export { useTimelineStore };
