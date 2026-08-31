import '../alpinejs/confirm.modal.js';

import '../alpinejs/components/code.js';

const businessTabCache = new Map();
let businessNavTransitionInFlight = false;

function getBusinessNavigationTarget(href) {
	if(! href) {
		return null;
	}

	const targetUrl = new URL(href, window.location.origin);

	if(targetUrl.origin !== window.location.origin || ! targetUrl.pathname.startsWith('/business')) {
		return null;
	}

	return `${targetUrl.pathname}${targetUrl.search}${targetUrl.hash}`;
}

function activateBusinessTab(tabName) {
	window.requestAnimationFrame(() => {
		const items = document.querySelectorAll('[data-business-tab]');

		for(const item of items) {
			const isActive = item.dataset.businessTab === tabName;
			item.classList.toggle('business-mobile-nav-item-active', isActive);
			item.classList.toggle('business-mobile-more-item-active', isActive);
		}
	});
}

function resolveBusinessTabName(pathname) {
	if(pathname === '/business' || pathname === '/business/') {
		return 'overview';
	}

	if(pathname.startsWith('/business/market')) {
		return 'marketplace';
	}

	if(pathname.startsWith('/business/jobs')) {
		return 'jobs';
	}

	if(pathname.startsWith('/business/wallet')) {
		return 'wallet';
	}

	if(pathname.startsWith('/business/ads')) {
		return 'campaign';
	}

	if(pathname.startsWith('/business/settings')) {
		return 'settings';
	}

	return 'overview';
}

function applyBusinessShellContent({ title, content, path }) {
	const region = document.querySelector('[data-business-refresh-region]');

	if(! region) {
		return;
	}

	region.innerHTML = content;
	document.title = title || document.title;
	activateBusinessTab(resolveBusinessTabName(path));

	if(window.Alpine?.initTree) {
		window.Alpine.initTree(document.body);
	}

	window.dispatchEvent(new CustomEvent('business:shell-updated', { detail: { path } }));
}

async function navigateBusinessShell(href) {
	const path = getBusinessNavigationTarget(href);

	if(! path || businessNavTransitionInFlight || path === window.location.pathname + window.location.search + window.location.hash) {
		return;
	}

	businessNavTransitionInFlight = true;

	try {
		if(businessTabCache.has(path)) {
			const cached = businessTabCache.get(path);
			applyBusinessShellContent({ ...cached, path });
			history.pushState({ businessShell: true }, '', path);
			window.scrollTo({ top: 0, behavior: 'auto' });
			return;
		}

		const response = await fetch(path, {
			cache: 'no-store',
			credentials: 'same-origin',
			headers: {
				'Accept': 'text/html',
				'X-Requested-With': 'XMLHttpRequest',
				'X-Business-Shell': '1'
			}
		});

		if(! response.ok) {
			window.location.href = href;
			return;
		}

		const html = await response.text();
		const parsedDocument = new DOMParser().parseFromString(html, 'text/html');
		const nextRegion = parsedDocument.querySelector('[data-business-refresh-region]');
		const nextTitle = parsedDocument.title || document.title;

		if(! nextRegion) {
			window.location.href = href;
			return;
		}

		const shellState = {
			title: nextTitle,
			content: nextRegion.innerHTML,
			path
		};

		businessTabCache.set(path, shellState);
		applyBusinessShellContent(shellState);
		history.pushState({ businessShell: true }, '', path);
		window.scrollTo({ top: 0, behavior: 'auto' });
	}
	catch(error) {
		console.warn('Business shell navigation failed.', error);
		window.location.href = href;
	}
	finally {
		businessNavTransitionInFlight = false;
	}
}

function initBusinessNativeBridge() {
	const root = document.documentElement;
	const uiBridge = window.ZulorsBusinessBridge || {};

	const syncInsets = () => {
		const safeTop = window.innerHeight > 0 ? 'max(env(safe-area-inset-top, 0px), var(--zulors-android-safe-top, 0px))' : '0px';
		const safeRight = 'max(env(safe-area-inset-right, 0px), var(--zulors-android-safe-right, 0px))';
		const safeBottom = 'max(env(safe-area-inset-bottom, 0px), var(--zulors-android-safe-bottom, 0px))';
		const safeLeft = 'max(env(safe-area-inset-left, 0px), var(--zulors-android-safe-left, 0px))';

		root.style.setProperty('--business-safe-top', safeTop);
		root.style.setProperty('--business-safe-right', safeRight);
		root.style.setProperty('--business-safe-bottom', safeBottom);
		root.style.setProperty('--business-safe-left', safeLeft);

		if(typeof uiBridge.applyInsets === 'function') {
			uiBridge.applyInsets({
				top: safeTop,
				right: safeRight,
				bottom: safeBottom,
				left: safeLeft
			});
		}
	};

	window.addEventListener('resize', syncInsets, { passive: true });
	window.addEventListener('orientationchange', syncInsets, { passive: true });
	window.addEventListener('load', syncInsets, { passive: true });
	syncInsets();

	window.ZulorsBusinessBridge = {
		...uiBridge,
		applyInsets: (insets = {}) => {
			if(insets.top) {
				root.style.setProperty('--business-safe-top', insets.top);
			}
			if(insets.right) {
				root.style.setProperty('--business-safe-right', insets.right);
			}
			if(insets.bottom) {
				root.style.setProperty('--business-safe-bottom', insets.bottom);
			}
			if(insets.left) {
				root.style.setProperty('--business-safe-left', insets.left);
			}
		}
	};
}

