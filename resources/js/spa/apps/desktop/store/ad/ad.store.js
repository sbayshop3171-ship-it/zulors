import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useAdStore = defineStore('ad', {
    state: function() {
        return {
            ad: null
        };
    },
    actions: {
        fetchAd: async function() {
            await colibriAPI().ads().params({
				prev_ad_id: this.ad ? this.ad.id : null
			}).getFrom('ad').then((response) => {
                this.ad = response.data.data;
            }).catch((error) => {
                if(error.response) {
					this.ad = null;
                }
            });
        }
    }
});

export { useAdStore };