import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { resolveAppBaseUrl } from '@/kernel/services/axios/index.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

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

            try {
                await fetch(`${resolveAppBaseUrl()}/sanctum/csrf-cookie`, {
                    method: 'GET',
                    credentials: 'include'
                });

                const response = await colibriAPI().bootstrap().getFrom('bootstrap');

                state.appData = response.data.data;
                authStore.setUser(state.appData?.auth?.user ?? null);

                return true;
            } catch (error) {
                authStore.setUser(null);
                const statusCode = error?.response?.status ?? null;
                const errorMessage = String(error?.message ?? '').toLowerCase();
                const isSessionError = [401, 403, 419].includes(statusCode);
                const isRecoverableLoadError =
                    errorMessage.includes('load failed') ||
                    errorMessage.includes('failed to fetch dynamically imported module') ||
                    error?.name === 'ChunkLoadError';

                if (isRecoverableLoadError && ! sessionStorage.getItem('bootstrap-reload-attempted')) {
                    sessionStorage.setItem('bootstrap-reload-attempted', 'true');
                    window.location.reload();
                    return false;
                }

                sessionStorage.removeItem('bootstrap-reload-attempted');

                if (isSessionError) {
                    window.location.href = loginUrl;
                    return false;
                }

                throw error;
            }
        }
    }
});

export { useAppStore };
