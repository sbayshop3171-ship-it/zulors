import { defineStore } from 'pinia';

const useProcessStore = defineStore('processes_store', {
    state: function() {
		return {
			currentProcesses: []
		}
	},
    getters: {
        processesList: function(state) {
            return this.currentProcesses;
        }
    },
    actions: {
        add: function(content) {
            this.currentProcesses.push(content);

            return this.currentProcesses;
        },
        remove: function(processId) {
            // Add logic later
        }
    }
});

export default useProcessStore;