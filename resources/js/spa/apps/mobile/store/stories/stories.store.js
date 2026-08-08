import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

const DEFAULT_STORY_REACTION_ID = '2764-fe0f';

const getStoriesFeedCacheKey = function() {
    const authStore = useAuthStore();

    return `colibri.mobile.stories.feed.v2.${authStore.userData?.id || 'guest'}`;
};

const storyVisibleInFeed = function(storyItem) {
    return ! (storyItem?.status === 'processing' && storyItem?.progress?.stage === 'failed');
};

const frameVisibleInStory = function(frameItem) {
    const isFailed = frameItem?.progress?.stage === 'failed' || frameItem?.media?.status === 'failed';

    return ! (frameItem?.status === 'processing' && isFailed);
};

const visibleStories = function(stories) {
    return stories.map((storyItem) => {
        return {
            ...storyItem,
            relations: {
                ...storyItem.relations,
                frames: (storyItem.relations?.frames || []).filter(frameVisibleInStory)
            }
        };
    }).filter((storyItem) => {
        return storyItem.relations.frames.length > 0;
    });
};

const useStoriesStore = defineStore('mobile_stories_store', {
    state: function() {
		return {
			storiesFeed: readCache(getStoriesFeedCacheKey(), []).filter(storyVisibleInFeed),
            stories: []
		}
	},
    getters: {
        hasProcessingStories: (state) => {
            return state.storiesFeed.some((storyItem) => {
                return storyItem.status === 'processing' && ! ['failed', 'ready'].includes(storyItem.progress?.stage);
            });
        }
    },
    actions: {
        applyStoryFrameReactionState: function(frameId, payload = {}) {
            this.stories.forEach((storyItem) => {
                const frameData = storyItem.relations.frames.find((frameItem) => {
                    return frameItem.id === frameId;
                });

                if(frameData) {
                    if(Object.prototype.hasOwnProperty.call(payload, 'likes_count')) {
                        frameData.likes_count = payload.likes_count;
                    }

                    if(Object.prototype.hasOwnProperty.call(payload, 'reactions_count')) {
                        frameData.reactions_count = payload.reactions_count;
                    }

                    if(Object.prototype.hasOwnProperty.call(payload, 'reactions_summary')) {
                        frameData.reactions_summary = payload.reactions_summary;
                    }

                    if(Object.prototype.hasOwnProperty.call(payload, 'activity')) {
                        frameData.activity = {
                            ...frameData.activity,
                            ...payload.activity
                        };
                    }
                }
            });
        },
        applyStoryFrameLikeState: function(frameId, payload = {}) {
            this.applyStoryFrameReactionState(frameId, payload);
        },
        prependFeedItem: function(storyData) {
            if(! storyData) {
                return;
            }

            if(! storyVisibleInFeed(storyData)) {
                this.removeFeedItem(storyData.story_uuid);

                return;
            }

            const storyIndex = this.storiesFeed.findIndex((storyItem) => {
                return storyItem.story_uuid === storyData.story_uuid;
            });

            if(storyIndex !== -1) {
                this.storiesFeed.splice(storyIndex, 1);
            }

            this.storiesFeed.unshift(storyData);
            writeCache(getStoriesFeedCacheKey(), this.storiesFeed);
        },
        removeFeedItem: function(storyUUID) {
            this.storiesFeed = this.storiesFeed.filter((storyItem) => {
                return storyItem.story_uuid !== storyUUID;
            });

            writeCache(getStoriesFeedCacheKey(), this.storiesFeed);
        },
        fetchStoriesFeed: async function() {
            await colibriAPI().stories().getFrom('feed').then((response) => {
                this.storiesFeed = response.data.data.filter(storyVisibleInFeed);
                writeCache(getStoriesFeedCacheKey(), this.storiesFeed);

                return this.storiesFeed;
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

                if(storyData) {
                    const frameIndex = storyData.relations.frames.findIndex((frameItem) => {
                        return frameItem.id === frameId;
                    });

                    if(frameIndex !== -1) {
                        storyData.relations.frames.splice(frameIndex, 1);
                    }

                    this.stories = this.stories.filter((storyItem) => {
                        return storyItem.relations.frames.length > 0;
                    });
                }

                if(response.data.data) {
                    this.prependFeedItem(response.data.data);
                }
                else {
                    this.removeFeedItem(storyUUID);
                }
            }).catch((error) => {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
        fetchStory: async function(storyUUID) {
            await colibriAPI().stories().getFrom(`stories/${storyUUID}`).then((response) => {
                this.stories = visibleStories(response.data.data);
            }).catch((error) => {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
        fetchAndReturnStoryViews: async function(frameId) {
            return await colibriAPI().stories().getFrom(`views/${frameId}`).then((response) => {
                this.applyStoryFrameReactionState(frameId, {
                    likes_count: response.data.meta?.likes_count,
                    reactions_count: response.data.meta?.reactions_count,
                    reactions_summary: response.data.meta?.reactions_summary
                });

                return response.data;
            }).catch((error) => {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
        toggleStoryReaction: async function(frameId, unifiedId = DEFAULT_STORY_REACTION_ID) {
            return await colibriAPI().stories().with({
                frame_id: frameId,
                unified_id: unifiedId
            }).sendTo('reactions/toggle').then((response) => {
                this.applyStoryFrameReactionState(frameId, response.data.data);

                return response.data.data;
            }).catch((error) => {
                if(error.response) {
                    throw new Error(error.response.data.message);
                }
            });
        },
        toggleStoryLike: async function(frameId) {
            return this.toggleStoryReaction(frameId, DEFAULT_STORY_REACTION_ID);
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
