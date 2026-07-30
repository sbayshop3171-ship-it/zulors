import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { prefetchTimelineMedia } from '@/kernel/services/media-prefetch/index.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';

const getTimelineCacheKey = function() {
    const authStore = useAuthStore();

    return `colibri.desktop.timeline.public_feed.first_page.v2.${authStore.userData?.id || 'guest'}`;
};

const timelineCacheLimit = 30;

const useTimelineStore = defineStore('timeline_store', {
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
                    onset: state.posts.at(0).id
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
                    page: 1,
                    onset: null
                }
            }).getFrom('feed').then((response) => {
                const posts = response.data.data;

                prefetchTimelineMedia(posts);

                this.filter.page = 1;
                this.posts = posts;
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
            writeCache(getTimelineCacheKey(), this.posts.slice(0, timelineCacheLimit));
        }
    }
});

export { useTimelineStore };
