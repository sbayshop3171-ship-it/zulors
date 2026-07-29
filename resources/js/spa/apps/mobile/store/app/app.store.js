import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { resolveAppBaseUrl } from '@/kernel/services/axios/index.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

const wait = (timeout) => {
    return new Promise((resolve) => {
        setTimeout(resolve, timeout);
    });
};

const useAppStore = defineStore('mobile_app_store', {
    state: () => {
        return {
            appData: null
        };
    },
    actions: {
        bootstrapApplication: async function() {
            let state = this;
            const authStore = useAuthStore();
            const loginUrl = embedder('routes.user_auth_index');
            const retryDelays = [0, 800, 1600];

            for (const [attemptIndex, retryDelay] of retryDelays.entries()) {
                if (retryDelay) {
                    await wait(retryDelay);
                }

                try {
                    const csrfResponse = await fetch(`${resolveAppBaseUrl()}/sanctum/csrf-cookie`, {
                        method: 'GET',
                        credentials: 'include'
                    });

                    if(! csrfResponse.ok) {
                        throw Object.assign(new Error(`CSRF bootstrap failed with ${csrfResponse.status}`), {
                            response: {
                                status: csrfResponse.status
                            }
                        });
                    }

                    const response = await colibriAPI().bootstrap().getFrom('bootstrap');

                    state.appData = response.data.data;
                    authStore.setUser(state.appData?.auth?.user ?? null);
                    sessionStorage.removeItem('bootstrap-reload-attempted');

                    return true;
                } catch (error) {
                    authStore.setUser(null);
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
                        window.location.href = loginUrl;
                        return false;
                    }

                    if ((isTemporaryServerError || isRecoverableLoadError) && attemptIndex < retryDelays.length - 1) {
                        continue;
                    }

                    if (isRecoverableLoadError && ! sessionStorage.getItem('bootstrap-reload-attempted')) {
                        sessionStorage.setItem('bootstrap-reload-attempted', 'true');
                        window.location.reload();
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
