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

	return Math.max(
		0,
		window.scrollY ||
		window.pageYOffset ||
		document.documentElement?.scrollTop ||
		document.body?.scrollTop ||
		0
	);
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
	let lastScrollY = 0;
	let animationFrame = null;
	let isMounted = false;

	const isPinned = computed(() => {
		return isFullscreen.value ||
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

	const syncFullscreenState = () => {
		if(typeof document === 'undefined') {
			return;
		}

		isFullscreen.value = Boolean(document.fullscreenElement);
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

		window.addEventListener('scroll', requestScrollEvaluation, {
			passive: true
		});

		document.addEventListener('fullscreenchange', syncFullscreenState);
	});

	onUnmounted(() => {
		isMounted = false;

		if(typeof window !== 'undefined') {
			window.removeEventListener('scroll', requestScrollEvaluation);

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
