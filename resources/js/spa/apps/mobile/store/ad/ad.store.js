import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useAdStore = defineStore('mobile_ad_store', {
    actions: {
        fetchAd: async function(placement = 'sidebar') {
            return await colibriAPI().ads().params({
				placement,
				prev_ad_id: this.ad ? this.ad.id : null
			}).getFrom('ad').then((response) => {
                return response.data.data;
            }).catch((error) => {
                return null;
            });
        }
    }
});

export { useAdStore };
