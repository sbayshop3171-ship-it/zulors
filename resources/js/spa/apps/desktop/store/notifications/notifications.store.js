import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useNotificationsStore = defineStore('notifications_store', {
    state: function() {
		return {
			isOpen: false,
			unreadCount: {
				formatted: 0,
				raw: 0
			},
			notifications: []
		}
	},
    actions: {
		openNotifications: function() {
			this.isOpen = true;
		},
		closeNotifications: function() {
			this.isOpen = false;
		},
		fetchNotifications: async function(type = 'all') {
			await colibriAPI().notifications().getFrom(type).then((response) => {
				this.notifications = response.data.data;
			}).catch(() => {
				this.notifications = [];
			});
		},
		fetchUnreadCount: function() {
			colibriAPI().notifications().getFrom('unread/count').then((response) => {
				this.unreadCount = response.data.data;
			}).catch(() => {
				this.unreadCount = {
					formatted: 0,
					raw: 0
				};
			});
		},
		deleteNotification: function(notificationId) {
			colibriAPI().notifications().with({
				notification_id: notificationId
			}).delete('delete');

			this.notifications = this.notifications.filter((notification) => notification.id !== notificationId);
		},
		setUnreadNotificationsCount: function(unreadCount) {
			this.unreadCount = unreadCount;
		}
    }
});

export { useNotificationsStore };