<template>
	<form class="px-4 pt-3 pb-2" v-on:submit.prevent="submitComment">
		<div v-if="editingComment" class="text-par-s text-lab-sc mb-2 flex items-center justify-between">Editing comment <button type="button" v-on:click="cancelEdit">Cancel</button></div>
		<ReplyPreview v-if="repliedComment" v-bind:repliedComment="repliedComment" v-on:cancel="cancelCommentReply"></ReplyPreview>
		<div v-if="media.selections.value.length || pendingPreview" class="relative my-2 w-24 h-24">
			<img v-bind:src="pendingPreview || media.selections.value[0]?.preview_url" class="w-full h-full object-cover rounded-lg">
			<button v-if="!pendingUid" type="button" class="absolute -top-2 -right-2 bg-bg-pr rounded-full shadow px-2" v-on:click="removeSelectedPhoto">×</button>
			<div v-if="pendingUid" class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-xs p-1 rounded-b-lg">{{ pendingRow?.status }} {{ pendingRow?.progress || 0 }}%</div>
		</div>
		<div v-if="pendingUid && ['failed','waiting','auth_required','expired'].includes(pendingRow?.status)" class="flex gap-4 text-par-s mb-2">
			<button type="button" class="text-brand-900" v-on:click="retryUpload">Retry upload</button>
			<button type="button" class="text-red-600" v-on:click="cancelUpload">Cancel upload</button>
			<span class="text-red-600 truncate">{{ pendingRow?.error }}</span>
		</div>
		<label v-if="editingComment?.media?.length" class="text-par-s text-lab-sc flex items-center gap-2 mb-2">
			<input type="checkbox" v-model="removeExistingPhoto"> Remove current photo
		</label>
		<div class="flex items-end gap-3">
			<div class="shrink-0"><AvatarExtraSmall v-bind:avatarSrc="userData.avatar_url"></AvatarExtraSmall></div>
			<div class="flex-1 overflow-hidden">
				<textarea ref="commentTextInputField" v-model="commentInputText" maxlength="2200" v-on:input="commentInputHandler"
					v-bind:placeholder="$t('labels.enter_comment_placeholder') + '...'"
					class="pt-1 leading-6 bg-transparent outline-hidden w-full min-h-10 resize-none text-lab-pr text-par-l placeholder:text-par-n"
					v-bind:disabled="Boolean(pendingUid)"></textarea>
				<div class="text-cap-s text-lab-sc text-right">{{ commentInputText.length }}/2200</div>
			</div>
			<input ref="photoInput" class="hidden" type="file" accept="image/jpeg,image/png,image/webp" v-on:change="selectPhoto">
			<PrimaryIconButton v-bind:disabled="state.isSubmitting || Boolean(pendingUid)" v-on:click="photoInput?.click()" iconName="image-01" iconSize="icon-normal" buttonColor="text-lab-sc"></PrimaryIconButton>
			<PrimaryIconButton v-bind:disabled="state.isSubmitting || Boolean(pendingUid) || (!commentInputText.trim().length && !media.selections.value.length && !removeExistingPhoto)" v-on:click="submitComment" iconName="send-03" iconSize="icon-normal" buttonColor="text-brand-900"></PrimaryIconButton>
		</div>
	</form>
</template>

