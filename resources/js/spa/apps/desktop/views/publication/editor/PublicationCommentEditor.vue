<template>
	<div class="px-4 py-4 bg-bg-pr">
		<form v-on:submit.prevent="submitComment">
			<div v-if="editingComment" class="text-par-s text-lab-sc mb-2 flex justify-between">Editing comment <button type="button" v-on:click="cancelEdit">Cancel</button></div>
			<div v-if="repliedComment" class="pb-3 flex items-center gap-3">
				<div class="size-small-avatar shrink-0 bg-brand-900 rounded-full inline-flex-center"><SvgIcon name="share-06" type="line" classes="size-icon-small text-white"></SvgIcon></div>
				<div class="flex-1 overflow-hidden"><div class="text-par-n font-semibold">{{ repliedComment.relations.user.name }}</div><p class="text-par-s truncate">{{ repliedComment.content }}</p></div>
				<button type="button" class="text-lab-sc" v-on:click="cancelCommentReply">×</button>
			</div>
			<div v-if="media.selections.value.length || pendingPreview" class="relative mb-3 w-28 h-28">
				<img v-bind:src="pendingPreview || media.selections.value[0]?.preview_url" class="w-full h-full object-cover rounded-lg">
				<button v-if="!pendingUid" type="button" class="absolute -top-2 -right-2 bg-bg-pr rounded-full shadow px-2" v-on:click="removeSelectedPhoto">×</button>
				<div v-if="pendingUid" class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-xs p-1 rounded-b-lg">{{ pendingRow?.status }} {{ pendingRow?.progress || 0 }}%</div>
			</div>
			<div v-if="pendingUid && ['failed','waiting','auth_required','expired'].includes(pendingRow?.status)" class="flex gap-4 text-par-s mb-2">
				<button type="button" class="text-brand-900" v-on:click="retryUpload">Retry upload</button>
				<button type="button" class="text-red-600" v-on:click="cancelUpload">Cancel upload</button>
				<span class="text-red-600 truncate">{{ pendingRow?.error }}</span>
			</div>
			<label v-if="editingComment?.media?.length" class="text-par-s text-lab-sc flex items-center gap-2 mb-2"><input type="checkbox" v-model="removeExistingPhoto"> Remove current photo</label>
			<div class="flex gap-2.5 items-end relative">
				<div class="shrink-0"><AvatarSmall v-bind:avatarSrc="userData.avatar_url"></AvatarSmall></div>
				<div class="flex-1">
					<textarea ref="commentTextInputField" v-model="commentInputText" maxlength="2200" v-on:focus="commentFocusHandler" v-on:blur="commentBlurHandler" v-on:input="commentInputHandler"
						v-bind:placeholder="$t('labels.enter_comment_placeholder') + '...'" v-bind:disabled="Boolean(pendingUid)"
						class="bg-transparent pt-2 outline-hidden w-full min-h-8 resize-none text-lab-pr text-par-l leading-tight placeholder:text-par-l"></textarea>
					<div class="text-cap-s text-lab-sc text-right">{{ commentInputText.length }}/2200</div>
				</div>
				<input ref="photoInput" class="hidden" type="file" accept="image/jpeg,image/png,image/webp" v-on:change="selectPhoto">
				<div class="ml-auto shrink-0 inline-flex items-center gap-1">
					<div class="relative">
						<PrimaryIconButton iconSize="icon-normal" v-on:click.stop="state.isEmojisPickerOpen = true" iconType="line" buttonColor="text-brand-900" iconName="face-smile"></PrimaryIconButton>
						<div v-if="state.isEmojisPickerOpen" class="absolute top-6 right-0 w-80 z-50"><EmojisPicker v-on:pick="insertCommentEmoji" v-on:close="state.isEmojisPickerOpen = false"></EmojisPicker></div>
					</div>
					<PrimaryIconButton iconSize="icon-normal" v-on:click="photoInput?.click()" iconType="line" buttonColor="text-lab-sc" iconName="image-01"></PrimaryIconButton>
					<PrimaryIconButton buttonType="submit" iconSize="icon-normal" iconType="solid" buttonColor="text-brand-900" iconName="send-03"
						v-bind:disabled="submitButtonState"></PrimaryIconButton>
				</div>
			</div>
		</form>
	</div>
</template>

