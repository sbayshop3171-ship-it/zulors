import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCacheEntry } from '@/kernel/services/cache/index.js';
import { evictViewerFeedSnapshots, feedSnapshotMaxAgeMs } from '@/kernel/services/cache/feed-cache.js';

const bootstrapCacheKey = 'colibri.mobile.bootstrap.v1';
const bootstrapCacheTtl = feedSnapshotMaxAgeMs;

const useAuthStore = defineStore('mobile_auth_store', {
    state: function() {
        const cachedUser = readCacheEntry(bootstrapCacheKey, bootstrapCacheTtl)?.data?.auth?.user ?? null;

		return {
            user: cachedUser,
		}
	},
    getters: {
        authCheck: function() {
            return this.user !== null;
        },
        userData: function(state) {
            return this.user;
        }
    },
    actions: {
        setUser: function(userData) {
           this.user = userData;
        },
        setProperty: function(key, value) {
            this.user[key] = value;
        },
        logoutUser: async function() {
            const userId = this.user?.id ?? null;

            try {
                return await colibriAPI().userAuth().sendTo('logout');
            }
            finally {
                if(userId) {
                    evictViewerFeedSnapshots(`user:${userId}`).catch(() => {});
                }

                this.user = null;
            }
        }
    }
});

export { useAuthStore };
