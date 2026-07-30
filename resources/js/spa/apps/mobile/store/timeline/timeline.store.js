import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { prefetchTimelineMedia } from '@/kernel/services/media-prefetch/index.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

const isOptimisticPost = function(postData) {
    return Boolean(postData?.meta?.is_optimistic);
};

const getTimelineCacheKey = function() {
    const authStore = useAuthStore();

    return `colibri.mobile.timeline.public_feed.first_page.v2.${authStore.userData?.id || 'guest'}`;
};

const timelineCacheLimit = 30;

const useTimelineStore = defineStore('mobile_timeline_store', {
    // This is used to tell the postDeleteListener to listen to this store
    // This is used only for timeline stores, on desktop and mobile with the same logic.
    
    deleteAware: true,
    state: function() {
        const cachedPosts = readCache(getTimelineCacheKey(), []);

        prefetchTimelineMedia(cachedPosts);

		return {
			posts: cachedPosts,
            update: [],
			filter: {
				page: 1
			}
		}
	},
    actions: {
        updateFeed: async function() {
            const latestServerPost = this.posts.find((postData) => {
                return ! isOptimisticPost(postData);
            });

            if(! latestServerPost) {
                await this.refreshFirstPage();

                return false;
            }

            await colibriAPI().userTimeline().params({
                filter: {
                    onset: latestServerPost.id
                }
            }).getFrom('feed').then((response) => {
                this.update = response.data.data;
            }).catch((error) => {
                if(error.response) {
                    this.update = [];
                }
            });
        },
        applyUpdate: function() {
            // Check if post already exists before adding
            // Otherwise, add the post to the beginning of the posts array.

            this.update.forEach((postItem) => {
                const exists = this.posts.slice(0, this.update.length).some((post) => {
                    return post.id === postItem.id;
                });
                
                if (! exists) {
                    prefetchTimelineMedia([postItem]);
                    this.posts.unshift(postItem);
                }
            });

            this.update = [];
            this.persistFirstPage();
        },
        initialLoad: async function() {
            if (! this.posts.length) {
                await this.refreshFirstPage();
            }
        },
        refreshFirstPage: async function() {
            return await colibriAPI().userTimeline().params({
                filter: {
                    page: 1
                }
            }).getFrom('feed').then((response) => {
                const posts = response.data.data;

                prefetchTimelineMedia(posts);

                this.filter.page = 1;
                this.posts = this.mergeOptimisticPosts(posts);
                this.persistFirstPage();

                return response;
            }).catch((error) => {
                if(! this.posts.length) {
                    this.posts = [];
                }
            });
        },
        loadNextPage: async function() {
            this.filter.page += 1;

            return await this.load();
        },
        load: async function() {
            return await colibriAPI().userTimeline().params({
                filter: this.filter
            }).getFrom('feed');
        },
        appendPosts: function(posts) {
            prefetchTimelineMedia(posts);
            this.posts = this.posts.concat(posts);
            this.persistFirstPage();
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
        prependOptimisticPost: function(postData) {
            this.posts = this.posts.filter((item) => {
                return item.id != postData.id && item.meta?.client_id !== postData.meta?.client_id;
            });

            this.posts.unshift(postData);

            return this.posts;
        },
        replaceOptimisticPost: function(clientId, postData) {
            const postIndex = this.posts.findIndex((item) => {
                return item.meta?.client_id === clientId;
            });

            this.posts = this.posts.filter((item, index) => {
                return index === postIndex || item.id != postData.id;
            });

            prefetchTimelineMedia([postData]);

            if(postIndex !== -1) {
                this.posts.splice(postIndex, 1, postData);
            }
            else {
                this.posts.unshift(postData);
            }

            this.persistFirstPage();
        },
        removeOptimisticPost: function(clientId) {
            this.posts = this.posts.filter((item) => {
                return item.meta?.client_id !== clientId;
            });
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
        updatePoll: function(pollData) {
            const postItem = this.posts.find((item) => {
                return item.id == pollData.post_id;
            });

            if(postItem) {
                postItem.relations.poll = pollData;
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
        setPostMedia: function(mediaData) {
            const postItem = this.posts.find((item) => {
                return item.id == mediaData.mediaable_id;
            });

            if(postItem?.relations?.media?.length) {
                const mediaIndex = postItem.relations.media.findIndex((item) => {
                    return item.id == mediaData.id;
                });

                if(mediaIndex !== -1) {
                    postItem.relations.media.splice(mediaIndex, 1, mediaData);
                    this.persistFirstPage();
                }
            }
        },
        mergeOptimisticPosts: function(posts) {
            const missingOptimisticPosts = this.posts.filter((optimisticPost) => {
                return isOptimisticPost(optimisticPost) && ! posts.some((postData) => {
                    return postData.id == optimisticPost.id || postData.hash_id === optimisticPost.hash_id;
                });
            });

            return missingOptimisticPosts.concat(posts);
        },
        persistFirstPage: function() {
            writeCache(getTimelineCacheKey(), this.posts.filter((postData) => {
                return ! isOptimisticPost(postData);
            }).slice(0, timelineCacheLimit));
        }
    }
});

export { useTimelineStore };
