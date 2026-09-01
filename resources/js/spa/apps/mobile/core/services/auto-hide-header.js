import { computed, onMounted, onUnmounted, ref, unref, watch } from 'vue';
import { useRoute } from 'vue-router';

const TOP_VISIBLE_SCROLL_Y = 24;
const HIDE_START_SCROLL_Y = 64;
const HIDE_DELTA = 12;
const SHOW_DELTA = 8;

const readScrollY = () => {
	if(typeof window === 'undefined') {
		return 0;
	}

	const scrollCandidates = [
		window.scrollY,
		window.pageYOffset,
		document.scrollingElement?.scrollTop,
		document.documentElement?.scrollTop,
		document.body?.scrollTop
	].filter((scrollTop) => {
		return Number.isFinite(scrollTop);
	});

	return Math.max(0, ...scrollCandidates, 0);
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
		lastScrollY = readScrollY();
	};

	const evaluateScroll = () => {
		animationFrame = null;

		if(! isMounted) {
			return;
		}

		const currentScrollY = readScrollY();

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

		if(scrollDelta <= -SHOW_DELTA) {
			isHidden.value = false;
			lastScrollY = currentScrollY;
		}
	};

	const requestScrollEvaluation = () => {
		if(animationFrame !== null || typeof window === 'undefined') {
			return;
		}

		animationFrame = window.requestAnimationFrame(evaluateScroll);
	};

	const collectScrollTargets = () => {
		if(typeof window === 'undefined' || typeof document === 'undefined') {
			return [];
		}

		return [
			window,
			document,
			document.scrollingElement,
			document.documentElement,
			document.body
		].filter((target, index, targets) => {
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
			lastScrollY = readScrollY();
		}
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
