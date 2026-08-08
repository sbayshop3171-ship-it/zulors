import { computed, onUnmounted, unref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const defaultThreshold = 72;
const defaultVerticalTolerance = 96;
const defaultDominanceRatio = 1.2;
const navigationCooldownMs = 350;
const dragActivationThreshold = 12;
const dragPreviewFactor = 0.32;
const blockedDragPreviewFactor = 0.16;
const maxDragPreviewPx = 72;
const dragSnapBackMs = 180;
const dragNavigatePreviewMs = 120;
const dragNavigateDelayMs = 70;

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
	{ name: 'explore_posts', matchNames: ['explore_index', 'explore_posts'] }
];

export const mobileExploreSwipeSequence = [
	{ name: 'home_index' },
	{ name: 'explore_posts', matchNames: ['explore_index', 'explore_posts'] },
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
	let lastDeltaX = 0;
	let lockedAxis = null;
	let cleanupTimerId = null;
	let navigationTimerId = null;

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
		lastDeltaX = 0;
		lockedAxis = null;
	};

	const clearSurfaceTimers = () => {
		if(cleanupTimerId) {
			window.clearTimeout(cleanupTimerId);
			cleanupTimerId = null;
		}

		if(navigationTimerId) {
			window.clearTimeout(navigationTimerId);
			navigationTimerId = null;
		}
	};

	const cleanupSurfaceStyles = () => {
		if(! activeSurface) {
			return;
		}

		activeSurface.style.removeProperty('transition');
		activeSurface.style.removeProperty('transform');
		activeSurface.style.removeProperty('opacity');
		activeSurface.style.removeProperty('will-change');
	};

	const scheduleSurfaceCleanup = (delay = 0) => {
		clearSurfaceTimers();

		if(delay <= 0) {
			cleanupSurfaceStyles();
			return;
		}

		cleanupTimerId = window.setTimeout(() => {
			cleanupTimerId = null;
			cleanupSurfaceStyles();
		}, delay);
	};

	const getTargetLocationByOffset = (offset) => {
		return sequence.value[currentIndex.value + offset] || null;
	};

	const canNavigateOffset = (offset) => {
		return Boolean(getTargetLocationByOffset(offset));
	};

	const animateSurfaceTo = (translateX = 0, {
		duration = dragSnapBackMs,
		opacity = 1
	} = {}) => {
		if(! activeSurface) {
			return;
		}

		clearSurfaceTimers();

		activeSurface.style.transition = `transform ${duration}ms cubic-bezier(0.22, 1, 0.36, 1), opacity ${duration}ms ease-out`;
		activeSurface.style.transform = `translate3d(${Math.round(translateX)}px, 0, 0)`;
		activeSurface.style.opacity = String(opacity);
		activeSurface.style.willChange = 'transform, opacity';

		if(translateX === 0 && opacity === 1) {
			scheduleSurfaceCleanup(duration + 24);
		}
	};

	const applySurfaceDrag = (deltaX) => {
		if(! activeSurface) {
			return;
		}

		const directionOffset = deltaX < 0 ? 1 : -1;
		const resistance = canNavigateOffset(directionOffset) ? dragPreviewFactor : blockedDragPreviewFactor;
		const previewOffset = Math.sign(deltaX) * Math.min(Math.abs(deltaX) * resistance, maxDragPreviewPx);
		const previewOpacity = Math.max(0.92, 1 - Math.min(Math.abs(previewOffset) / 360, 0.08));

		clearSurfaceTimers();

		activeSurface.style.transition = 'none';
		activeSurface.style.transform = `translate3d(${Math.round(previewOffset)}px, 0, 0)`;
		activeSurface.style.opacity = String(previewOpacity);
		activeSurface.style.willChange = 'transform, opacity';
	};

	const navigateByOffset = async (offset) => {
		const nextLocation = getTargetLocationByOffset(offset);

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
			cleanupSurfaceStyles();
			resetGesture();
			return;
		}

		const touch = event.touches[0];

		startX = touch.clientX;
		startY = touch.clientY;
		isTracking = true;
	};

	const handleTouchMove = (event) => {
		if(! isTracking || event.touches.length !== 1) {
			return;
		}

		const touch = event.touches[0];
		const deltaX = touch.clientX - startX;
		const deltaY = touch.clientY - startY;
		const absoluteX = Math.abs(deltaX);
		const absoluteY = Math.abs(deltaY);

		lastDeltaX = deltaX;

		if(! lockedAxis) {
			if(absoluteX < dragActivationThreshold && absoluteY < dragActivationThreshold) {
				return;
			}

			if(absoluteX > (absoluteY * dominanceRatio)) {
				lockedAxis = 'x';
			}
			else if(absoluteY > absoluteX) {
				lockedAxis = 'y';
			}
		}

		if(lockedAxis !== 'x') {
			return;
		}

		if(absoluteY > verticalTolerance) {
			animateSurfaceTo(0);
			resetGesture();
			return;
		}

		event.preventDefault();
		applySurfaceDrag(deltaX);
	};

	const handleTouchCancel = () => {
		animateSurfaceTo(0);
		resetGesture();
	};

	const handleTouchEnd = (event) => {
		if(! isTracking || event.changedTouches.length !== 1) {
			animateSurfaceTo(0);
			resetGesture();
			return;
		}

		const touch = event.changedTouches[0];
		const deltaX = lastDeltaX || (touch.clientX - startX);
		const deltaY = touch.clientY - startY;
		const absoluteX = Math.abs(deltaX);
		const absoluteY = Math.abs(deltaY);
		const offset = deltaX < 0 ? 1 : -1;
		const hasTarget = canNavigateOffset(offset);

		resetGesture();

		if(absoluteX < threshold || absoluteY > verticalTolerance || absoluteX < (absoluteY * dominanceRatio) || ! hasTarget) {
			animateSurfaceTo(0);
			return;
		}

		animateSurfaceTo(deltaX < 0 ? -maxDragPreviewPx : maxDragPreviewPx, {
			duration: dragNavigatePreviewMs,
			opacity: 0.96
		});

		navigationTimerId = window.setTimeout(() => {
			navigationTimerId = null;
			void navigateByOffset(offset);
		}, dragNavigateDelayMs);
	};

	const attachListeners = (surfaceNode = null) => {
		activeSurface = surfaceNode || unref(surfaceRef);

		if(! activeSurface) {
			return;
		}

		activeSurface.addEventListener('touchstart', handleTouchStart, { passive: true });
		activeSurface.addEventListener('touchmove', handleTouchMove, { passive: false });
		activeSurface.addEventListener('touchend', handleTouchEnd, { passive: true });
		activeSurface.addEventListener('touchcancel', handleTouchCancel, { passive: true });
	};

	const detachListeners = () => {
		if(! activeSurface) {
			return;
		}

		activeSurface.removeEventListener('touchstart', handleTouchStart);
		activeSurface.removeEventListener('touchmove', handleTouchMove);
		activeSurface.removeEventListener('touchend', handleTouchEnd);
		activeSurface.removeEventListener('touchcancel', handleTouchCancel);
		cleanupSurfaceStyles();
		activeSurface = null;
	};

	watch(() => unref(surfaceRef), (nextSurface, previousSurface) => {
		if(previousSurface && previousSurface !== nextSurface) {
			detachListeners();
		}

		if(nextSurface && nextSurface !== activeSurface) {
			attachListeners(nextSurface);
		}
	}, {
		immediate: true
	});

	onUnmounted(() => {
		clearSurfaceTimers();
		detachListeners();
	});
};
