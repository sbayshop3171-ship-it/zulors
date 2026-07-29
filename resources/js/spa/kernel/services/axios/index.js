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

export { AxiosAuth, Axios, resolveAppBaseUrl, resolveApiBaseUrl };
