import { defineStore } from 'pinia';
import { setPublicationAccount } from '@/kernel/services/media/publications/index.js';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCacheEntry } from '@/kernel/services/cache/index.js';
import { evictViewerFeedSnapshots, feedSnapshotMaxAgeMs } from '@/kernel/services/cache/feed-cache.js';

const bootstrapCacheKey = 'colibri.mobile.bootstrap.v1';
const bootstrapCacheTtl = feedSnapshotMaxAgeMs;

const useAuthStore = defineStore('mobile_auth_store', {
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
            const previousUserId = this.user?.id ?? null;
            this.user = userData;
            setPublicationAccount(userData?.id);

            if(typeof window !== 'undefined' && String(previousUserId ?? '') !== String(userData?.id ?? '')) {
                window.dispatchEvent(new CustomEvent('zulors:account-scope-changed', {
                    detail: { viewerId: userData?.id ?? null }
                }));
            }
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

                this.setUser(null);
            }
        }
    }
});

export { useAuthStore };
