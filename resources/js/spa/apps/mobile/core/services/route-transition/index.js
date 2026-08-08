import { reactive, readonly } from 'vue';

import { mobileExploreSwipeSequence } from '@/kernel/vue/composables/swipe-route-navigation/index.js';

const routeTransitionState = reactive({
	name: 'mobile-route-fade'
});

const transitionSequences = [
	mobileExploreSwipeSequence
];

const routeMatchesSequenceItem = function(route, sequenceItem = {}) {
	if(! route?.name || ! sequenceItem?.name) {
		return false;
	}

	if(Array.isArray(sequenceItem.matchNames) && sequenceItem.matchNames.length) {
		return sequenceItem.matchNames.includes(route.name);
	}

	return route.name === sequenceItem.name;
};

const resolveSequenceIndex = function(route, sequence = []) {
	return sequence.findIndex((sequenceItem) => {
		return routeMatchesSequenceItem(route, sequenceItem);
	});
};

const resolveTransitionName = function(to, from, currentTransitionName) {
	if(! to?.name || ! from?.name || to.fullPath === from.fullPath) {
		return 'mobile-route-fade';
	}

	for(const sequence of transitionSequences) {
		const fromIndex = resolveSequenceIndex(from, sequence);
		const toIndex = resolveSequenceIndex(to, sequence);

		if(fromIndex === -1 || toIndex === -1) {
			continue;
		}

		if(toIndex > fromIndex) {
			return 'mobile-route-slide-next';
		}

		if(toIndex < fromIndex) {
			return 'mobile-route-slide-prev';
		}

		return currentTransitionName;
	}

	return 'mobile-route-fade';
};

export const syncMobileRouteTransition = function(to, from) {
	routeTransitionState.name = resolveTransitionName(to, from, routeTransitionState.name);
};

export const useMobileRouteTransition = function() {
	return readonly(routeTransitionState);
};
