import { ref, onMounted, onUnmounted } from 'vue';

export function useIntersectionObserver(options = {}) {
	const isIntersecting = ref(false);
	const intersectionRatio = ref(0);
	const observerEntry = ref(null);
	const videoPlayerRef = options.targetRef || ref(null);
	let observer = null;

	const callback = (entries) => {
		entries.forEach((entry) => {
			isIntersecting.value = entry.isIntersecting;
			intersectionRatio.value = entry.intersectionRatio || 0;
			observerEntry.value = entry;
		});
	}

	onMounted(() => {
		if(typeof IntersectionObserver === 'undefined') {
			isIntersecting.value = true;
			intersectionRatio.value = 1;
			return;
		}

		observer = new IntersectionObserver(callback, {
			threshold: options.threshold ?? 0.5,
			rootMargin: options.rootMargin || '0px',
			root: options.root || null
		});
		
		if (videoPlayerRef.value) {
			observer.observe(videoPlayerRef.value);
		}
	});

	onUnmounted(() => {
		if (observer) {
			observer.disconnect();
		}
	});

	return { isIntersecting, intersectionRatio, observerEntry, videoPlayerRef };
}
