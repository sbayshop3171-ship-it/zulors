import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useGroupStore = defineStore('mobile_chats_group', {
	state: () => {
		return {
			groupData: null,
			groupParticipants: []
		};
	},
	actions: {
		fetchGroupData: async function(chatId) {
			await colibriAPI().messenger().getFrom(`groups/${chatId}/show`).then((response) => {
				this.groupData = response.data.data;
			}).catch((error) => {
				if(error.response) {
					this.groupData = null;
				}
			});
		},
		fetchGroupParticipants: async function() {
			await colibriAPI().messenger().getFrom(`groups/${this.groupData.chat_id}/participants`).then((response) => {
				this.groupParticipants = response.data.data;
			}).catch((error) => {
				if(error.response) {
					this.groupParticipants = [];
				}
			});
		}
	}
});

export { useGroupStore };