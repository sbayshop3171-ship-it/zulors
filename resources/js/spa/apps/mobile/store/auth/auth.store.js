import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCacheEntry } from '@/kernel/services/cache/index.js';

const bootstrapCacheKey = 'colibri.mobile.bootstrap.v1';
const bootstrapCacheTtl = 1000 * 60 * 15;

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
            return await colibriAPI().userAuth().sendTo('logout');
        }
    }
});

export { useAuthStore };