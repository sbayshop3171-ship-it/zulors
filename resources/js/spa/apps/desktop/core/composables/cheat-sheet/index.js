import { useUIStore } from '@D/store/global/ui.store.js';
import { computed } from 'vue';

function useCheatSheet() {

    const uiStore = useUIStore();

    const openCheatSheetPanel = () => {
        uiStore.openCheatSheet();
    };

    const closeCheatSheetPanel = () => {
        uiStore.closeCheatSheet();
    };

    const isOpen = computed(() => {
        return uiStore.cheatSheet.isOpen;
    });

    return {
        openCheatSheetPanel: openCheatSheetPanel,
        closeCheatSheetPanel: closeCheatSheetPanel,
        isOpen: isOpen
    };
}

export { useCheatSheet };