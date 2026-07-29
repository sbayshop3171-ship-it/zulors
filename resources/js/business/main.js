import '../alpinejs/confirm.modal.js';

import '../alpinejs/components/code.js';

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
