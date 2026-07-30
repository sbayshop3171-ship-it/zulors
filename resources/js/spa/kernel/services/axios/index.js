import Axios from 'axios';


const configuredBaseURL = import.meta.env.VITE_API_BASE_URL;
const appApiPrefix = import.meta.env.VITE_APP_API_PREFIX;

const normalizeBaseURL = (url) => {
    return String(url || '').replace(/\/+$/, '');
};

const normalizeApiPrefix = (prefix) => {
    return String(prefix || 'api').replace(/^\/+|\/+$/g, '');
};

const isLoopbackHost = (hostname) => {
    return ['127.0.0.1', 'localhost', '::1'].includes(hostname);
};

const resolveAppBaseUrl = () => {
    const currentOrigin = normalizeBaseURL(window.location.origin);
    const runtimeBaseURL = normalizeBaseURL(configuredBaseURL);

    if (!runtimeBaseURL) {
        return currentOrigin;
    }

    try {
        const configuredUrl = new URL(runtimeBaseURL, currentOrigin);
        const currentUrl = new URL(currentOrigin);

        // A local build can accidentally carry a loopback VITE_API_BASE_URL into
        // production. Always keep a real-domain page on its current origin.
        if (
            isLoopbackHost(configuredUrl.hostname) &&
            !isLoopbackHost(currentUrl.hostname)
        ) {
            return currentOrigin;
        }

        if (
            isLoopbackHost(configuredUrl.hostname) &&
            isLoopbackHost(currentUrl.hostname) &&
            configuredUrl.origin !== currentUrl.origin
        ) {
            return currentOrigin;
        }

        return normalizeBaseURL(configuredUrl.toString());
    } catch (error) {
        return currentOrigin;
    }
};

const resolveApiBaseUrl = () => {
    return `${resolveAppBaseUrl()}/${normalizeApiPrefix(appApiPrefix)}`;
};

const refreshCsrfCookie = (() => {
    let pendingRefresh = null;

    return () => {
        if(! pendingRefresh) {
            pendingRefresh = fetch(`${resolveAppBaseUrl()}/sanctum/csrf-cookie`, {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).finally(() => {
                pendingRefresh = null;
            });
        }

        return pendingRefresh;
    };
})();

const normalizeExpiredSessionError = (error) => {
    if(error?.response?.status === 401 && error.response.data) {
        error.response.data.message = 'Your session expired. Please refresh the page and sign in again.';
    }

    return error;
};

const AxiosAuthHeaders = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
};

if(window.ColibriBRD) {
    AxiosAuthHeaders['X-Socket-ID'] = window.ColibriBRD.connector.pusher.connection.socket_id;
}

// Create an Axios instance
const AxiosAuth = Axios.create({
    baseURL: `${resolveApiBaseUrl()}/`,
    headers: AxiosAuthHeaders
});

AxiosAuth.defaults.withCredentials = true;
AxiosAuth.defaults.withXSRFToken = true;

AxiosAuth.interceptors.response.use((response) => {
    return response;
}, async (error) => {
    const status = Number(error?.response?.status || 0);
    const requestConfig = error?.config;

    if(! [401, 419].includes(status) || ! requestConfig || requestConfig.__zulorsAuthRetried) {
        return Promise.reject(normalizeExpiredSessionError(error));
    }

    requestConfig.__zulorsAuthRetried = true;

    try {
        await refreshCsrfCookie();

        return AxiosAuth.request(requestConfig);
    }
    catch (refreshError) {
        return Promise.reject(normalizeExpiredSessionError(error));
    }
});

export { AxiosAuth, Axios, resolveAppBaseUrl, resolveApiBaseUrl };
