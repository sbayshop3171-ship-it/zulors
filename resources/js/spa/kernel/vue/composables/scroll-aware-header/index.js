import { computed, onMounted, onUnmounted, reactive, toValue, watch } from 'vue';
import { useRoute } from 'vue-router';

const defaultScrollAwareHeaderPolicy = Object.freeze({
    topThreshold: 24,
    hideAfter: 48,
    hideDistance: 48,
    revealDistance: 14,
    minDelta: 2,
    resetOnRouteChange: true
});

const clampScrollY = (scrollY) => {
    const numericScrollY = Number(scrollY);

    if(! Number.isFinite(numericScrollY)) {
        return 0;
    }

    return Math.max(0, numericScrollY);
};

const resolvePolicy = (policy = {}) => {
    return {
        ...defaultScrollAwareHeaderPolicy,
        ...(policy || {})
    };
};

const readWindowScrollY = () => {
    if(typeof window === 'undefined') {
        return 0;
    }

    return clampScrollY(
        window.scrollY
        ?? window.pageYOffset
        ?? document.documentElement?.scrollTop
        ?? document.body?.scrollTop
        ?? 0
    );
};

const normalizeState = (state, scrollY = 0) => {
    const safeScrollY = clampScrollY(scrollY);

    if(! state) {
        return {
            isVisible: true,
            lastScrollY: safeScrollY,
            anchorScrollY: safeScrollY
        };
    }

    return {
        isVisible: state.isVisible !== false,
        lastScrollY: clampScrollY(state.lastScrollY),
        anchorScrollY: clampScrollY(state.anchorScrollY)
    };
};

function createScrollAwareHeaderState(scrollY = 0) {
    return normalizeState(null, scrollY);
}

function resetScrollAwareHeaderState(scrollY = 0) {
    return createScrollAwareHeaderState(scrollY);
}

function resolveScrollAwareHeaderState(previousState, scrollY = 0, policy = {}, options = {}) {
    const resolvedPolicy = resolvePolicy(policy);
    const safeScrollY = clampScrollY(scrollY);
    const normalizedState = normalizeState(previousState, safeScrollY);
    const nextState = {
        ...normalizedState,
        lastScrollY: safeScrollY
    };

    if(options.forceVisible || options.disabled) {
        nextState.isVisible = true;
        nextState.anchorScrollY = safeScrollY;

        return nextState;
    }

    if(safeScrollY <= resolvedPolicy.topThreshold) {
        nextState.isVisible = true;
        nextState.anchorScrollY = safeScrollY;

        return nextState;
    }

    const delta = safeScrollY - normalizedState.lastScrollY;

    if(Math.abs(delta) < resolvedPolicy.minDelta) {
        if(normalizedState.isVisible && delta < 0) {
            nextState.anchorScrollY = safeScrollY;
        }
        else if(! normalizedState.isVisible && delta > 0) {
            nextState.anchorScrollY = safeScrollY;
        }

        return nextState;
    }

    if(normalizedState.isVisible) {
        if(delta < 0) {
            nextState.anchorScrollY = safeScrollY;

            return nextState;
        }

        const distanceFromAnchor = safeScrollY - normalizedState.anchorScrollY;

        if(safeScrollY >= resolvedPolicy.hideAfter && distanceFromAnchor >= resolvedPolicy.hideDistance) {
            nextState.isVisible = false;
            nextState.anchorScrollY = safeScrollY;
        }

        return nextState;
    }

    if(delta > 0) {
        nextState.anchorScrollY = safeScrollY;

        return nextState;
    }

    const revealedDistance = normalizedState.anchorScrollY - safeScrollY;

    if(revealedDistance >= resolvedPolicy.revealDistance) {
        nextState.isVisible = true;
        nextState.anchorScrollY = safeScrollY;
    }

    return nextState;
}

function useScrollAwareHeader(options = {}) {
    const route = useRoute();
    const state = reactive(createScrollAwareHeaderState());
    let routeResetHandle = null;

    const readPolicy = () => {
        return resolvePolicy(toValue(options.policy));
    };

    const isDisabled = () => {
        return Boolean(toValue(options.disabled));
    };

    const applyState = (nextState) => {
        state.isVisible = nextState.isVisible;
        state.lastScrollY = nextState.lastScrollY;
        state.anchorScrollY = nextState.anchorScrollY;
    };

    const reset = (scrollY = readWindowScrollY()) => {
        applyState(resetScrollAwareHeaderState(scrollY));
    };

    const sync = (scrollY = readWindowScrollY()) => {
        applyState(resolveScrollAwareHeaderState(state, scrollY, readPolicy(), {
            disabled: isDisabled()
        }));
    };

    const queueRouteReset = () => {
        if(typeof window === 'undefined') {
            reset(0);

            return;
        }

        if(routeResetHandle) {
            window.cancelAnimationFrame(routeResetHandle);
        }

        routeResetHandle = window.requestAnimationFrame(() => {
            routeResetHandle = window.requestAnimationFrame(() => {
                routeResetHandle = null;
                reset(readWindowScrollY());
            });
        });
    };

    const handleScroll = () => {
        sync(readWindowScrollY());
    };

    onMounted(() => {
        reset(readWindowScrollY());
        window.addEventListener('scroll', handleScroll, { passive: true });
    });

    onUnmounted(() => {
        if(typeof window !== 'undefined') {
            window.removeEventListener('scroll', handleScroll);

            if(routeResetHandle) {
                window.cancelAnimationFrame(routeResetHandle);
                routeResetHandle = null;
            }
        }
    });

    watch(() => {
        return isDisabled();
    }, (disabled) => {
        if(disabled) {
            reset(readWindowScrollY());
        }
    }, {
        immediate: true
    });

    watch(() => {
        return route.fullPath;
    }, () => {
        if(readPolicy().resetOnRouteChange) {
            queueRouteReset();
        }
    });

    return {
        isVisible: computed(() => {
            return state.isVisible;
        }),
        isHidden: computed(() => {
            return ! state.isVisible;
        }),
        hiddenClass: computed(() => {
            return state.isVisible ? '' : '-translate-y-full';
        }),
        reset: reset,
        sync: sync
    };
}

export {
    createScrollAwareHeaderState,
    defaultScrollAwareHeaderPolicy,
    resolveScrollAwareHeaderState,
    resetScrollAwareHeaderState,
    useScrollAwareHeader
};