function initBusinessShellNavigation() {
	const shellLinks = [
		...document.querySelectorAll('.business-mobile-nav-item'),
		...document.querySelectorAll('.business-mobile-more-item')
	];

	for(const link of shellLinks) {
		link.addEventListener('pointerdown', (event) => {
			const href = link.getAttribute('href') || link.closest('a')?.getAttribute('href');
			if(! href || event.button !== 0) {
				return;
			}

			const target = getBusinessNavigationTarget(href);
			if(! target || target === window.location.pathname + window.location.search + window.location.hash) {
				return;
			}

			event.preventDefault();
			navigateBusinessShell(href);
		}, { passive: false });

		link.addEventListener('touchstart', (event) => {
			const href = link.getAttribute('href') || link.closest('a')?.getAttribute('href');
			if(! href) {
				return;
			}

			const target = getBusinessNavigationTarget(href);
			if(! target) {
				return;
			}

			event.preventDefault();
			navigateBusinessShell(href);
		}, { passive: false });
	}

	window.addEventListener('popstate', (event) => {
		if(event.state?.businessShell) {
			const path = window.location.pathname + window.location.search + window.location.hash;
			const cached = businessTabCache.get(path);

			if(cached) {
				applyBusinessShellContent({ ...cached, path });
				return;
			}
		}
	});
}

function initBusinessInstantRefresh() {
	const body = document.body;

	if(! body || body.dataset.businessAutoRefresh !== 'on') {
		return;
	}

	const refreshRegionSelector = '[data-business-refresh-region]';
	let refreshInProgress = false;
	let lastRefreshAt = 0;
	let deferredRefreshId = null;

	const isEditingField = () => {
		const activeElement = document.activeElement;

		if(! activeElement) {
			return false;
		}

		return ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeElement.tagName) || activeElement.isContentEditable;
	};

	const isConfirmModalOpen = () => {
		try {
			return Boolean(window.Alpine?.store('confirmModal')?.isOpen);
		}
		catch(error) {
			return false;
		}
	};

	const shouldSkipRefresh = () => {
		return document.hidden || isEditingField() || isConfirmModalOpen();
	};

	const refreshPage = async (reason = 'manual', force = false) => {
		if(refreshInProgress || shouldSkipRefresh()) {
			return false;
		}

		const elapsed = Date.now() - lastRefreshAt;

		if(! force && elapsed < 1500) {
			window.clearTimeout(deferredRefreshId);

			deferredRefreshId = window.setTimeout(() => {
				refreshPage(reason, true);
			}, Math.max(1500 - elapsed, 0));

			return false;
		}

		const currentRegion = document.querySelector(refreshRegionSelector);

		if(! currentRegion) {
			return false;
		}

		refreshInProgress = true;
		lastRefreshAt = Date.now();

		try {
			const response = await fetch(window.location.href, {
				cache: 'no-store',
				credentials: 'same-origin',
				headers: {
					'Accept': 'text/html',
					'X-Requested-With': 'XMLHttpRequest',
					'X-Business-Instant-Refresh': reason
				}
			});

			if(! response.ok) {
				return false;
			}

			const responseUrl = new URL(response.url);

			if(responseUrl.origin !== window.location.origin || responseUrl.pathname.includes('/auth/')) {
				return false;
			}

			const html = await response.text();
			const parsedDocument = new DOMParser().parseFromString(html, 'text/html');
			const nextRegion = parsedDocument.querySelector(refreshRegionSelector);

			if(! nextRegion || shouldSkipRefresh()) {
				return false;
			}

			const scrollTop = window.scrollY;
			currentRegion.replaceWith(nextRegion);

			if(window.Alpine?.initTree) {
				window.Alpine.initTree(nextRegion);
			}

			window.dispatchEvent(new CustomEvent('business:content-refreshed', {
				detail: {
					reason: reason
				}
			}));

			window.scrollTo({
				top: scrollTop,
				behavior: 'auto'
			});
		}
		catch(error) {
			console.log(error);
		}
		finally {
			refreshInProgress = false;
		}
	};

	document.addEventListener('visibilitychange', () => {
		if(! document.hidden) {
			refreshPage('visibility');
		}
	});

	window.addEventListener('focus', () => {
		refreshPage('focus');
	});

	window.addEventListener('pageshow', () => {
		refreshPage('pageshow');
	});

	window.addEventListener('online', () => {
		refreshPage('online');
	});

	window.addEventListener('business:refresh', () => {
		refreshPage('event', true);
	});

	window.setInterval(() => {
		refreshPage('interval');
	}, 15000);
}

if(document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initBusinessInstantRefresh);
}
else {
	initBusinessInstantRefresh();
}
