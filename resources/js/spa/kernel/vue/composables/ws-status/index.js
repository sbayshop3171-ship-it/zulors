import { ref, onMounted, onUnmounted } from 'vue';

const useWSConnectionStatus = function() {
    const isWSEstablished = ref(Boolean(window.ColibriBRState?.connected ?? window.ColibriBRConnected));
    const wsState = ref(window.ColibriBRState ?? null);

    const updateStatus = (event) => {
        isWSEstablished.value = Boolean(event.detail?.connected);
        wsState.value = event.detail ?? null;
    };

    onMounted(() => {
        window.addEventListener('colibri:ws-status', updateStatus);
    });

    onUnmounted(() => {
        window.removeEventListener('colibri:ws-status', updateStatus);
    });

    return {
        isWSEstablished: isWSEstablished,
        wsState: wsState
    };
};

export { useWSConnectionStatus };
