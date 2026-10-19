import { computed, onMounted, onUnmounted, ref, unref, watch } from 'vue';
import { useRoute } from 'vue-router';

const TOP_VISIBLE_SCROLL_Y = 24;
const HIDE_START_SCROLL_Y = 24;
const HIDE_DELTA = 4;
const SCROLL_TO_TOP_REQUEST_EVENT = 'zulors:scroll-to-top-request';
const SCROLL_TO_TOP_EVENT = 'zulors:scroll-to-top';
const scrollToTopOperations = new WeakMap();

const resolveScrollSource = (source = null) => {
	return typeof source === 'function' ? source() : unref(source) || source;
};

const isScrollableElement = (element) => {
	if(typeof window === 'undefined' || ! element) {
		return false;
	}

	const overflowY = window.getComputedStyle(element).overflowY;

	return ['auto', 'overlay', 'scroll'].includes(overflowY) && element.scrollHeight > element.clientHeight;
};

const resolveScrollTarget = (source = null) => {
	if(typeof window === 'undefined' || typeof document === 'undefined') {
		return null;
	}

	const resolvedSource = resolveScrollSource(source);

	if(resolvedSource === window || resolvedSource === document || ! resolvedSource) {
		return document.scrollingElement || document.documentElement;
	}

	if(resolvedSource.nodeType === 1) {
		let candidate = resolvedSource;

		while(candidate) {
			if(isScrollableElement(candidate)) {
				return candidate;
			}

			candidate = candidate.parentElement;
		}
	}

	return document.scrollingElement || document.documentElement;
};

const readScrollY = (source = null) => {
	const scrollTarget = resolveScrollTarget(source);

	if(scrollTarget && Number.isFinite(scrollTarget.scrollTop)) {
		return Math.max(0, scrollTarget.scrollTop);
	}

	return typeof window !== 'undefined' ? Math.max(0, window.scrollY || window.pageYOffset || 0) : 0;
};

const readBoolean = (source) => {
	if(typeof source === 'function') {
		return Boolean(source());
	}

	return Boolean(unref(source));
};

const focusWithoutScrolling = (element) => {
	if(! element?.isConnected || typeof element.focus !== 'function') {
		return;
	}

	try {
		element.focus({ preventScroll: true });
	} catch (error) {
		element.focus();
	}
};

export function scrollToTop(options = {}) {
	const scrollTarget = resolveScrollTarget(options.scrollTarget);

	if(! scrollTarget || typeof window === 'undefined') {
		return Promise.resolve(false);
	}

	const existingOperation = scrollToTopOperations.get(scrollTarget);

	if(existingOperation) {
		if(options.focusTarget) {
			existingOperation.focusTarget = options.focusTarget;
		}

		return existingOperation.promise;
	}

	let resolveOperation;
	let animationFrame = null;
	let fallbackTimer = null;
	let stableFrames = 0;
	let lastScrollTop = scrollTarget.scrollTop;
	const startTime = Date.now();
	const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || false;
	const operation = {
		focusTarget: options.focusTarget || null,
		promise: new Promise((resolve) => {
			resolveOperation = resolve;
		})
	};

	const finish = () => {
		if(! scrollToTopOperations.has(scrollTarget)) {
			return;
		}

		if(animationFrame !== null) {
			window.cancelAnimationFrame(animationFrame);
		}

		if(fallbackTimer !== null) {
			window.clearTimeout(fallbackTimer);
		}

		scrollTarget.removeEventListener('scrollend', finish);
		scrollToTopOperations.delete(scrollTarget);
		focusWithoutScrolling(operation.focusTarget);
		window.dispatchEvent(new CustomEvent(SCROLL_TO_TOP_EVENT, {
			detail: { scrollTarget: scrollTarget }
		}));
		resolveOperation(true);
	};

	const monitorScroll = () => {
		const currentScrollTop = Math.max(0, scrollTarget.scrollTop || 0);

		if(currentScrollTop <= 1 || Date.now() - startTime >= 8000) {
			finish();

			return;
		}

		stableFrames = Math.abs(currentScrollTop - lastScrollTop) < 0.1 ? stableFrames + 1 : 0;
		lastScrollTop = currentScrollTop;

		if(stableFrames >= 18) {
			finish();

			return;
		}

		animationFrame = window.requestAnimationFrame(monitorScroll);
	};

	scrollToTopOperations.set(scrollTarget, operation);
	window.dispatchEvent(new CustomEvent(SCROLL_TO_TOP_EVENT, {
		detail: { scrollTarget: scrollTarget }
	}));

	if(reducedMotion || options.behavior === 'instant' || scrollTarget.scrollTop <= 1) {
		scrollTarget.scrollTop = 0;
		finish();

		return operation.promise;
	}

	scrollTarget.addEventListener('scrollend', finish, { once: true });
	scrollTarget.scrollTo({ top: 0, behavior: 'smooth' });
	animationFrame = window.requestAnimationFrame(monitorScroll);
	fallbackTimer = window.setTimeout(finish, 8500);

	return operation.promise;
}

