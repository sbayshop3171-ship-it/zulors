import test from 'node:test';
import assert from 'node:assert/strict';

import {
	MOBILE_ROUTE_LOAD_RETRY_KEY,
	MOBILE_ROUTE_RETRY_QUERY_PARAM,
	buildMobileRouteRetryUrl,
	installMobileRouteLoadRecovery,
	isRecoverableMobileRouteLoadError,
	normalizeMobileRoutePath,
} from '../../resources/js/spa/apps/mobile/core/services/route-load-recovery/index.js';

function createBrowserWindow(entries = new Map()) {
	const listeners = new Map();
	const location = {
		origin: 'https://zulors.test',
		pathname: '/messenger/c/chat-42',
		replaced: [],
		replace(url) {
			this.replaced.push(url);
		},
	};

	return {
		location,
		sessionStorage: {
			getItem(key) { return entries.get(key) ?? null; },
			setItem(key, value) { entries.set(key, value); },
			removeItem(key) { entries.delete(key); },
		},
		addEventListener(name, handler) { listeners.set(name, handler); },
		removeEventListener(name) { listeners.delete(name); },
		dispatch(name, event) { listeners.get(name)?.(event); },
	};
}

function createRouter() {
	const handlers = {};
	const router = {
		currentRoute: { value: { name: 'messenger_chat', fullPath: '/messenger/c/chat-42' } },
		replacements: [],
		onError(handler) { handlers.error = handler; return () => { delete handlers.error; }; },
		afterEach(handler) { handlers.afterEach = handler; return () => { delete handlers.afterEach; }; },
		replace(target) { this.replacements.push(target); return Promise.resolve(); },
	};

	return {
		router,
		error: (...args) => handlers.error?.(...args),
		afterEach: (...args) => handlers.afterEach?.(...args),
	};
}

test('mobile route recovery identifies lazy chunk and preload failures only', () => {
	assert.equal(isRecoverableMobileRouteLoadError(new TypeError('Failed to fetch dynamically imported module')), true);
	assert.equal(isRecoverableMobileRouteLoadError({ name: 'ChunkLoadError' }), true);
	assert.equal(isRecoverableMobileRouteLoadError(new Error('Unable to preload CSS for /assets/chat.css')), true);
	assert.equal(isRecoverableMobileRouteLoadError(new Error('Request failed with status code 500')), false);
});

test('mobile route retry URL keeps the deep link and removes stale retry markers', () => {
	assert.equal(normalizeMobileRoutePath('/messenger/c/chat-42?tab=media&_mobile_route_retry=old', 'https://zulors.test'), '/messenger/c/chat-42?tab=media');
	const retryUrl = new URL(buildMobileRouteRetryUrl('/messenger/c/chat-42?tab=media&_mobile_route_retry=old', 'https://zulors.test', 123));
	assert.equal(retryUrl.pathname, '/messenger/c/chat-42');
	assert.equal(retryUrl.searchParams.get('tab'), 'media');
	assert.equal(retryUrl.searchParams.get(MOBILE_ROUTE_RETRY_QUERY_PARAM), '123');
});

test('mobile route recovery reloads a failed deep link once, then exposes a retry screen', () => {
	const entries = new Map();
	const firstWindow = createBrowserWindow(entries);
	const first = createRouter();
	installMobileRouteLoadRecovery(first.router, firstWindow);
	const failedRoute = { name: 'messenger_chat', fullPath: '/messenger/c/chat-42?source=notification' };

	first.error(new TypeError('Failed to fetch dynamically imported module'), failedRoute);
	assert.equal(firstWindow.location.replaced.length, 1);
	assert.equal(new URL(firstWindow.location.replaced[0]).pathname, '/messenger/c/chat-42');
	assert.equal(entries.get(MOBILE_ROUTE_LOAD_RETRY_KEY), failedRoute.fullPath);

	const secondWindow = createBrowserWindow(entries);
	const second = createRouter();
	installMobileRouteLoadRecovery(second.router, secondWindow);
	second.error(new TypeError('Failed to fetch dynamically imported module'), failedRoute);
	assert.equal(secondWindow.location.replaced.length, 0);
	assert.deepEqual(second.router.replacements, [{
		name: 'bootstrap_error',
		query: { reason: 'route_load', return_to: failedRoute.fullPath },
	}]);

	second.afterEach({ name: 'messenger_chat' }, {}, undefined);
	assert.equal(entries.has(MOBILE_ROUTE_LOAD_RETRY_KEY), false);
});

test('Vite preload errors use the same single-retry recovery path', () => {
	const browserWindow = createBrowserWindow();
	const { router } = createRouter();
	installMobileRouteLoadRecovery(router, browserWindow);
	let prevented = false;

	browserWindow.dispatch('vite:preloadError', {
		payload: new TypeError('Failed to fetch dynamically imported module'),
		preventDefault() { prevented = true; },
	});

	assert.equal(prevented, true);
	assert.equal(browserWindow.location.replaced.length, 1);
});

test('successful deep-link load removes the temporary retry query from the address', () => {
	const browserWindow = createBrowserWindow();
	const { router, afterEach } = createRouter();
	installMobileRouteLoadRecovery(router, browserWindow);

	afterEach({
		name: 'messenger_chat',
		path: '/messenger/c/chat-42',
		hash: '',
		query: { source: 'notification', [MOBILE_ROUTE_RETRY_QUERY_PARAM]: '123' },
	}, {}, undefined);

	assert.deepEqual(router.replacements, [{
		path: '/messenger/c/chat-42',
		query: { source: 'notification' },
		hash: '',
	}]);
});