<script>
	import { defineComponent, ref, defineAsyncComponent, computed, reactive, onMounted, onUnmounted, watch } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMediaPublication } from '@/kernel/vue/composables/media-publication/index.js';
	import { publicationManager, publicationRows } from '@/kernel/services/media/publications/index.js';
	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		props: { postId: { type: Number, default: 0 } },
		emits: ['add', 'edited', 'uploaded'],
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
			const state = reactive({ isSubmitting: false, isEmojisPickerOpen: false, commentInputFocused: false });
			const { autoResize, insertSymbolAtCaret } = useInputHandlers();
			const commentTextInputField = ref(null);
			const pendingRow = computed(() => publicationRows.value.find(row => row.client_uid === pendingUid.value));
			let handledPublishedUid = null;
			const clearPendingPreview = () => { if (pendingPreview.value?.startsWith('blob:')) URL.revokeObjectURL(pendingPreview.value); pendingPreview.value = ''; };
			const finishComposer = () => { commentInputText.value = ''; repliedComment.value = null; editingComment.value = null; removeExistingPhoto.value = false; media.clear(); autoResize(commentTextInputField.value); };
			watch(() => pendingRow.value?.status, status => {
				if (status === 'published' && pendingUid.value && handledPublishedUid !== pendingUid.value) {
					handledPublishedUid = pendingUid.value; context.emit('uploaded'); pendingUid.value = null; clearPendingPreview(); finishComposer();
				}
			});
			const onReply = event => { if (!pendingUid.value) { repliedComment.value = event.commentData; editingComment.value = null; } };
			const decode = value => { const node = document.createElement('textarea'); node.innerHTML = value || ''; return node.value; };
			const onEdit = event => { if (pendingUid.value) return; editingComment.value = event.commentData; commentInputText.value = decode(event.commentData.content); repliedComment.value = null; removeExistingPhoto.value = false; };
			onMounted(() => { colibriEventBus.on('publication-comment:reply', onReply); colibriEventBus.on('publication-comment:edit', onEdit); });
			onUnmounted(() => { colibriEventBus.off('publication-comment:reply', onReply); colibriEventBus.off('publication-comment:edit', onEdit); clearPendingPreview(); });
			return {
				state, userData, commentInputText, repliedComment, editingComment, removeExistingPhoto, pendingUid, pendingPreview, pendingRow, media, photoInput, commentTextInputField,
				commentInputHandler: () => autoResize(commentTextInputField.value),
				commentFocusHandler: () => { state.commentInputFocused = true; },
				commentBlurHandler: () => { state.commentInputFocused = false; },
				insertCommentEmoji: symbol => { commentInputText.value = insertSymbolAtCaret(commentTextInputField.value, symbol); commentTextInputField.value.focus(); },
				selectPhoto: async event => { const file = event.target.files?.[0]; event.target.value = ''; if (!file) return; try { if (!await media.select(file)) throw new Error('Photo comments are not available right now.'); } catch (error) { toastError(error.message); } },
				removeSelectedPhoto: () => media.remove(media.selections.value[0]),
				cancelCommentReply: () => { repliedComment.value = null; },
				cancelEdit: () => { editingComment.value = null; commentInputText.value = ''; removeExistingPhoto.value = false; },
				retryUpload: () => publicationManager.retry(pendingRow.value).catch(error => toastError(error.message)),
				cancelUpload: async () => { try {
					await publicationManager.cancel(pendingRow.value);
					if (media.selections.value[0]?.native_file_id) { media.remove(media.selections.value[0]); toastError('Upload cancelled. Select the photo again to retry.'); }
					media.newClientUid(); pendingUid.value = null; clearPendingPreview();
				} catch (error) { toastError(error.message); } },
				submitButtonState: computed(() => state.isSubmitting || Boolean(pendingUid.value) || (!commentInputText.value.trim().length && !media.selections.value.length && !removeExistingPhoto.value)),
				submitComment: async () => {
					if (state.isSubmitting || pendingUid.value) return;
					const content = commentInputText.value.trim(); const selection = media.selections.value[0];
					if (!content && !selection && !removeExistingPhoto.value) return;
					state.isSubmitting = true;
					try {
						if (selection) {
							pendingPreview.value = selection.native_file_id ? selection.preview_url : URL.createObjectURL(selection.file);
							const row = await media.enqueue({ post_id: props.postId, content, parent_id: repliedComment.value?.id || null, comment_id: editingComment.value?.id || null, comment_mode: editingComment.value ? 'edit' : 'create' });
							pendingUid.value = row.client_uid;
						} else if (editingComment.value) {
							const response = await colibriAPI().userTimeline().with({ id: editingComment.value.id, content, remove_media: removeExistingPhoto.value }).putTo('post/comment/update');
							context.emit('edited', response.data.data.comment); finishComposer();
						} else {
							const response = await colibriAPI().userTimeline().with({ post_id: props.postId, content, parent_id: repliedComment.value?.id || null }).sendTo('post/comment/create');
							context.emit('add', response.data.data); finishComposer();
						}
					} catch (error) { toastError(error.response?.data?.message || error.message); clearPendingPreview(); }
					finally { state.isSubmitting = false; }
				}
			};
		},
		components: { AvatarSmall, PrimaryIconButton, EmojisPicker: defineAsyncComponent(() => import('@D/components/emojis/EmojisPicker.vue')) }
	});
</script>
