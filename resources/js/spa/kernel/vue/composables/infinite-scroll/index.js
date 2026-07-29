import { onMounted, onUnmounted } from 'vue';

export function useInfiniteScroll(options = {}) {
    const { pageOffset = 200, callback } = options;

    async function handleScroll() {
        const windowHeight = window.innerHeight;
        const scrollPosition = window.scrollY;
        const documentHeight = document.body.offsetHeight;

        if (windowHeight + scrollPosition >= documentHeight - pageOffset) {
            try {
                await callback();
            } catch (error) {
                console.error('Error in infinite scroll callback: ', error);
            }
        }
    }

    function addScrollEventListener() {
        window.addEventListener('scroll', handleScroll, { passive: true });
    }

    function removeScrollEventListener() {
        window.removeEventListener('scroll', handleScroll);
    }

    onMounted(() => {
        addScrollEventListener();
    });

    onUnmounted(() => {
        removeScrollEventListener();
    });
}