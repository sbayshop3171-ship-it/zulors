import { defineStore } from 'pinia';

const useLightboxStore = defineStore('mobile_lightbox_store', {
    state: function() {
		return {
			albumData: null
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