import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useReportStore = defineStore('report_store', {
    state: function() {
		return {
			reportReasons: {
				post: null,
				user: null
			}
		}
	},
    actions: {
		fetchReportReasons: async function(type) {
			await colibriAPI().feedback().params({
				type: type
			}).getFrom('report/reasons').then((response) => {
				this.reportReasons[type] = response.data.data;
			}).catch((error) => {
				if(error.response) {
					alert(error.response.data.message);
				}
			});
		},
		sendReport: function(reportData) {
			colibriAPI().feedback().with(reportData).sendTo('report/send').then((response) => {
				
			}).catch((error) => {
				if(error.response) {
					alert(error.response.data.message);
				}
			});
		}
    }
});

export { useReportStore };