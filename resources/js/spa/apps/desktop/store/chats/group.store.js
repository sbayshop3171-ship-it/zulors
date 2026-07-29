import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useGroupStore = defineStore('chats_group', {
	state: () => {
		return {
			editGroupData: null,
			groupData: null,
			groupParticipants: [],
			groupRecentJoins: []
		};
	},
	actions: {
		fetchEditGroup: async function(chatId) {
			await colibriAPI().messenger().getFrom(`groups/${chatId}/edit`).then((response) => {
				this.editGroupData = response.data.data;
			}).catch((error) => {
				if(error.response) {
					this.editGroupData = null;
				}
			});
		},
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
		},
		fetchGroupRecentJoins: async function() {
			await colibriAPI().messenger().getFrom(`groups/${this.groupData.chat_id}/recent-joins`).then((response) => {
				this.groupRecentJoins = response.data.data;
			}).catch((error) => {
				if(error.response) {
					this.groupRecentJoins = [];
				}
			});
		}
	}
});

export { useGroupStore };