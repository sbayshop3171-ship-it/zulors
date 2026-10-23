export const BUSINESS_PAGE_CACHE_TTL = 15_000;
export const BUSINESS_PAGE_CACHE_STALE_TTL = 120_000;

export function isBusinessShellPathname(pathname) {
	return pathname === '/business'
		|| pathname.startsWith('/business/')
		|| pathname === '/document/help-center';
}

export function getBusinessNavigationTarget(href, origin) {
	if(! href || ! origin) {
		return null;
	}

	try {
		const targetUrl = new URL(href, origin);

		if(targetUrl.origin !== origin || ! isBusinessShellPathname(targetUrl.pathname)) {
			return null;
		}

		return `${targetUrl.pathname}${targetUrl.search}${targetUrl.hash}`;
	}
	catch(error) {
		return null;
	}
}

export function shouldInterceptBusinessClick(event, anchor) {
	if(! event || ! anchor || event.button !== 0) {
		return false;
	}

	if(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
		return false;
	}

	if(anchor.hasAttribute('download') || anchor.getAttribute('rel')?.split(/\s+/).includes('external')) {
		return false;
	}

	const target = anchor.getAttribute('target');

	return ! target || target === '_self';
}

export function isOneShotBusinessPath(pathname) {
	return /^\/business\/(?:(?:market|jobs|ads)\/(?:create|\d+\/edit)|wallet\/cashouts\/new|settings\/edit)\/?$/.test(pathname);
}

export function isBusinessUnsavedFormPath(pathname) {
	return /^\/business\/(?:market|jobs)\/create\/?$/.test(pathname);
}

export class BusinessPageCache {
	constructor({
		maxEntries = 10,
		freshTtl = BUSINESS_PAGE_CACHE_TTL,
		staleTtl = BUSINESS_PAGE_CACHE_STALE_TTL,
	} = {}) {
		this.maxEntries = maxEntries;
		this.freshTtl = freshTtl;
		this.staleTtl = staleTtl;
		this.entries = new Map();
	}

	get(key, now = Date.now()) {
		const entry = this.entries.get(key);

		if(! entry) {
			return null;
		}

		const age = now - entry.savedAt;

		if(age > (entry.oneShot ? this.freshTtl : this.staleTtl)) {
			this.delete(key);
			return null;
		}

		this.entries.delete(key);

		if(! entry.oneShot) {
			this.entries.set(key, entry);
		}
		else {
			this.delete(key);
		}

		return {
			page: entry.page,
			fresh: age <= this.freshTtl,
			oneShot: entry.oneShot,
		};
	}

	hasFresh(key, now = Date.now()) {
		const entry = this.entries.get(key);

		if(! entry) {
			return false;
		}

		const age = now - entry.savedAt;
		const maxAge = entry.oneShot ? this.freshTtl : this.staleTtl;

		if(age > maxAge) {
			this.delete(key);
			return false;
		}

		return age <= this.freshTtl;
	}

	set(key, page, { oneShot = false, now = Date.now() } = {}) {
		this.delete(key);
		this.entries.set(key, { page, oneShot, savedAt: now });

		while(this.entries.size > this.maxEntries) {
			this.entries.delete(this.entries.keys().next().value);
		}
	}

	delete(key) {
		this.entries.delete(key);
	}

	clear() {
		this.entries.clear();
	}
}
