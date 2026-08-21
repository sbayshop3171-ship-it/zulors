/*
|------------------------------------------------------------------
| Desktop Bootstrap Initialization
|------------------------------------------------------------------
| This file is part of the pre initialization of the VueJS application.
| It prepares the framework before the actual application starts.
|
| @Author: (Mansur Terla)
*/

import '@/kernel/helpers/helpers.js';
import '@/kernel/helpers/javascript/index.js';

import axios from 'axios';

import '@/kernel/helpers/embeds/index.js';
import '@D/core/global/global.js';

import { toastSuccess, toastError } from '@D/core/services/toasts/index.js';
import { installFriendlyAlertGuard } from '@/kernel/services/errors/index.js';

window.toastSuccess = toastSuccess;
window.toastError = toastError;
installFriendlyAlertGuard(toastError);

window.HIDE_AUTHOR_ATTRIBUTION = import.meta.env.VITE_HIDE_AUTHOR_ATTRIBUTION;

axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if (typeof window.syncThemeMode == 'function') {
    window.syncThemeMode();
}

if (typeof window.observeSystemThemeMode == 'function') {
    window.observeSystemThemeMode();
}
