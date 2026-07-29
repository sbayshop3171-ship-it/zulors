import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

const getStoriesFeedCacheKey = function() {
    const authStore = useAuthStore();

    return `colibri.mobile.stories.feed.v1.${authStore.userData?.id || 'guest'}`;
};

const useStoriesStore = defineStore('mobile_stories_store', {
    state: function() {
		return {
			storiesFeed: readCache(getStoriesFeedCacheKey(), []),
            stories: []
		}
	},
    actions: {
        fetchStoriesFeed: async function() {
            await colibriAPI().stories().getFrom('feed').then((response) => {
                this.storiesFeed = response.data.data;
                writeCache(getStoriesFeedCacheKey(), this.storiesFeed);
            }).catch((error) => {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
        deleteStory: async function(storyUUID, frameId) {
            await colibriAPI().stories().with({
                frame_id: frameId
            }).delete('delete').then((response) => {
                const storyData = this.stories.find((storyItem) => {
                    return storyItem.story_uuid === storyUUID;
                });

                const frameIndex = storyData.relations.frames.findIndex((frameItem) => {
                    return frameItem.id === frameId;
                });

                storyData.relations.frames.splice(frameIndex, 1);

                this.stories = this.stories.filter((storyItem) => {
                    return storyItem.relations.frames.length > 0;
                });

                // Remove deleted story from storiesFeed if present
                this.storiesFeed = this.storiesFeed.filter((feedItem) => {
                    return this.stories.some((storyItem) => {
                        return storyItem.story_uuid === feedItem.story_uuid;
                    });
                });
            }).catch((error) => {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
        fetchStory: async function(storyUUID) {
            await colibriAPI().stories().getFrom(`stories/${storyUUID}`).then((response) => {
                this.stories = response.data.data;
            }).catch((error) => {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
        fetchAndReturnStoryViews: async function(frameId) {
            return await colibriAPI().stories().getFrom(`views/${frameId}`).then((response) => {
                return response.data.data;
            }).catch((error) => {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
        recordStoryView: async function(storyUUID, frameId) {
            const frameData = this.stories.find((storyItem) => {
                return storyItem.story_uuid === storyUUID;
            }).relations.frames.find((frameItem) => {
                return frameItem.id === frameId;
            });

            frameData.activity.is_seen = true;
            
            await colibriAPI().stories().with({
                frame_id: frameId
            }).sendTo('views/record').catch((error) => {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        }
    }
});

export { useStoriesStore };
