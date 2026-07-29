<template>
	<div class="px-4 py-4 bg-bg-pr">
		<form v-on:submit.prevent="submitComment">
			<div class="pb-4" v-if="repliedComment">
				<div class="flex overflow-hidden">
					<div class="size-small-avatar shrink-0">
						<div class="transform -scale-x-100 size-small-avatar bg-brand-900 inline-flex-center overflow-hidden rounded-full">
							<SvgIcon name="share-06" type="line" classes="size-icon-small text-white"></SvgIcon>
						</div>
					</div>
					<div class="flex-1 overflow-hidden mx-4">
						<div class="overflow-hidden">
							<h3 class="text-par-n font-semibold text-lab-pr2 leading-none mb-1">
								{{ repliedComment.relations.user.name }}

								<span class="text-par-s text-lab-sc leading-none ml-1 font-normal">
									{{ repliedComment.date.time_ago }}
								</span>
							</h3>
							<p class="text-par-s truncate text-lab-pr2">
								{{ repliedComment.content }}
							</p>
						</div>
					</div>
					<div class="shrink-0">
						<PrimaryIconButton v-on:click="cancelCommentReply" name="x-close"></PrimaryIconButton>
					</div>
				</div>
			</div>
			<div class="flex gap-2.5 items-end relative">
				<div class="shrink-0">
					<AvatarSmall v-bind:avatarSrc="userData.avatar_url"></AvatarSmall>
				</div>
				<div class="flex-1">
					<div class="block max-h-72 overflow-y-auto">
						<textarea
							ref="commentTextInputField"
							v-model.trim="commentInputText"
							v-on:focus="commentFocusHandler"
							v-on:blur="commentBlurHandler"
						v-on:input="commentInputHandler"
						v-bind:placeholder="$t('labels.enter_comment_placeholder') + '...'"
						class="bg-transparent pt-2 outline-hidden w-full h-8 min-h-8 resize-none text-lab-pr text-par-l leading-tight placeholder:text-par-l"></textarea>
					</div>
				</div>
				<div class="ml-auto shrink-0">
					<div class="inline-flex items-center gap-1 leading-none">
						<div class="relative">
							<PrimaryIconButton iconSize="icon-normal" v-on:click.stop="state.isEmojisPickerOpen = true" iconType="line" buttonColor="text-brand-900" iconName="face-smile"></PrimaryIconButton>
							<template v-if="state.isEmojisPickerOpen">
								<div class="block absolute top-6 right-0 w-80 z-50">
									<EmojisPicker 
										v-on:pick="insertCommentEmoji"
									v-on:close="state.isEmojisPickerOpen = false"></EmojisPicker>
								</div>
							</template>
						</div>
						<PrimaryIconButton
							hoverBg="hover:bg-transparent"
							hoverText="hover:text-brand-900"
							buttonType="submit"
							iconSize="icon-normal"
							iconType="solid"
							buttonColor="text-brand-900"
							iconName="send-03"
						v-bind:disabled="submitButtonState"></PrimaryIconButton>
					</div>
				</div>
			</div>
		</form>
	</div>
</template>

<script>
	import { defineComponent, ref, defineAsyncComponent, computed, reactive, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	
	export default defineComponent({
		props: {
			postId: {
				type: Number,
				default: 0
			}
		},
		emits: ['add'],
		setup: function(props, context) {
			const commentInputText = ref('');
			const authStore = useAuthStore();
            const userData = ref(authStore.userData);
			const postId = ref(props.postId);
			const repliedComment = ref(null);

			const state = reactive({
                isSubmitting: false,
                isEmojisPickerOpen: false,
                commentInputFocused: false
            });

			const { autoResize, insertSymbolAtCaret } = useInputHandlers();
			const commentTextInputField = ref(null);

            const commentInputHandler = function() {
                autoResize(commentTextInputField.value);
            }

			onMounted(() => {
				colibriEventBus.on('publication-comment:reply', (event) => {
					repliedComment.value = event.commentData;
				});
			});

			return {
				state: state,
				userData: userData,
				commentInputText: commentInputText,
				repliedComment: repliedComment,
				commentInputHandler: commentInputHandler,
				commentTextInputField: commentTextInputField,
				insertCommentEmoji: (emojiSymbol) => {
                    commentInputText.value = insertSymbolAtCaret(commentTextInputField.value, emojiSymbol);
                    commentTextInputField.value.focus();
                },
				submitButtonState: computed(() => {
					return state.isSubmitting || commentInputText.value.length === 0;
				}),
				submitComment: async () => {
                    if(commentInputText.value.length > 0) {
                        
                        state.isSubmitting = true;
                        let parentId = null;

                        if(repliedComment.value) {
                            parentId = repliedComment.value.id;
                        }

                        await colibriAPI().userTimeline().with({
                            post_id: postId.value,
                            content: commentInputText.value,
                            parent_id: parentId
                        }).sendTo('post/comment/create').then((response) => {
                            context.emit('add', response.data.data);
                            commentInputText.value = '';

                            autoResize(commentTextInputField.value);

                            repliedComment.value = null;
                        }).catch((error) => {
                            if (error.response) {
                                toastError(error.response.data.message);
                            }
                        });

                        state.isSubmitting = false;
                    }
                },
				commentFocusHandler: () => {
                    state.commentInputFocused = true;
                },
                commentBlurHandler: () => {
                    state.commentInputFocused = false;
                },
				cancelCommentReply: () => {
                    repliedComment.value = null;
                }
			};
		},
		components: {
			PrimaryPillButton: PrimaryPillButton,
			AvatarSmall: AvatarSmall,
			PrimaryIconButton: PrimaryIconButton,
			EmojisPicker: defineAsyncComponent(() => {
                return import('@D/components/emojis/EmojisPicker.vue');
            })
		}
	});
</script>