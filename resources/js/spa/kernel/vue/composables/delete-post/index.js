import { colibriEventBus } from '@/kernel/events/bus/index.js';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useI18n } from 'vue-i18n';

function useDeletePost() {
    const { t } = useI18n();

	const postDeleter = (postData, callback = null) => {
		colibriEventBus.emit('confirmation-modal:open', {
			title: t('prompt.delete_post.title'),
			description: t('prompt.delete_post.description'),
			closeOnConfirm: true,
			onConfirm: () => {
				const postId = postData.id;
				const rollback = optimisticallyDeletePost(postData, callback);

				colibriAPI().userTimeline().with({
					id: postId
				}).delete('post/delete').then(() => {
					colibriEventBus.emit('timeline:post-delete-confirmed', postId);
				}).catch((error) => {
					if(isMissingPostDelete(error)) {
						colibriEventBus.emit('timeline:post-delete-confirmed', postId);

						return false;
					}

					console.error('Unable to delete post', error);

					if (typeof rollback === 'function') {
						rollback(error);
					}

					colibriEventBus.emit('timeline:post-delete-failed', {
						postId: postId,
						postData: postData
					});

					toastError(error?.response?.data?.message || 'Unable to delete post. Please try again.');
				});

				return postId;
			}
		});
	}

	return {
		postDeleter: postDeleter
	}
}

function optimisticallyDeletePost(postData, callback = null) {
	const postId = postData.id;

	colibriEventBus.emit('timeline:post-deleted', postId);

	if (typeof callback === 'function') {
		return callback(postId, postData);
	}

	return null;
}

function isMissingPostDelete(error) {
	const status = Number(error?.response?.status || 0);
	const message = String(error?.response?.data?.message || '');

	return status === 404 && message.includes('No query results for model [App\\Models\\Post]');
}

export { useDeletePost };
