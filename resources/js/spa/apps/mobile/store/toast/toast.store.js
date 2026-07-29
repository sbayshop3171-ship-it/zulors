import { defineStore } from 'pinia';

const useToastNotificationsStore = defineStore('toast_notifications_store', {
    state: function() {
		return {
			toastNotifications: []
		}
	},
    getters: {
        notificationsList: function(state) {
            return this.toastNotifications;
        }
    },
    actions: {
        add: function(content, duration = 5000, type = 'success') {
            const id = Date.now();

            this.toastNotifications.push({
                id: id,
                text: content,
                duration: duration,
                type: type
            });

            setTimeout(() => {
                this.remove(id);
            }, duration);

            return this.toastNotifications;
        },
        remove: function(toastId) {
            this.toastNotifications = this.toastNotifications.filter((msg) => {
                return msg.id !== toastId;
            });
        }
    }
});

export default useToastNotificationsStore;