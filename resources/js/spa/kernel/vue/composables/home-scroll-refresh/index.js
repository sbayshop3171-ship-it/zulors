import { colibriEventBus } from '@/kernel/events/bus/index.js';

const homeScrollRefreshEvent = 'home:scroll-refresh';
const topThresholdPx = 2;
const maxSmoothScrollWaitMs = 900;

const getScrollTop = () => {
	return window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
};

const prefersReducedMotion = () => {
	return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
};

const waitUntilTop = () => {
	return new Promise((resolve) => {
		const startedAt = Date.now();

		const tick = () => {
			if(getScrollTop() <= topThresholdPx || (Date.now() - startedAt) >= maxSmoothScrollWaitMs) {
				resolve();
				return;
			}

			window.requestAnimationFrame(tick);
		};

		tick();
	});
};

const scrollWindowToTop = async () => {
	if(getScrollTop() <= topThresholdPx) {
		return;
	}

	const behavior = prefersReducedMotion() ? 'auto' : 'smooth';

	window.scrollTo({
		top: 0,
		left: 0,
		behavior: behavior
	});

	if(behavior === 'smooth') {
		await waitUntilTop();
	}
};

const requestHomeScrollRefresh = () => {
	colibriEventBus.emit(homeScrollRefreshEvent);
};

export { homeScrollRefreshEvent, requestHomeScrollRefresh, scrollWindowToTop };
