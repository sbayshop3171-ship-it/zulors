import { onMounted, onUnmounted, ref, watch } from 'vue';

export function useNearViewport(options = {}) {
	const targetRef = ref(null);
	const isNearViewport = ref(false);
	let observer = null;
	let observedTarget = null;

	const observeTarget = (target = targetRef.value) => {
		if(observer && observedTarget === target) {
			return;
		}

		observer?.disconnect();
		observer = null;
		observedTarget = target || null;
		isNearViewport.value = false;

		if(! target) {
			return;
		}

		if(typeof IntersectionObserver === 'undefined') {
			isNearViewport.value = true;
			return;
		}

		observer = new IntersectionObserver((entries) => {
			const entry = entries.find((item) => item.target === target);
			if(entry) {
				isNearViewport.value = entry.isIntersecting;
			}
		}, {
			root: options.root || null,
			rootMargin: options.rootMargin || '500px 0px',
			threshold: 0
		});

		observer.observe(target);
	};

	onMounted(() => {
		observeTarget();
	});
	watch(targetRef, (target) => observeTarget(target), { flush: 'post' });

	onUnmounted(() => {
		observer?.disconnect();
		observer = null;
		observedTarget = null;
	});

	return {
		targetRef,
		isNearViewport
	};
}
