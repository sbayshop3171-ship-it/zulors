import { defineStore } from 'pinia';

const useLightboxStore = defineStore('lightbox_store', {
    state: function() {
		return {
			albumData: null
		}
	},
    getters: {
        postData: function(state) {
            return this.albumData;
        }
    },
    actions: {
        add: function(albumData) {
            this.albumData = albumData;
        },
        remove: function() {
            this.albumData = null;
        }
    }
});

export { useLightboxStore };