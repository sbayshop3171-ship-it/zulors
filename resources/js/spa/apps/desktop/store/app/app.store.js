import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { resolveAppBaseUrl } from '@/kernel/services/axios/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';

const bootstrapCacheKey = 'colibri.desktop.bootstrap.v1';
const bootstrapCacheTtl = 1000 * 60 * 15;

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

const useAppStore = defineStore('app', {
    state: () => {
        return {
            appData: readCache(bootstrapCacheKey, null, bootstrapCacheTtl)
        };
    },
    actions: {
        hydrateCachedBootstrap: function() {
            const authStore = useAuthStore();
            const userData = this.appData?.auth?.user ?? null;

            if(userData) {
                authStore.setUser(userData);

                return true;
            }

            return false;
        },
        applyBootstrapData: function(bootstrapData) {
            const authStore = useAuthStore();

            this.appData = bootstrapData;
            authStore.setUser(bootstrapData?.auth?.user ?? null);
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
                    await fetch(`${resolveAppBaseUrl()}/sanctum/csrf-cookie`, {
                        method: 'GET',
                        credentials: 'include'
                    }).catch(() => null);

                    const response = await colibriAPI().bootstrap().getFrom('bootstrap');
                    const bootstrapData = response?.data?.data;

                    if(! bootstrapData) {
                        throw Object.assign(new Error('Bootstrap returned an invalid payload'), {
                            response: {
                                status: response?.status ?? 500
                            }
                        });
                    }

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
