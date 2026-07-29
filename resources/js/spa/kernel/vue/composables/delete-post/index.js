import { colibriEventBus } from '@/kernel/events/bus/index.js';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useI18n } from 'vue-i18n';

function useDeletePost() {
    const { t } = useI18n();

	const postDeleter = (postData, callback = null) => {
		colibriEventBus.emit('confirmation-modal:open', {
			title: t('prompt.delete_post.title'),
			description: t('prompt.delete_post.description'),
			onConfirm: () => {
				return colibriAPI().userTimeline().with({
					id: postData.id
				}).delete('post/delete').then(() => {

					// Call the callback if it is provided.
					if (typeof callback === 'function') {
						callback(postData.id);
					}

					return postData.id;
				}).catch((error) => {
					console.error('Unable to delete post', error);

					throw error;
				});
			}
		});
	}

	return {
		postDeleter: postDeleter
	}
}

export { useDeletePost };
