import { defineStore } from 'pinia';
import { setPublicationAccount } from '@/kernel/services/media/publications/index.js';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCacheEntry } from '@/kernel/services/cache/index.js';
import { evictViewerFeedSnapshots, feedSnapshotMaxAgeMs } from '@/kernel/services/cache/feed-cache.js';

const bootstrapCacheKey = 'colibri.desktop.bootstrap.v1';
const bootstrapCacheTtl = feedSnapshotMaxAgeMs;

const useAuthStore = defineStore('auth_store', {
    state: function() {
        const cachedUser = readCacheEntry(bootstrapCacheKey, bootstrapCacheTtl)?.data?.auth?.user ?? null;
        queueMicrotask(() => setPublicationAccount(useAuthStore().user?.id));

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
           setPublicationAccount(userData?.id);
        },
        setProperty: function(key, value) {
            this.user[key] = value;
        },
        logoutUser: async function() {
            const userId = this.user?.id ?? null;
            setPublicationAccount(null);

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