export function requestScrollToTop() {
	if(typeof window !== 'undefined') {
		window.dispatchEvent(new CustomEvent(SCROLL_TO_TOP_REQUEST_EVENT));
	}
}

export function useAutoHideHeader(options = {}) {
	const route = useRoute();
	const isHidden = ref(false);
	const isFullscreen = ref(false);
	const isScrollLocked = ref(false);
	let lastScrollY = 0;
	let downwardDistance = 0;
	let animationFrame = null;
	let isMounted = false;
	let scrollTargets = [];

	const isPinned = computed(() => {
		return isFullscreen.value ||
			isScrollLocked.value ||
			readBoolean(options.isPinned) ||
			readBoolean(options.isMenuOpen);
	});

	const resetToVisible = () => {
		isHidden.value = false;
		lastScrollY = readScrollY(options.scrollTarget);
		downwardDistance = 0;
	};

	let pendingScrollSource = null;

	const evaluateScroll = () => {
		animationFrame = null;

		if(! isMounted) {
			return;
		}

		const currentScrollY = readScrollY(pendingScrollSource);
		pendingScrollSource = null;

		if(isPinned.value) {
			isHidden.value = false;
			lastScrollY = currentScrollY;
			downwardDistance = 0;

			return;
		}

		if(currentScrollY <= TOP_VISIBLE_SCROLL_Y) {
			isHidden.value = false;
			lastScrollY = currentScrollY;
			downwardDistance = 0;

			return;
		}

		const scrollDelta = currentScrollY - lastScrollY;
		// Always track the latest position, including movement below HIDE_DELTA.
		// Otherwise a small reversal is compared with an older position and lost.
		lastScrollY = currentScrollY;

		// Reveal on every real upward movement. Mobile browsers often emit
		// several sub-pixel scroll events while the finger is moving back up.
		if(scrollDelta < 0) {
			isHidden.value = false;
			downwardDistance = 0;

			return;
		}

		downwardDistance += scrollDelta;
		if(downwardDistance >= HIDE_DELTA && currentScrollY > HIDE_START_SCROLL_Y) {
			isHidden.value = true;
		}
	};

	const requestScrollEvaluation = (event = null) => {
		// Keep the newest scroll source even when a frame is already queued. A
		// quick direction change must not be lost between animation frames.
		pendingScrollSource = event?.target || pendingScrollSource;

		if(animationFrame !== null || typeof window === 'undefined') {
			return;
		}

		animationFrame = window.requestAnimationFrame(evaluateScroll);
	};

	const collectScrollTargets = () => {
		if(typeof window === 'undefined' || typeof document === 'undefined') {
			return [];
		}

		const scrollSource = resolveScrollSource(options.scrollTarget);
		const customTarget = resolveScrollTarget(options.scrollTarget);
		const documentScrollTarget = document.scrollingElement || document.documentElement;
		const ancestorTargets = [];

		// The mobile shell can scroll either through the document scrollport or
		// through an overflow-y ancestor. Bind the route surface and every
		// ancestor so native touch scrolling and browser inertia are observed in
		// both layouts.
		if(scrollSource?.nodeType === 1) {
			let ancestor = scrollSource;

			while(ancestor) {
				ancestorTargets.push(ancestor);
				ancestor = ancestor.parentElement;
			}
		}

		const candidates = [
			customTarget,
			...ancestorTargets,
			documentScrollTarget,
			document.body,
			document,
			window
		];

		return candidates.filter((target, index, targets) => {
			return target && targets.indexOf(target) === index && typeof target.addEventListener === 'function';
		});
	};

	const bindScrollTargets = () => {
		scrollTargets = collectScrollTargets();

		scrollTargets.forEach((target) => {
			target.addEventListener('scroll', requestScrollEvaluation, {
				capture: true,
				passive: true
			});
		});
	};

	const unbindScrollTargets = () => {
		scrollTargets.forEach((target) => {
			target.removeEventListener('scroll', requestScrollEvaluation, true);
		});

		scrollTargets = [];
	};

	const syncFullscreenState = () => {
		if(typeof document === 'undefined') {
			return;
		}

		isFullscreen.value = Boolean(document.fullscreenElement);
		resetToVisible();
	};

	const syncScrollLockState = (event = null) => {
		if(typeof window === 'undefined') {
			return;
		}

		isScrollLocked.value = Boolean(event?.detail?.active || window.ACTIVE_MODALS > 0);
		resetToVisible();
	};

	const handleScrollToTopRequest = () => {
		isHidden.value = false;
		void scrollToTop({ scrollTarget: options.scrollTarget });
	};

	const handleScrollToTop = (event) => {
		const expectedTarget = resolveScrollTarget(options.scrollTarget);

		if(! event?.detail?.scrollTarget || event.detail.scrollTarget === expectedTarget) {
			resetToVisible();
		}
	};

	watch(() => route.fullPath, () => {
		resetToVisible();
	}, {
		flush: 'post'
	});

	watch(isPinned, (pinned) => {
		if(pinned) {
			resetToVisible();
		}
		else {
			lastScrollY = readScrollY(options.scrollTarget);
			downwardDistance = 0;
		}
	}, {
		flush: 'post'
	});

	watch(() => resolveScrollSource(options.scrollTarget), () => {
		if(! isMounted) {
			return;
		}

		unbindScrollTargets();
		bindScrollTargets();
		resetToVisible();
	}, {
		flush: 'post'
	});

	onMounted(() => {
		if(typeof window === 'undefined') {
			return;
		}

		isMounted = true;
		resetToVisible();
		syncFullscreenState();
		syncScrollLockState();

		bindScrollTargets();
		window.addEventListener('zulors:scroll-lock-changed', syncScrollLockState);
		window.addEventListener(SCROLL_TO_TOP_REQUEST_EVENT, handleScrollToTopRequest);
		window.addEventListener(SCROLL_TO_TOP_EVENT, handleScrollToTop);
		document.addEventListener('fullscreenchange', syncFullscreenState);
	});

	onUnmounted(() => {
		isMounted = false;

		if(typeof window !== 'undefined') {
			unbindScrollTargets();
			window.removeEventListener('zulors:scroll-lock-changed', syncScrollLockState);
			window.removeEventListener(SCROLL_TO_TOP_REQUEST_EVENT, handleScrollToTopRequest);
			window.removeEventListener(SCROLL_TO_TOP_EVENT, handleScrollToTop);

			if(animationFrame !== null) {
				window.cancelAnimationFrame(animationFrame);
				animationFrame = null;
			}
		}

		if(typeof document !== 'undefined') {
			document.removeEventListener('fullscreenchange', syncFullscreenState);
		}
	});

	return {
		isHeaderHidden: computed(() => {
			return ! isPinned.value && isHidden.value;
		}),
		resetToVisible
	};
}
