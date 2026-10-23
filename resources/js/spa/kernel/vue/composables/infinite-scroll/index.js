import { onMounted, onUnmounted } from 'vue';

export function useInfiniteScroll(options = {}) {
    const { pageOffset = 200, callback } = options;
    let frameId = 0;
    let isLoading = false;

    function isNearPageEnd() {
        const windowHeight = window.innerHeight;
        const scrollPosition = window.scrollY;
        const documentHeight = Math.max(
            document.body?.scrollHeight || 0,
            document.documentElement?.scrollHeight || 0
        );

        return windowHeight + scrollPosition >= documentHeight - pageOffset;
    }

    function checkPageEnd() {
        frameId = 0;

        if (isLoading || typeof callback !== 'function' || !isNearPageEnd()) {
            return;
        }

        isLoading = true;

        Promise.resolve()
            .then(() => callback())
            .catch((error) => {
                console.error('Error in infinite scroll callback: ', error);
            })
            .finally(() => {
                isLoading = false;
            });
    }

    function schedulePageEndCheck() {
        if (frameId || typeof window.requestAnimationFrame !== 'function') {
            return;
        }

        frameId = window.requestAnimationFrame(checkPageEnd);
    }

    function removeScrollListeners() {
        window.removeEventListener('scroll', schedulePageEndCheck);
        window.removeEventListener('resize', schedulePageEndCheck);

        if (frameId && typeof window.cancelAnimationFrame === 'function') {
            window.cancelAnimationFrame(frameId);
            frameId = 0;
        }
    }

    onMounted(() => {
        window.addEventListener('scroll', schedulePageEndCheck, { passive: true });
        window.addEventListener('resize', schedulePageEndCheck, { passive: true });
        schedulePageEndCheck();
    });

    onUnmounted(() => {
        removeScrollListeners();
    });
}
