const friendlyRateLimitMessage = 'Please wait a moment and try again.';

const getErrorStatus = (error) => {
    return Number(error?.response?.status || 0);
};

const isRateLimitMessage = (message) => {
    return /too many attempts|rate limit|please wait a moment/i.test(String(message || ''));
};

const normalizeRateLimitError = (error) => {
    if(getErrorStatus(error) !== 429 || ! error?.response) {
        return error;
    }

    const responseData = error.response.data && typeof error.response.data === 'object' ? error.response.data : {};
    const retryAfter = Number(error.response.headers?.['retry-after'] || responseData.retry_after || 0);

    error.response.data = {
        ...responseData,
        code: responseData.code || 'rate_limited',
        message: friendlyRateLimitMessage,
        retry_after: retryAfter
    };

    error.__zulorsRateLimited = true;

    return error;
};

const installFriendlyAlertGuard = (toastError = null) => {
    if(typeof window === 'undefined' || typeof window.alert !== 'function' || window.__zulorsFriendlyAlertGuardInstalled) {
        return;
    }

    const nativeAlert = window.alert.bind(window);

    window.__zulorsFriendlyAlertGuardInstalled = true;
    window.alert = (message) => {
        if(isRateLimitMessage(message)) {
            if(typeof toastError === 'function') {
                toastError(friendlyRateLimitMessage, 3500);
            }
            else {
                nativeAlert(friendlyRateLimitMessage);
            }

            return;
        }

        nativeAlert(message);
    };
};

export { friendlyRateLimitMessage, normalizeRateLimitError, installFriendlyAlertGuard };
