import '../alpinejs/confirm.modal.js';

import '../alpinejs/components/code.js';

import {
	BusinessPageCache,
	getBusinessNavigationTarget,
	isBusinessUnsavedFormPath,
	isOneShotBusinessPath,
	shouldInterceptBusinessClick,
} from './navigation-policy.js';
import { hasBusinessFormChanges } from './unsaved-form.js';

const BUSINESS_PREFETCH_LIMIT = 2;
const businessPageCache = new BusinessPageCache();
const pendingBusinessPageRequests = new Map();
const businessPrefetchQueue = [];
let activeBusinessPrefetches = 0;
let activeBusinessNavigationRequest = null;
let businessNavigationSequence = 0;
let businessHistoryIndex = 0;
let businessPageTitleBase = document.title;
let activeBusinessPagePathname = window.location.pathname;
let revertingCancelledBusinessPop = false;
let businessApexChartsPromise = null;
const businessFormBaselines = new Map();
let businessFormMediaBaseline = '';

function getBusinessCacheKey(path) {
	const targetUrl = new URL(path, window.location.origin);
	return `${targetUrl.pathname}${targetUrl.search}`;
}

function isBusinessOneShotPath(path) {
	return isOneShotBusinessPath(new URL(path, window.location.origin).pathname);
}

function resolveBusinessTabName(pathname) {
	if(pathname === '/business' || pathname === '/business/') return 'overview';
	if(pathname.startsWith('/business/market/')) return 'marketplace';
	if(pathname === '/business/market') return 'marketplace';
	if(pathname.startsWith('/business/jobs/')) return 'jobs';
	if(pathname === '/business/jobs') return 'jobs';
	if(pathname.startsWith('/business/wallet')) return 'wallet';
	if(pathname.startsWith('/business/ads')) return 'campaign';
	if(pathname.startsWith('/business/settings')) return 'settings';
	if(pathname === '/document/help-center') return 'help';

	return 'overview';
}

function isBusinessMoreTab(tabName) {
	return ['campaign', 'settings', 'help'].includes(tabName);
}

function getBusinessTabLabel(tabName) {
	const items = [...document.querySelectorAll('[data-business-tab]')];
	const item = items.find(candidate => candidate.dataset.businessTab === tabName);

	if(! item) {
		return '';
	}

	return item.getAttribute('aria-label') || item.textContent.trim().replace(/\s+/g, ' ');
}

function getBusinessPageLabel(path, documentToRead = document, preferredLabel = '') {
	if(preferredLabel) {
		return preferredLabel;
	}

	const pathname = new URL(path, window.location.origin).pathname;
	const tabName = resolveBusinessTabName(pathname);

	if(pathname === '/document/help-center') {
		return documentToRead.querySelector('.document-container h1')?.textContent.trim()
			|| getBusinessTabLabel('help')
			|| 'Help';
	}

	if(isBusinessOneShotPath(pathname) && !isBusinessUnsavedFormPath(pathname)) {
		if(pathname.includes('/market/')) return getBusinessTabLabel('marketplace') || 'Create Product';
		if(pathname.includes('/jobs/')) return getBusinessTabLabel('jobs') || 'Create Job';
	}

	if(isBusinessUnsavedFormPath(pathname)) {
		const createHeading = documentToRead.querySelector('[data-business-refresh-region] h1, [data-business-refresh-region] h2')?.textContent.trim();
		if(createHeading) return createHeading;
		return pathname.includes('/market/') ? 'Create Product' : 'Create Job';
	}

	const mobileTitle = documentToRead.querySelector('.business-mobile-topbar .flex-1 span')?.textContent.trim();
	return mobileTitle || getBusinessTabLabel(tabName) || 'Business Account';
}

function setBusinessDocumentTitle(label, responseTitle = '') {
	const prefix = label ? `${label} · ` : '';
	const baseTitle = responseTitle && responseTitle.startsWith(prefix)
		? responseTitle.slice(prefix.length)
		: (responseTitle && responseTitle !== businessPageTitleBase ? responseTitle : businessPageTitleBase);

	document.title = label ? `${label} · ${baseTitle}` : baseTitle;
}

