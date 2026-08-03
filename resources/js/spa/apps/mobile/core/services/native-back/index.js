const rootRouteNames = new Set([
    'home_index',
    'explore_posts',
    'messenger_inbox'
]);

const routeHistory = [];
const backHandlers = [];

let isInstalled = false;
let isProgrammaticBack = false;

function isRootRoute(route) {
    return rootRouteNames.has(route.name);
}

function normalizeRoute(route) {
    return {
        name: route.name,
        fullPath: route.fullPath
    };
}

function pushRoute(route) {
    const normalizedRoute = normalizeRoute(route);
    const lastRoute = routeHistory[routeHistory.length - 1];

    if(lastRoute?.fullPath === normalizedRoute.fullPath) {
        return;
    }

    routeHistory.push(normalizedRoute);

    if(routeHistory.length > 40) {
        routeHistory.shift();
    }
}

function resolveFallbackRoute(route) {
    if(String(route.name || '').startsWith('messenger_')) {
        return { name: 'messenger_inbox' };
    }

    if(String(route.name || '').startsWith('explore_')) {
        return { name: 'explore_posts' };
    }

    if(String(route.name || '').startsWith('settings_')) {
        return { name: 'settings_navigator' };
    }

    return { name: 'home_index' };
}

function closeTopHandler() {
    for(let index = backHandlers.length - 1; index >= 0; index--) {
        const handler = backHandlers[index];

        if(handler?.()) {
            return true;
        }
    }

    return false;
}

function navigateBack(router) {
    if(routeHistory.length > 1) {
        isProgrammaticBack = true;
        routeHistory.pop();
        router.back();

        return true;
    }

    const currentRoute = router.currentRoute.value;

    if(! isRootRoute(currentRoute)) {
        router.push(resolveFallbackRoute(currentRoute));

        return true;
    }

    if(currentRoute.name !== 'home_index') {
        router.push({ name: 'home_index' });

        return true;
    }

    return false;
}

function handleNativeBack(router) {
    if(closeTopHandler()) {
        return true;
    }

    return navigateBack(router);
}

export function registerNativeBackHandler(handler) {
    if(typeof handler !== 'function') {
        return () => {};
    }

    backHandlers.push(handler);

    return () => {
        const index = backHandlers.indexOf(handler);

        if(index !== -1) {
            backHandlers.splice(index, 1);
        }
    };
}

export function installNativeBackBridge(router) {
    if(isInstalled) {
        return;
    }

    isInstalled = true;

    router.afterEach((to, from, failure) => {
        if(failure) {
            return;
        }

        if(isProgrammaticBack) {
            isProgrammaticBack = false;
            return;
        }

        pushRoute(to);
    });

    window.ZulorsNativeBack = {
        handle: () => {
            return handleNativeBack(router);
        }
    };
}
