import { ref, onMounted, onUnmounted } from 'vue';

const useWSConnectionStatus = function() {
    const isWSEstablished = ref(window.ColibriBRConnected !== false);

    const updateStatus = (event) => {
        isWSEstablished.value = event.detail.connected !== false;
    };

    onMounted(() => {
        window.addEventListener('colibri:ws-status', updateStatus);
    });

    onUnmounted(() => {
        window.removeEventListener('colibri:ws-status', updateStatus);
    });

    return {
        isWSEstablished: isWSEstablished
    };
};

export { useWSConnectionStatus };