function resolveInlinePageScript(source) {
	let immediate = source.replace(/\bconst\s+alpineComponent\s*=/g, 'window.alpineComponent =');
	let deferredChart = '';
	const loadStart = immediate.search(/window\.addEventListener\(\s*(['"])load\1\s*,\s*\(\)\s*=>\s*\{/);

	if(loadStart >= 0) {
		const prefix = immediate.slice(loadStart);
		const handlerStart = prefix.indexOf('{') + 1;
		const handlerEnd = prefix.lastIndexOf('});');

		if(handlerStart > 0 && handlerEnd >= handlerStart) {
			deferredChart = prefix.slice(handlerStart, handlerEnd);
			deferredChart = deferredChart.replace(
				'new ApexCharts(document.querySelector("#chart"), {',
				'window.__businessActiveChart = new ApexCharts(document.querySelector("#chart"), {'
			);
			immediate = immediate.slice(0, loadStart);
		}
	}

	const alpineInitMatch = immediate.match(/window\.addEventListener\(\s*(['"])alpine:init\1\s*,\s*\(\)\s*=>\s*\{/);

	if(alpineInitMatch) {
		const eventStart = immediate.indexOf(alpineInitMatch[0]);
		const handlerStart = eventStart + alpineInitMatch[0].length;
		const handlerEnd = immediate.lastIndexOf('});');

		if(handlerEnd >= handlerStart) {
			immediate = `${immediate.slice(0, eventStart)}(() => {${immediate.slice(handlerStart, handlerEnd)}})();`;
		}
	}

	return { immediate, deferredChart };
}

function executeBusinessInlineScript(source) {
	if(! source.trim()) {
		return;
	}

	const script = document.createElement('script');
	script.textContent = source;
	document.head.append(script);
	script.remove();
}

function ensureBusinessApexCharts() {
	if(window.ApexCharts) {
		return Promise.resolve();
	}

	if(! businessApexChartsPromise) {
		businessApexChartsPromise = import('../mpa/apexcharts.js')
			.catch(error => {
				businessApexChartsPromise = null;
				throw error;
			});
	}

	return businessApexChartsPromise;
}

function runBusinessPageScripts(page) {
	for(const source of page.scripts || []) {
		const { immediate, deferredChart } = resolveInlinePageScript(source);
		executeBusinessInlineScript(immediate);

		if(deferredChart) {
			ensureBusinessApexCharts()
				.then(() => executeBusinessInlineScript(`(() => {${deferredChart}})();`))
				.catch(error => console.warn('Business page chart could not be loaded.', error));
		}
	}
}

function extractPageContent(region, { help = false } = {}) {
	const contentNode = region.cloneNode(true);
	const scripts = [...contentNode.querySelectorAll('script')]
		.filter(script => !script.src && script.type !== 'application/ld+json')
		.map(script => script.textContent)
		.filter(source => source.trim() !== '');

	contentNode.querySelectorAll('script').forEach(script => script.remove());

	if(help) {
		return {
			content: `<div class="business-help-content">${contentNode.innerHTML}</div>`,
			scripts,
		};
	}

	return { content: contentNode.innerHTML, scripts };
}

function getPageChrome(documentToRead) {
	const selectors = {
		header: 'main > header',
		sidebar: 'main > aside',
		mobile: '.business-mobile',
	};
	const chrome = {};

	for(const [name, selector] of Object.entries(selectors)) {
		const element = documentToRead.querySelector(selector);
		chrome[name] = element ? element.outerHTML : null;
	}

	return chrome;
}

function parseBusinessPage(html, path, preferredLabel = '') {
	const parsedDocument = new DOMParser().parseFromString(html, 'text/html');
	const pathname = new URL(path, window.location.origin).pathname;
	const businessRegion = parsedDocument.querySelector('[data-business-refresh-region]');
	const helpRegion = pathname === '/document/help-center'
		? parsedDocument.querySelector('.document-container')
		: null;
	const region = businessRegion || helpRegion;

	if(! region) {
		return null;
	}

	const isHelp = Boolean(helpRegion && !businessRegion);
	const { content, scripts } = extractPageContent(region, { help: isHelp });
	const label = getBusinessPageLabel(path, parsedDocument, preferredLabel);

	return {
		kind: isHelp ? 'help' : 'business',
		content,
		scripts,
		label,
		title: parsedDocument.title || businessPageTitleBase,
		chrome: isHelp ? null : getPageChrome(parsedDocument),
		autoRefresh: isHelp ? 'off' : (parsedDocument.body.dataset.businessAutoRefresh || 'off'),
	};
}

function captureCurrentBusinessPage(path = `${window.location.pathname}${window.location.search}${window.location.hash}`) {
	const region = document.querySelector('[data-business-refresh-region]');

	if(! region) {
		return null;
	}

	const { content, scripts } = extractPageContent(region);
	const label = getBusinessPageLabel(path);

	return {
		kind: 'business',
		content,
		scripts,
		label,
		title: document.title,
		chrome: getPageChrome(document),
		autoRefresh: document.body.dataset.businessAutoRefresh || 'off',
	};
}

function createBusinessLoadingSkeleton(label) {
	const safeLabel = String(label || 'Loading page').replace(/[&<>"']/g, character => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	}[character]));

	return `<div class="business-route-skeleton" role="status" aria-label="${safeLabel}">
		<div class="business-route-skeleton__title"></div>
		<div class="business-route-skeleton__line"></div>
		<div class="business-route-skeleton__line business-route-skeleton__line--short"></div>
		<div class="business-route-skeleton__card"></div>
	</div>`;
}

function replaceShellElement(selector, html) {
	const currentElement = document.querySelector(selector);

	if(! currentElement || ! html) {
		return;
	}

	const parsed = new DOMParser().parseFromString(html, 'text/html');
	const nextElement = parsed.body.firstElementChild;

	if(nextElement) {
		currentElement.replaceWith(nextElement);
	}
}

function activateBusinessNavigation(path) {
	const pathname = new URL(path, window.location.origin).pathname;
	const tabName = resolveBusinessTabName(pathname);
	const moreActive = isBusinessMoreTab(tabName);

	for(const item of document.querySelectorAll('[data-business-tab]')) {
		const itemTab = item.dataset.businessTab;
		const isActive = itemTab === tabName || (itemTab === 'more' && moreActive);
		item.classList.toggle('business-mobile-nav-item-active', isActive);
		item.classList.toggle('business-mobile-more-item-active', isActive && item.classList.contains('business-mobile-more-item'));

		if(item.closest('aside')) {
			item.classList.toggle('sidenav-active', isActive);
			item.classList.toggle('sidenav-inactive', !isActive);
		}
	}

	const moreToggle = document.querySelector('#business-more-toggle');
	if(moreToggle) moreToggle.checked = false;
}

function updateBusinessChrome(page, path) {
	if(page.chrome) {
		replaceShellElement('main > header', page.chrome.header);
		replaceShellElement('main > aside', page.chrome.sidebar);
		replaceShellElement('.business-mobile', page.chrome.mobile);
	}
	else {
		document.querySelectorAll('[data-business-create-action]').forEach(element => element.remove());
	}

	document.body.dataset.businessAutoRefresh = page.autoRefresh || 'off';
	activateBusinessNavigation(path);

	const mobileTitle = document.querySelector('.business-mobile-topbar .flex-1 span');
	if(mobileTitle) mobileTitle.textContent = page.label;

	setBusinessDocumentTitle(page.label, page.title);
}

function applyBusinessShellPage(page, path, { scrollTop = 0, metric = null } = {}) {
	const region = document.querySelector('[data-business-refresh-region]');

	if(! region) {
		return false;
	}

	try {
		window.__businessActiveChart?.destroy?.();
	}
	catch(error) {
		console.debug('Could not dispose the previous business chart.', error);
	}
	window.__businessActiveChart = null;

	region.innerHTML = page.content;
	region.classList.remove('business-route-pending');
	region.removeAttribute('aria-busy');
	document.body.removeAttribute('data-business-nav-pending');

	updateBusinessChrome(page, path);
	activeBusinessPagePathname = new URL(path, window.location.origin).pathname;
	runBusinessPageScripts(page);

	if(window.Alpine?.initTree) {
		window.Alpine.initTree(region);
	}
	captureBusinessFormBaseline(region);

	window.dispatchEvent(new CustomEvent('business:shell-updated', {
		detail: { path, kind: page.kind },
	}));

	window.scrollTo({ top: scrollTop, behavior: 'auto' });
	window.requestAnimationFrame(() => {
		if(metric) {
			dispatchBusinessNavigationMetric({ ...metric, renderMs: performance.now() - metric.renderStartedAt });
		}
		prefetchContextualBusinessPage(path);
	});

	return true;
}

function dispatchBusinessNavigationMetric(detail) {
	window.dispatchEvent(new CustomEvent('business:navigation-metric', { detail }));

	try {
		if(window.localStorage.getItem('business:navigation-debug') === '1') {
			console.info('[business navigation]', detail);
		}
	}
	catch(error) {
		// Storage can be unavailable in private browsing modes.
	}
}

function showBusinessNavigationPending(path, label) {
	const region = document.querySelector('[data-business-refresh-region]');
	if(! region) return;

	try {
		window.__businessActiveChart?.destroy?.();
	}
	catch(error) {
		console.debug('Could not dispose the previous business chart.', error);
	}
	window.__businessActiveChart = null;

	region.innerHTML = createBusinessLoadingSkeleton(label);
	region.classList.add('business-route-pending');
	region.setAttribute('aria-busy', 'true');
	document.body.dataset.businessNavPending = 'true';
	activeBusinessPagePathname = new URL(path, window.location.origin).pathname;
	activateBusinessNavigation(path);

	const mobileTitle = document.querySelector('.business-mobile-topbar .flex-1 span');
	if(mobileTitle) mobileTitle.textContent = label;
	setBusinessDocumentTitle(label);
}

function removeQueuedPrefetch(job) {
	const queueIndex = businessPrefetchQueue.indexOf(job);
	if(queueIndex >= 0) businessPrefetchQueue.splice(queueIndex, 1);
	job.queued = false;
}

function startBusinessPageRequest(job, { prefetch = false } = {}) {
	if(job.started) return;
	job.started = true;
	job.queued = false;
	job.prefetch = prefetch;

	if(prefetch) {
		activeBusinessPrefetches++;
	}
	else if(job.navigationPriority) {
		activeBusinessNavigationRequest = job;
	}

	const requestStartedAt = performance.now();

	fetch(job.path, {
		cache: 'no-store',
		credentials: 'same-origin',
		signal: job.controller.signal,
		headers: {
			'Accept': 'text/html',
			'X-Requested-With': 'XMLHttpRequest',
			'X-Business-Shell': '1',
		},
	})
		.then(async response => {
			const responseReceivedAt = performance.now();
			const finalTarget = getBusinessNavigationTarget(response.url, window.location.origin);

			if(! response.ok || ! finalTarget) {
				throw new Error(`Business route request returned ${response.status}.`);
			}

			const html = await response.text();
			const page = parseBusinessPage(html, finalTarget);

			if(! page) {
				throw new Error('The route response does not contain a supported app page.');
			}

			page.requestMs = responseReceivedAt - requestStartedAt;
			return page;
		})
		.then(job.resolve, job.reject)
		.finally(() => {
			if(job.prefetch) activeBusinessPrefetches = Math.max(0, activeBusinessPrefetches - 1);
			if(activeBusinessNavigationRequest === job) activeBusinessNavigationRequest = null;
			if(pendingBusinessPageRequests.get(job.key) === job) pendingBusinessPageRequests.delete(job.key);
			drainBusinessPrefetchQueue();
		});
}

function drainBusinessPrefetchQueue() {
	while(activeBusinessPrefetches < BUSINESS_PREFETCH_LIMIT && businessPrefetchQueue.length) {
		const job = businessPrefetchQueue.shift();
		if(pendingBusinessPageRequests.get(job.key) === job) {
			startBusinessPageRequest(job, { prefetch: true });
		}
	}
}

function requestBusinessPage(path, { priority = false } = {}) {
	const key = getBusinessCacheKey(path);
	let job = pendingBusinessPageRequests.get(key);

	if(job) {
		if(priority) {
			job.navigationPriority = true;

			if(job.queued) {
				removeQueuedPrefetch(job);
				startBusinessPageRequest(job);
			}
			else if(job.prefetch) {
				job.prefetch = false;
				activeBusinessPrefetches = Math.max(0, activeBusinessPrefetches - 1);
				activeBusinessNavigationRequest = job;
				drainBusinessPrefetchQueue();
			}
			else {
				activeBusinessNavigationRequest = job;
			}
		}

		return job.promise;
	}

	let resolveRequest;
	let rejectRequest;
	job = {
		key,
		path: key,
		controller: new AbortController(),
		promise: new Promise((resolve, reject) => {
			resolveRequest = resolve;
			rejectRequest = reject;
		}),
		resolve: resolveRequest,
		reject: rejectRequest,
		started: false,
		queued: false,
		prefetch: false,
		navigationPriority: priority,
	};
	pendingBusinessPageRequests.set(key, job);

	if(priority) {
		startBusinessPageRequest(job);
	}
	else {
		job.queued = true;
		businessPrefetchQueue.push(job);
		drainBusinessPrefetchQueue();
	}

	return job.promise;
}

function cancelActiveBusinessNavigation(exceptPath = '') {
	const activeJob = activeBusinessNavigationRequest;
	if(! activeJob || activeJob.key === getBusinessCacheKey(exceptPath)) return;

	activeBusinessNavigationRequest = null;
	activeJob.controller.abort();
	if(pendingBusinessPageRequests.get(activeJob.key) === activeJob) {
		pendingBusinessPageRequests.delete(activeJob.key);
	}
}

function prefetchBusinessPage(path) {
	const key = getBusinessCacheKey(path);
	if(businessPageCache.hasFresh(key) || pendingBusinessPageRequests.has(key)) return;

	requestBusinessPage(key)
		.then(page => {
			businessPageCache.set(key, page, { oneShot: isBusinessOneShotPath(key) });
			if(page.kind === 'business' && page.scripts?.some(script => script.includes('new ApexCharts'))) {
				ensureBusinessApexCharts().catch(() => {});
			}
		})
		.catch(() => {});
}

function scheduleBusinessPrefetch(paths) {
	if(navigator.onLine === false || navigator.connection?.saveData) return;
	const connectionType = navigator.connection?.effectiveType;
	if(connectionType === 'slow-2g' || connectionType === '2g') return;

	const prefetch = () => paths.forEach(path => prefetchBusinessPage(path));
	if(typeof window.requestIdleCallback === 'function') {
		window.requestIdleCallback(prefetch, { timeout: 1200 });
	}
	else {
		window.setTimeout(prefetch, 180);
	}
}

function prefetchBusinessTargets(selector, tabNames = null) {
	const allowedNames = tabNames ? new Set(tabNames) : null;
	const paths = [...document.querySelectorAll(selector)]
		.filter(anchor => !allowedNames || allowedNames.has(anchor.dataset.businessTab))
		.map(anchor => getBusinessNavigationTarget(anchor.getAttribute('href'), window.location.origin))
		.filter(Boolean);

	scheduleBusinessPrefetch([...new Set(paths)]);
}

function prefetchContextualBusinessPage(path) {
	const pathname = new URL(path, window.location.origin).pathname;
	let selector = null;

	if(pathname.startsWith('/business/market/') && !pathname.endsWith('/create')) {
		selector = 'a[data-business-create-action][href*="/business/market/create"], .business-mobile-topbar a[data-business-create-action]';
	}
	else if(pathname === '/business/market') {
		selector = 'a[data-business-create-action][href*="/business/market/create"], .business-mobile-topbar a[data-business-create-action]';
	}
	else if(pathname.startsWith('/business/jobs') && !pathname.endsWith('/create')) {
		selector = 'a[data-business-create-action][href*="/business/jobs/create"], .business-mobile-topbar a[data-business-create-action]';
	}

	if(selector) prefetchBusinessTargets(selector);
}

function showBusinessShellFallback(href) {
	window.location.assign(href);
}

function setBusinessHistoryEntry(path, index, scrollTop = 0, replace = false) {
	const state = {
		...(history.state || {}),
		businessShell: true,
		businessPageIndex: index,
		businessScrollTop: scrollTop,
	};

	if(replace) history.replaceState(state, '', path);
	else history.pushState(state, '', path);

	businessHistoryIndex = index;
}

function askToLeaveDirtyBusinessForm() {
	return window.confirm(document.body.dataset.businessUnsavedConfirm || 'You have unsaved changes. Leave this page?');
}

function businessFormHasUnsavedChanges() {
	if(!isBusinessUnsavedFormPath(activeBusinessPagePathname)) return false;

	const controls = document.querySelectorAll('[data-business-refresh-region] input, [data-business-refresh-region] textarea, [data-business-refresh-region] select, [data-business-refresh-region] [contenteditable="true"]');
	const fields = [];

	for(const control of controls) {
		const isLivewireControl = control.getAttributeNames().some(name => name === 'wire:model' || name.startsWith('wire:model.'));
		if(! isLivewireControl) continue;

		const componentId = control.closest('[wire\\:id]')?.getAttribute('wire:id') || 'business-form';
		const fieldKey = getBusinessFormFieldKey(control);
		fields.push({
			componentId,
			fieldKey,
			value: getBusinessFormControlValue(control),
			differsFromDefault: businessFormControlDiffersFromDefault(control),
		});
	}

	if(hasBusinessFormChanges(fields, businessFormBaselines)) return true;

	const region = document.querySelector('[data-business-refresh-region]');
	return region ? captureBusinessFormMediaState(region) !== businessFormMediaBaseline : false;
}

function getBusinessFormFieldKey(control) {
	const wireModel = control.getAttributeNames().find(name => name === 'wire:model' || name.startsWith('wire:model.'));
	const discriminator = control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)
		? control.value
		: '';
	return [control.name || control.id || wireModel || '', control.type || control.tagName, discriminator].join(':');
}

function getBusinessFormControlValue(control) {
	if(control.matches('[contenteditable="true"]')) return control.innerHTML;
	if(control instanceof HTMLInputElement && control.type === 'file') return control.files?.length || 0;
	if(control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)) return control.checked;
	if(control instanceof HTMLSelectElement && control.multiple) return [...control.selectedOptions].map(option => option.value);
	if(control instanceof HTMLSelectElement) return control.value;
	return 'value' in control ? control.value : '';
}

function businessFormControlDiffersFromDefault(control) {
	if(control.matches('[contenteditable="true"]')) return control.innerHTML !== '';
	if(control instanceof HTMLInputElement && control.type === 'file') return Boolean(control.files?.length);
	if(control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)) return control.checked !== control.defaultChecked;
	if(control instanceof HTMLSelectElement) return [...control.options].some(option => option.selected !== option.defaultSelected);
	return 'value' in control && control.value !== control.defaultValue;
}

function captureBusinessFormBaseline(region) {
	const nextBaselines = new Map();
	const controls = region.querySelectorAll('input, textarea, select, [contenteditable="true"]');
	businessFormBaselines.clear();
	businessFormMediaBaseline = captureBusinessFormMediaState(region);

	for(const control of controls) {
		const isLivewireControl = control.getAttributeNames().some(name => name === 'wire:model' || name.startsWith('wire:model.'));
		if(!isLivewireControl) continue;

		const componentId = control.closest('[wire\\:id]')?.getAttribute('wire:id') || 'business-form';
		if(!nextBaselines.has(componentId)) nextBaselines.set(componentId, new Map());
		nextBaselines.get(componentId).set(getBusinessFormFieldKey(control), getBusinessFormControlValue(control));
	}

	for(const [componentId, fields] of nextBaselines) {
		businessFormBaselines.set(componentId, fields);
	}
}

function captureBusinessFormMediaState(region) {
	return [...region.querySelectorAll('.business-media-grid img')]
		.map(image => image.currentSrc || image.getAttribute('src') || '')
		.join('\n');
}

function saveBusinessScrollPosition() {
	if(!history.state?.businessShell) return;
	history.replaceState({ ...history.state, businessScrollTop: window.scrollY }, '', window.location.href);
}

async function navigateBusinessShell(path, {
	historyMode = 'push',
	historyState = null,
	preferredLabel = '',
	scrollTop = 0,
} = {}) {
	const navigationStartedAt = performance.now();
	const key = getBusinessCacheKey(path);
	const currentKey = getBusinessCacheKey(`${window.location.pathname}${window.location.search}`);
	if(key === currentKey && historyMode !== 'pop') {
		if(historyMode === 'pop') window.scrollTo({ top: scrollTop, behavior: 'auto' });
		return;
	}

	if(historyMode === 'push') {
		saveBusinessScrollPosition();
		setBusinessHistoryEntry(path, businessHistoryIndex + 1);
	}
	else if(historyMode === 'pop' && historyState) {
		businessHistoryIndex = Number.isInteger(historyState.businessPageIndex)
			? historyState.businessPageIndex
			: businessHistoryIndex;
	}

	const sequence = ++businessNavigationSequence;
	cancelActiveBusinessNavigation(key);
	const cached = businessPageCache.get(key);
	const label = cached?.page.label || getBusinessPageLabel(path, document, preferredLabel);

	if(cached) {
		const renderStartedAt = performance.now();
		const applied = applyBusinessShellPage(cached.page, path, {
			scrollTop: historyMode === 'pop' ? scrollTop : 0,
			metric: {
				path: key,
				source: cached.oneShot ? 'prefetch' : (cached.fresh ? 'cache' : 'stale-cache'),
				requestMs: 0,
				clickToRenderMs: renderStartedAt - navigationStartedAt,
				renderStartedAt,
			},
		});

		if(!applied) {
			showBusinessShellFallback(path);
			return;
		}

		if(!cached.fresh && !cached.oneShot) {
			void revalidateBusinessPage(key, sequence);
		}
		return;
	}

	showBusinessNavigationPending(path, label);

	try {
		const page = await requestBusinessPage(key, { priority: true });
		if(sequence !== businessNavigationSequence) return;

		if(!isBusinessOneShotPath(key)) {
			businessPageCache.set(key, page);
		}
		else {
			businessPageCache.delete(key);
		}

		const renderStartedAt = performance.now();
		const applied = applyBusinessShellPage(page, path, {
			scrollTop: historyMode === 'pop' ? scrollTop : 0,
			metric: {
				path: key,
				source: 'network',
				requestMs: page.requestMs,
				clickToRenderMs: renderStartedAt - navigationStartedAt,
				renderStartedAt,
			},
		});

		if(!applied) showBusinessShellFallback(path);
	}
	catch(error) {
		if(sequence !== businessNavigationSequence || error?.name === 'AbortError') return;
		console.warn('Business shell navigation failed; using a full page request.', error);
		showBusinessShellFallback(path);
	}
}

async function revalidateBusinessPage(path, sequence) {
	try {
		const page = await requestBusinessPage(path, { priority: true });
		businessPageCache.set(path, page);

		if(sequence !== businessNavigationSequence || getBusinessCacheKey(`${location.pathname}${location.search}`) !== path || businessFormHasUnsavedChanges()) {
			return;
		}

		const scrollTop = window.scrollY;
		applyBusinessShellPage(page, path, { scrollTop });
	}
	catch(error) {
		if(error?.name !== 'AbortError') {
			console.debug('Business page background refresh failed.', error);
		}
	}
}

function initBusinessShellNavigation() {
	if(!document.querySelector('[data-business-refresh-region]')) return;

	businessPageTitleBase = document.title;
	activeBusinessPagePathname = window.location.pathname;
	const initialPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;
	const initialIndex = Number.isInteger(history.state?.businessPageIndex) ? history.state.businessPageIndex : 0;
	setBusinessHistoryEntry(initialPath, initialIndex, window.scrollY, true);

	const initialPage = captureCurrentBusinessPage(initialPath);
	captureBusinessFormBaseline(document.querySelector('[data-business-refresh-region]'));
	if(initialPage && !isBusinessOneShotPath(initialPath)) {
		businessPageCache.set(getBusinessCacheKey(initialPath), initialPage);
	}

	document.addEventListener('click', event => {
		const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
		if(!anchor) return;

		const href = anchor.href;
		const path = getBusinessNavigationTarget(href, window.location.origin);
		const currentPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;
		const nextUrl = path ? new URL(path, window.location.origin) : null;
		const currentUrl = new URL(currentPath, window.location.origin);
		const isSamePage = nextUrl && nextUrl.pathname === currentUrl.pathname && nextUrl.search === currentUrl.search;
		if(!shouldInterceptBusinessClick(event, anchor)) return;

		if(isSamePage) {
			if(nextUrl.hash === currentUrl.hash) event.preventDefault();
			return;
		}

		if(businessFormHasUnsavedChanges() && !askToLeaveDirtyBusinessForm()) {
			event.preventDefault();
			return;
		}

		if(!path) return;

		event.preventDefault();
		const label = anchor.getAttribute('aria-label') || anchor.textContent.trim().replace(/\s+/g, ' ');
		void navigateBusinessShell(path, { preferredLabel: label });
	});

	document.addEventListener('mouseover', event => {
		const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
		const path = anchor ? getBusinessNavigationTarget(anchor.getAttribute('href'), window.location.origin) : null;
		if(path) prefetchBusinessPage(path);
	});

	document.addEventListener('focusin', event => {
		const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
		const path = anchor ? getBusinessNavigationTarget(anchor.getAttribute('href'), window.location.origin) : null;
		if(path) prefetchBusinessPage(path);
	});

	document.addEventListener('change', event => {
		if(event.target?.matches?.('#business-more-toggle') && event.target.checked) {
			prefetchBusinessTargets('.business-mobile-more-list a[href]', ['campaign', 'settings', 'help']);
		}

	});

	window.addEventListener('popstate', event => {
		const path = `${window.location.pathname}${window.location.search}${window.location.hash}`;
		const targetIndex = Number.isInteger(event.state?.businessPageIndex)
			? event.state.businessPageIndex
			: businessHistoryIndex;

		if(revertingCancelledBusinessPop) {
			revertingCancelledBusinessPop = false;
			businessHistoryIndex = targetIndex;
			return;
		}

		if(businessFormHasUnsavedChanges() && !askToLeaveDirtyBusinessForm()) {
			const delta = businessHistoryIndex - targetIndex;
			if(delta !== 0) {
				revertingCancelledBusinessPop = true;
				history.go(delta);
			}
			return;
		}

		void navigateBusinessShell(path, {
			historyMode: 'pop',
			historyState: event.state,
			scrollTop: Number(event.state?.businessScrollTop) || 0,
		});
	});

	window.addEventListener('scroll', saveBusinessScrollPosition, { passive: true });
	window.addEventListener('business:content-refreshed', () => {
		const path = `${window.location.pathname}${window.location.search}${window.location.hash}`;
		const page = captureCurrentBusinessPage(path);
		if(page && !isBusinessOneShotPath(path)) businessPageCache.set(getBusinessCacheKey(path), page);
	});

	scheduleBusinessPrefetch(
		[...document.querySelectorAll('.business-mobile-bottomnav a[data-business-tab]')]
			.filter(anchor => ['overview', 'marketplace', 'jobs', 'wallet'].includes(anchor.dataset.businessTab))
			.map(anchor => getBusinessNavigationTarget(anchor.getAttribute('href'), window.location.origin))
			.filter(Boolean),
	);
	prefetchContextualBusinessPage(initialPath);
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

function initBusinessInstantRefresh() {
	const body = document.body;
	if(!body) return;

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
		if(body.dataset.businessAutoRefresh !== 'on' || refreshInProgress || shouldSkipRefresh()) {
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

initBusinessNativeBridge();
initBusinessShellNavigation();

if(document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initBusinessInstantRefresh);
}
else {
	initBusinessInstantRefresh();
}
