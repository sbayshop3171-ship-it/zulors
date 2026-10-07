import { computed, onMounted, onUnmounted, ref, unref, watch } from 'vue';
import { useRoute } from 'vue-router';

const TOP_VISIBLE_SCROLL_Y = 24;
const HIDE_START_SCROLL_Y = 24;
const HIDE_DELTA = 4;
const SHOW_DELTA = 0;

const isScrollableElement = (element) => {
	if(typeof window === 'undefined' || ! element || element === document.body) {
		return false;
	}

	const overflowY = window.getComputedStyle(element).overflowY;

	return ['auto', 'overlay', 'scroll'].includes(overflowY) && element.scrollHeight > element.clientHeight;
};

const resolveScrollTarget = (source = null) => {
	if(typeof window === 'undefined' || typeof document === 'undefined') {
		return null;
	}

	const resolvedSource = typeof source === 'function' ? source() : unref(source) || source;

	if(resolvedSource === window || resolvedSource === document || ! resolvedSource) {
		return document.scrollingElement || document.documentElement;
	}

	if(resolvedSource.nodeType === 1) {
		let candidate = resolvedSource;

		while(candidate && candidate !== document.body) {
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

export function useAutoHideHeader(options = {}) {
	const route = useRoute();
	const isHidden = ref(false);
	const isFullscreen = ref(false);
	const isScrollLocked = ref(false);
	let lastScrollY = 0;
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

			return;
		}

		if(currentScrollY <= TOP_VISIBLE_SCROLL_Y) {
			isHidden.value = false;
			lastScrollY = currentScrollY;

			return;
		}

		const scrollDelta = currentScrollY - lastScrollY;

		if(scrollDelta >= HIDE_DELTA && currentScrollY > HIDE_START_SCROLL_Y) {
			isHidden.value = true;
			lastScrollY = currentScrollY;

			return;
		}

		// Reveal on every real upward movement. Mobile browsers often emit
		// several sub-pixel scroll events while the finger is moving back up.
		if(scrollDelta < 0 && (isHidden.value || Math.abs(scrollDelta) >= SHOW_DELTA)) {
			isHidden.value = false;
			lastScrollY = currentScrollY;

			return;
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

		const customTarget = resolveScrollTarget(options.scrollTarget);

		return [customTarget].filter((target, index, targets) => {
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
		}
	}, {
		flush: 'post'
	});

	watch(() => unref(options.scrollTarget), () => {
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
		document.addEventListener('fullscreenchange', syncFullscreenState);
	});

	onUnmounted(() => {
		isMounted = false;

		if(typeof window !== 'undefined') {
			unbindScrollTargets();
			window.removeEventListener('zulors:scroll-lock-changed', syncScrollLockState);

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
