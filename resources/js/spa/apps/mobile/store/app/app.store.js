import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCacheEntry, writeCache } from '@/kernel/services/cache/index.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';
import { useTimelineStore } from '@M/store/timeline/timeline.store.js';

const bootstrapCacheKey = 'colibri.mobile.bootstrap.v1';
const bootstrapCacheTtl = 1000 * 60 * 15;
const bootstrapHomeFeedTtl = 1000 * 60 * 5;
const nativeGoogleAuthSuccessKey = 'zulors.native.google.auth.success.at';

const wait = (timeout) => {
    return new Promise((resolve) => {
        setTimeout(resolve, timeout);
    });
};

const refreshApplication = () => {
    const refreshUrl = new URL(window.location.href);
    refreshUrl.searchParams.set('bootstrap_refresh', Date.now().toString());

    window.location.replace(refreshUrl.toString());
};

const clearNativeGoogleAuthSuccessFlag = () => {
    try {
        window.sessionStorage.removeItem(nativeGoogleAuthSuccessKey);
    }
    catch (error) {
        //
    }
};

const takeBootBootstrapRequest = async () => {
    if(typeof window === 'undefined' || ! window.__zulorsBoot?.bootstrapRequest) {
        return null;
    }

    const pendingRequest = window.__zulorsBoot.bootstrapRequest;

    try {
        return await pendingRequest;
    }
    finally {
        if(window.__zulorsBoot?.bootstrapRequest === pendingRequest) {
            window.__zulorsBoot.bootstrapRequest = null;
        }
    }
};

const useAppStore = defineStore('mobile_app_store', {
    state: () => {
        const cachedEntry = readCacheEntry(bootstrapCacheKey, bootstrapCacheTtl);

        return {
            appData: cachedEntry?.data ?? null,
            appDataCachedAt: cachedEntry?.timestamp ?? 0,
            lastBootstrapMeta: null
        };
    },
    actions: {
        hydrateCachedBootstrap: function() {
            const authStore = useAuthStore();
            const userData = this.appData?.auth?.user ?? null;
            const hasFreshHomeFeed = this.appDataCachedAt && ((Date.now() - this.appDataCachedAt) <= bootstrapHomeFeedTtl);

            if(userData) {
                authStore.setUser(userData);

                if(hasFreshHomeFeed) {
                    const timelineStore = useTimelineStore();

                    if(! timelineStore.posts.length) {
                        timelineStore.hydrateBootFeed(this.appData?.home_feed ?? null);
                    }
                }

                return true;
            }

            return false;
        },
        applyBootstrapData: function(bootstrapData) {
            const authStore = useAuthStore();

            this.appData = bootstrapData;
            this.appDataCachedAt = Date.now();
            authStore.setUser(bootstrapData?.auth?.user ?? null);

            if (bootstrapData?.auth?.user) {
                clearNativeGoogleAuthSuccessFlag();
            }

            const timelineStore = useTimelineStore();

            if(! timelineStore.posts.length) {
                timelineStore.hydrateBootFeed(bootstrapData?.home_feed ?? null);
            }

            writeCache(bootstrapCacheKey, bootstrapData);
        },
        forgetBootstrapCache: function() {
            try {
                window.localStorage.removeItem(bootstrapCacheKey);
            }
            catch (error) {
                //
            }
        },
        bootstrapApplication: async function() {
            const authStore = useAuthStore();
            const loginUrl = embedder('routes.user_auth_index');
            const retryDelays = [0, 800, 1600];

            for (const [attemptIndex, retryDelay] of retryDelays.entries()) {
                if (retryDelay) {
                    await wait(retryDelay);
                }

                try {
                    const prefetchedResponse = attemptIndex === 0
                        ? await takeBootBootstrapRequest()
                        : null;
                    const response = prefetchedResponse ?? await colibriAPI().bootstrap().getFrom('bootstrap');
                    const bootstrapData = response?.data?.data;

                    if(! bootstrapData) {
                        throw Object.assign(new Error('Bootstrap returned an invalid payload'), {
                            response: {
                                status: response?.status ?? 500
                            }
                        });
                    }

                    this.lastBootstrapMeta = {
                        serverTiming: response?.headers?.['server-timing'] ?? null,
                        cacheHeader: response?.headers?.['x-zulors-home-feed-cache'] ?? null
                    };
                    this.applyBootstrapData(bootstrapData);
                    sessionStorage.removeItem('bootstrap-reload-attempted');
                    sessionStorage.removeItem('bootstrap-hard-reload-attempted');

                    return true;
                } catch (error) {
                    const statusCode = error?.response?.status ?? null;
                    const errorMessage = String(error?.message ?? '').toLowerCase();
                    const isSessionError = [401, 403, 419].includes(statusCode);
                    const isTemporaryServerError = [408, 429, 500, 502, 503, 504].includes(statusCode);
                    const isRecoverableLoadError =
                        errorMessage.includes('load failed') ||
                        errorMessage.includes('failed to fetch dynamically imported module') ||
                        errorMessage.includes('network error') ||
                        error?.name === 'ChunkLoadError';

                    if (isSessionError) {
                        authStore.setUser(null);
                        this.forgetBootstrapCache();
                        window.location.href = loginUrl;
                        return false;
                    }

                    if ((isTemporaryServerError || isRecoverableLoadError) && attemptIndex < retryDelays.length - 1) {
                        continue;
                    }

                    if (isTemporaryServerError && this.appData?.auth?.user) {
                        sessionStorage.removeItem('bootstrap-reload-attempted');
                        sessionStorage.removeItem('bootstrap-hard-reload-attempted');

                        return true;
                    }

                    if (isRecoverableLoadError && ! sessionStorage.getItem('bootstrap-reload-attempted')) {
                        sessionStorage.setItem('bootstrap-reload-attempted', 'true');
                        window.location.reload();
                        return false;
                    }

                    if (isRecoverableLoadError && ! sessionStorage.getItem('bootstrap-hard-reload-attempted')) {
                        sessionStorage.setItem('bootstrap-hard-reload-attempted', 'true');
                        refreshApplication();
                        return false;
                    }

                    sessionStorage.removeItem('bootstrap-reload-attempted');

                    throw error;
                }
            }
        }
    }
});

export { useAppStore };
