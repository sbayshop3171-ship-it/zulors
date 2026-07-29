import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';

const getFollowRecommendationsCacheKey = function() {
    const authStore = useAuthStore();

    return `colibri.desktop.recommend.follow.v1.${authStore.userData?.id || 'guest'}`;
};

const useRecommendStore = defineStore('recommend_store', {
	state: function() {
		return {
            lastUpdate: null,
			followRecommendations: readCache(getFollowRecommendationsCacheKey(), []),
		}
	},
    getters: {
    },
    actions: {
		fetchFollowRecommendations: async function() {
			const state = this;

			await colibriAPI().recommendations().getFrom('follow').then((response) => {
				state.followRecommendations = response.data.data;
                writeCache(getFollowRecommendationsCacheKey(), state.followRecommendations);
			});
		}
    }
});

export { useRecommendStore };