<script>
	import { defineComponent, ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMediaPublication } from '@/kernel/vue/composables/media-publication/index.js';
	import { publicationManager, publicationRows } from '@/kernel/services/media/publications/index.js';
	import AvatarExtraSmall from '@M/components/general/avatars/AvatarExtraSmall.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ReplyPreview from '@M/components/timeline/feed/parts/comments/editor/ReplyPreview.vue';

	export default defineComponent({
		props: { postId: { type: Number, default: 0 } },
		emits: ['created', 'edited', 'uploaded'],
		setup(props, context) {
			const commentInputText = ref('');
			const userData = ref(useAuthStore().userData);
			const repliedComment = ref(null);
			const editingComment = ref(null);
			const removeExistingPhoto = ref(false);
			const pendingUid = ref(null);
			const pendingPreview = ref('');
			const photoInput = ref(null);
			const media = useMediaPublication('comment');
			const state = reactive({ isSubmitting: false });
			const { autoResize } = useInputHandlers();
			const commentTextInputField = ref(null);
			const pendingRow = computed(() => publicationRows.value.find(row => row.client_uid === pendingUid.value));
			let handledPublishedUid = null;
			const clearPendingPreview = () => { if (pendingPreview.value?.startsWith('blob:')) URL.revokeObjectURL(pendingPreview.value); pendingPreview.value = ''; };
			const finishComposer = () => {
				commentInputText.value = '';
				repliedComment.value = null;
				editingComment.value = null;
				removeExistingPhoto.value = false;
				media.clear();
				autoResize(commentTextInputField.value);
			};
			watch(() => pendingRow.value?.status, status => {
				if (status === 'published' && pendingUid.value && handledPublishedUid !== pendingUid.value) {
					handledPublishedUid = pendingUid.value;
					context.emit('uploaded');
					pendingUid.value = null;
					clearPendingPreview();
					finishComposer();
				}
			});
			const onReply = event => { if (!pendingUid.value) { repliedComment.value = event.commentData; editingComment.value = null; } };
			const decode = value => { const node = document.createElement('textarea'); node.innerHTML = value || ''; return node.value; };
			const onEdit = event => {
				if (pendingUid.value) return;
				editingComment.value = event.commentData;
				commentInputText.value = decode(event.commentData.content);
				repliedComment.value = null;
				removeExistingPhoto.value = false;
			};
			onMounted(() => {
				colibriEventBus.on('publication-comment:reply', onReply);
				colibriEventBus.on('publication-comment:edit', onEdit);
			});
			onUnmounted(() => {
				colibriEventBus.off('publication-comment:reply', onReply);
				colibriEventBus.off('publication-comment:edit', onEdit);
				clearPendingPreview();
			});
			return {
				state, userData, commentInputText, repliedComment, editingComment, removeExistingPhoto, photoInput, media, pendingUid, pendingPreview, pendingRow,
				commentInputHandler: () => autoResize(commentTextInputField.value),
				selectPhoto: async event => {
					const file = event.target.files?.[0]; event.target.value = '';
					if (!file) return;
					try {
						if (!await media.select(file)) throw new Error('Photo comments are not available right now.');
					}
					catch (error) { toastError(error.message); }
				},
				removeSelectedPhoto: () => media.remove(media.selections.value[0]),
				cancelEdit: () => { editingComment.value = null; commentInputText.value = ''; removeExistingPhoto.value = false; },
				cancelCommentReply: () => { repliedComment.value = null; },
				retryUpload: () => publicationManager.retry(pendingRow.value).catch(error => toastError(error.message)),
				cancelUpload: async () => {
					try {
						await publicationManager.cancel(pendingRow.value);
						if (media.selections.value[0]?.native_file_id) {
							media.remove(media.selections.value[0]);
							toastError('Upload cancelled. Select the photo again to retry.');
						}
						media.newClientUid();
						pendingUid.value = null;
						clearPendingPreview();
					}
					catch (error) { toastError(error.message); }
				},
				submitComment: async () => {
					if (state.isSubmitting || pendingUid.value) return;
					const content = commentInputText.value.trim();
					const selection = media.selections.value[0];
					if (!content && !selection && !removeExistingPhoto.value) return;
					state.isSubmitting = true;
					try {
						if (selection) {
							pendingPreview.value = selection.native_file_id ? selection.preview_url : URL.createObjectURL(selection.file);
							const row = await media.enqueue({ post_id: props.postId, content, parent_id: repliedComment.value?.id || null,
								comment_id: editingComment.value?.id || null, comment_mode: editingComment.value ? 'edit' : 'create' });
							pendingUid.value = row.client_uid;
						} else if (editingComment.value) {
							const response = await colibriAPI().userTimeline().with({ id: editingComment.value.id, content, remove_media: removeExistingPhoto.value }).putTo('post/comment/update');
							context.emit('edited', response.data.data.comment);
							finishComposer();
						} else {
						const response = await colibriAPI().userTimeline().with({ post_id: props.postId, content, parent_id: repliedComment.value?.id || null }).sendTo('post/comment/create');
							context.emit('created', response.data.data);
							finishComposer();
						}
					} catch (error) { toastError(error.response?.data?.message || error.message); clearPendingPreview(); }
					finally { state.isSubmitting = false; }
				}
			};
		},
		components: { AvatarExtraSmall, PrimaryIconButton, ReplyPreview }
	});
</script>
