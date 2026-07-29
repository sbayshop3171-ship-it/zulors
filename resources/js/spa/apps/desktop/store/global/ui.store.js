import { defineStore } from 'pinia';

const useUIStore = defineStore('ui', {
    state: () => {
        return {
            cheatSheet: {
                isOpen: false
            }
        };
    },
    actions: {
        openCheatSheet: function() {
            this.cheatSheet.isOpen = true;
        },
        closeCheatSheet: function() {
            this.cheatSheet.isOpen = false;
        }
    }
});

export { useUIStore };