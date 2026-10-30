import { onBeforeUnmount, onMounted, ref, unref } from 'vue';

export function useMediaPreviewBounds(element, reservedViewportHeight = 340) {
    const bounds = ref({ width: 0, height: 0 });
    let observer = null;
    let observedElement = null;

    const refresh = () => {
        const value = unref(element);
        const root = Array.isArray(value) ? value[0] : value;
        const container = root?.parentElement;
        if (!container || typeof window === 'undefined') return;
        const viewportHeight = Math.max(160, window.innerHeight - reservedViewportHeight);
        let containerMaxHeight = Number.POSITIVE_INFINITY;
        if(typeof window.getComputedStyle === 'function') {
            for(let ancestor = container; ancestor; ancestor = ancestor.parentElement) {
                const maxHeight = Number.parseFloat(window.getComputedStyle(ancestor).maxHeight);
                if(Number.isFinite(maxHeight) && maxHeight > 0) containerMaxHeight = Math.min(containerMaxHeight, maxHeight);
            }
        }
        bounds.value = {
            width: Math.max(1, container.clientWidth),
            height: Number.isFinite(containerMaxHeight)
                ? Math.min(viewportHeight, containerMaxHeight)
                : viewportHeight,
        };
    };

    onMounted(() => {
        refresh();
        const value = unref(element);
        observedElement = (Array.isArray(value) ? value[0] : value)?.parentElement || null;
        if (observedElement && typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(refresh);
            observer.observe(observedElement);
        }
        window.addEventListener('resize', refresh, { passive: true });
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        observer = null;
        window.removeEventListener('resize', refresh);
    });

    return { bounds, refresh };
}
