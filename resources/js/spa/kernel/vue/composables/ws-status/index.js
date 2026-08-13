import { ref, onMounted, onUnmounted } from 'vue';
import { isConnectionIssueState } from '@/kernel/websockets/brd/connection.js';

const useWSConnectionStatus = function() {
    const isWSEstablished = ref(Boolean(window.ColibriBRState?.connected ?? window.ColibriBRConnected));
    const wsState = ref(window.ColibriBRState ?? null);
    const hasWSIssue = ref(isConnectionIssueState(window.ColibriBRState?.current));

    const updateStatus = (event) => {
        isWSEstablished.value = Boolean(event.detail?.connected);
        wsState.value = event.detail ?? null;
        hasWSIssue.value = isConnectionIssueState(event.detail?.current);
    };

    onMounted(() => {
        window.addEventListener('colibri:ws-status', updateStatus);
    });

    onUnmounted(() => {
        window.removeEventListener('colibri:ws-status', updateStatus);
    });

    return {
        isWSEstablished: isWSEstablished,
        wsState: wsState,
        hasWSIssue: hasWSIssue
    };
};

export { useWSConnectionStatus };
