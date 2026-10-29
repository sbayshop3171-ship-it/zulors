export const MOBILE_ROUTE_LOAD_RETRY_KEY = 'zulors.mobile.route-load-retry';
export const MOBILE_ROUTE_RETRY_QUERY_PARAM = '_mobile_route_retry';

export const isRecoverableMobileRouteLoadError = (error) => {
	const message = `${error?.name || ''} ${error?.message || error || ''}`.toLowerCase();

	return /failed to fetch dynamically imported module|error loading dynamically imported module|importing a module script failed|unable to preload (?:css|module)|loading (?:javascript )?chunk|chunkloaderror|module script failed/.test(message);
};

export const normalizeMobileRoutePath = (routePath = '/', origin = 'http://localhost') => {
	try {
		const url = new URL(routePath, origin);
		url.searchParams.delete(MOBILE_ROUTE_RETRY_QUERY_PARAM);

		return `${url.pathname}${url.search}${url.hash}` || '/';
	}
	catch(error) {
		return '/';
	}
};

export const buildMobileRouteRetryUrl = (routePath = '/', origin = 'http://localhost', timestamp = Date.now()) => {
	const url = new URL(normalizeMobileRoutePath(routePath, origin), origin);
	url.searchParams.set(MOBILE_ROUTE_RETRY_QUERY_PARAM, String(timestamp));

	return url.toString();
};

export const installMobileRouteLoadRecovery = (router, browserWindow = window) => {
	let recoveryStarted = false;

	const recoverRoute = (error, targetRoute = router.currentRoute.value) => {
		if(! isRecoverableMobileRouteLoadError(error)) {
			return false;
		}

		if(recoveryStarted) {
			return true;
		}

		recoveryStarted = true;

		const routePath = normalizeMobileRoutePath(targetRoute?.fullPath || browserWindow.location.pathname, browserWindow.location.origin);
		let alreadyRetried = true;

		try {
			alreadyRetried = browserWindow.sessionStorage.getItem(MOBILE_ROUTE_LOAD_RETRY_KEY) === routePath;
		}
		catch(storageError) {
			// If session storage is unavailable, avoid a reload loop and show the recovery screen.
		}

		if(! alreadyRetried) {
			try {
				browserWindow.sessionStorage.setItem(MOBILE_ROUTE_LOAD_RETRY_KEY, routePath);
				browserWindow.location.replace(buildMobileRouteRetryUrl(routePath, browserWindow.location.origin));

				return true;
			}
			catch(storageError) {
				// Fall through to the in-app error screen when storage or navigation is blocked.
			}
		}

		if(targetRoute?.name !== 'bootstrap_error') {
			router.replace({
				name: 'bootstrap_error',
				query: {
					reason: 'route_load',
					return_to: routePath
				}
			}).catch(() => {
				browserWindow.location.replace('/');
			});
		}

		return true;
	};

	const removeRouteErrorHandler = router.onError((error, to) => {
		recoverRoute(error, to);
	});
	const removeAfterEachHandler = router.afterEach((to, from, failure) => {
		if(! failure && to.name !== 'bootstrap_error') {
			try {
				browserWindow.sessionStorage.removeItem(MOBILE_ROUTE_LOAD_RETRY_KEY);
			}
			catch(error) {
				//
			}

			if(to.query?.[MOBILE_ROUTE_RETRY_QUERY_PARAM]) {
				const query = { ...to.query };
				delete query[MOBILE_ROUTE_RETRY_QUERY_PARAM];

				router.replace({ path: to.path, query, hash: to.hash }).catch(() => {});
			}
		}
	});
	const handleVitePreloadError = (event) => {
		event.preventDefault();
		recoverRoute(event.payload || event, router.currentRoute.value);
	};

	browserWindow.addEventListener('vite:preloadError', handleVitePreloadError);

	return () => {
		removeRouteErrorHandler?.();
		removeAfterEachHandler?.();
		browserWindow.removeEventListener('vite:preloadError', handleVitePreloadError);
	};
};
