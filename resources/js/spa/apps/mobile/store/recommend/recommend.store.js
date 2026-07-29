import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

const getFollowRecommendationsCacheKey = function() {
    const authStore = useAuthStore();

    return `colibri.mobile.recommend.follow.v1.${authStore.userData?.id || 'guest'}`;
};

const useRecommendStore = defineStore('mobile_recommend_store', {
    state: function() {
		return {
            followRecommendations: readCache(getFollowRecommendationsCacheKey(), []),
		}
	},
    actions: {
		fetchFollowRecommendations: async function() {
			return await colibriAPI().recommendations().getFrom('follow').then((response) => {
                this.followRecommendations = response.data.data;
                writeCache(getFollowRecommendationsCacheKey(), this.followRecommendations);

				return this.followRecommendations;
			}).catch((error) => {
				return this.followRecommendations;
			});
		}
    }
});

export { useRecommendStore };
