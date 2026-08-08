import { computed, onMounted, onUnmounted, unref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const defaultThreshold = 72;
const defaultVerticalTolerance = 96;
const defaultDominanceRatio = 1.2;
const navigationCooldownMs = 350;

const interactiveIgnoreSelector = [
	'input',
	'textarea',
	'select',
	'option',
	'button',
	'label',
	'video',
	'audio',
	'iframe',
	'[role="button"]',
	'[contenteditable="true"]',
	'[data-swipe-nav-lock]'
].join(', ');

export const mobileHomeSwipeSequence = [
	{ name: 'home_index' },
	{ name: 'explore_posts' }
];

export const mobileExploreSwipeSequence = [
	{ name: 'home_index' },
	{ name: 'explore_posts' },
	{ name: 'explore_reels' },
	{ name: 'explore_people' }
];

const resolveSequence = function(routeSequence) {
	const sequence = typeof routeSequence === 'function' ? routeSequence() : unref(routeSequence);

	return Array.isArray(sequence) ? sequence.filter(Boolean) : [];
};

const routeMatchesLocation = function(route, location = {}) {
	if(! location?.name) {
		return false;
	}

	if(Array.isArray(location.matchNames) && location.matchNames.length) {
		return location.matchNames.includes(route.name);
	}

	return location.name === route.name;
};

const shouldIgnoreSwipeTarget = function(target) {
	if(! (target instanceof Element)) {
		return false;
	}

	if(target.closest(interactiveIgnoreSelector)) {
		return true;
	}

	let current = target;

	while(current && current !== document.body) {
		const tagName = current.tagName?.toLowerCase?.() || '';

		if(tagName === 'swiper-container' || tagName === 'swiper-slide') {
			return true;
		}

		const style = window.getComputedStyle(current);
		const isHorizontalScroller = ['auto', 'scroll'].includes(style.overflowX) && current.scrollWidth > (current.clientWidth + 12);

		if(isHorizontalScroller) {
			return true;
		}

		current = current.parentElement;
	}

	return false;
};

export const useSwipeRouteNavigation = function(surfaceRef, routeSequence, options = {}) {
	const router = useRouter();
	const route = useRoute();

	let activeSurface = null;
	let startX = 0;
	let startY = 0;
	let isTracking = false;
	let lastNavigationAt = 0;

	const threshold = Number(options.threshold || defaultThreshold);
	const verticalTolerance = Number(options.verticalTolerance || defaultVerticalTolerance);
	const dominanceRatio = Number(options.dominanceRatio || defaultDominanceRatio);

	const sequence = computed(() => {
		return resolveSequence(routeSequence);
	});

	const currentIndex = computed(() => {
		return sequence.value.findIndex((item) => routeMatchesLocation(route, item));
	});

	const resetGesture = () => {
		isTracking = false;
		startX = 0;
		startY = 0;
	};

	const navigateByOffset = async (offset) => {
		const nextIndex = currentIndex.value + offset;
		const nextLocation = sequence.value[nextIndex];

		if(! nextLocation) {
			return;
		}

		const targetRoute = router.resolve(nextLocation);

		if(targetRoute.fullPath === route.fullPath) {
			return;
		}

		if((Date.now() - lastNavigationAt) < navigationCooldownMs) {
			return;
		}

		lastNavigationAt = Date.now();

		await router.push(nextLocation).catch(() => {});
	};

	const handleTouchStart = (event) => {
		if(event.touches.length !== 1 || currentIndex.value === -1 || shouldIgnoreSwipeTarget(event.target)) {
			resetGesture();
			return;
		}

		const touch = event.touches[0];

		startX = touch.clientX;
		startY = touch.clientY;
		isTracking = true;
	};

	const handleTouchCancel = () => {
		resetGesture();
	};

	const handleTouchEnd = (event) => {
		if(! isTracking || event.changedTouches.length !== 1) {
			resetGesture();
			return;
		}

		const touch = event.changedTouches[0];
		const deltaX = touch.clientX - startX;
		const deltaY = touch.clientY - startY;
		const absoluteX = Math.abs(deltaX);
		const absoluteY = Math.abs(deltaY);

		resetGesture();

		if(absoluteX < threshold || absoluteY > verticalTolerance || absoluteX < (absoluteY * dominanceRatio)) {
			return;
		}

		void navigateByOffset(deltaX < 0 ? 1 : -1);
	};

	const attachListeners = () => {
		activeSurface = unref(surfaceRef);

		if(! activeSurface) {
			return;
		}

		activeSurface.addEventListener('touchstart', handleTouchStart, { passive: true });
		activeSurface.addEventListener('touchend', handleTouchEnd, { passive: true });
		activeSurface.addEventListener('touchcancel', handleTouchCancel, { passive: true });
	};

	const detachListeners = () => {
		if(! activeSurface) {
			return;
		}

		activeSurface.removeEventListener('touchstart', handleTouchStart);
		activeSurface.removeEventListener('touchend', handleTouchEnd);
		activeSurface.removeEventListener('touchcancel', handleTouchCancel);
		activeSurface = null;
	};

	onMounted(() => {
		attachListeners();
	});

	onUnmounted(() => {
		detachListeners();
	});
};
