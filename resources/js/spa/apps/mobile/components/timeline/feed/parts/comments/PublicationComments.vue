<template>
	<ActionSheet v-on:close="$emit('close')">
		<div class="h-full flex flex-col">
			<div class="px-4 pb-4 border-b border-b-bord-pr text-center">
				<SheetTitle v-bind:title="$t('labels.comments')"></SheetTitle>
			</div>
			<template v-if="state.isLoading">
				<div class="flex justify-center py-24">
					<div class="colibri-primary-animation"></div>
				</div>
			</template>
			<template v-else>
				<div class="flex-1 overflow-y-auto">
					<div v-if="postComments.length">
						<div v-for="(commentItem, idx) in postComments">
							<Comment v-on:delete="handleCommentDelete"
								v-bind:key="commentItem.id"
							v-bind:commentData="commentItem"></Comment>
						</div>
						<template v-if="state.isLoadingComments">
							<div class="flex justify-center py-4">
								<div class="colibri-primary-animation"></div>
							</div>
						</template>
						<template v-else>
							<LoadmoreButton v-if="! state.noMoreComments" v-on:click="handleLoadMoreComments"></LoadmoreButton>
						</template>
					</div>
					<div v-else class="py-42">
						<TimelineEmptyState v-bind:desc="$t('empty_state.comments.desc')"></TimelineEmptyState>
					</div>
				</div>
	
				<div class="shrink-0 border-t border-t-bord-pr">
					<CommentEditor v-on:created="handleCommentCreate" v-bind:postId="postData.id"></CommentEditor>
				</div>
			</template>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, reactive, ref, computed, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useTimelineStore } from '@M/store/timeline/timeline.store.js';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
	import TimelineEmptyState from '@M/components/timeline/state/TimelineEmptyState.vue';
	import Comment from '@M/components/timeline/feed/parts/comments/parts/Comment.vue';
	import CommentEditor from '@M/components/timeline/feed/parts/comments/editor/CommentEditor.vue';
	import LoadmoreButton from '@M/components/inter-ui/buttons/LoadmoreButton.vue';

	export default defineComponent({
		emits: ['close'],
		props: {
			postData: {
				type: Object,
				required: true,
			},
		},
		setup: (props) => {
			const postData = computed(() => {
				return props.postData;
			});

			const timelineStore = useTimelineStore();
			const state = reactive({
				isLoading: true,
                isLoadingComments: false,
                noMoreComments: false
			});

			const postComments = ref([]);

			const fetchComments = async () => {
                if (! state.noMoreComments && ! state.isLoadingComments) {
                	let cursorId = 0;

					if (postComments.value.length) {
						cursorId = postComments.value.at(-1).id;
					}
                    
					state.isLoadingComments = true;

					await colibriAPI().userTimeline().params({ 
						cursor: cursorId
					}).getFrom(`post/${postData.value.hash_id}/comments`).then(function(response) {
						let comments = response.data.data;

						if (comments.length) {
							postComments.value = postComments.value.concat(comments);
						}
						else {
							state.noMoreComments = true;
						}
					}).catch(function(error) {
						state.noMoreComments = true;
					});

					state.isLoadingComments = false;
                }
            }

			onMounted(async () => {
				await fetchComments();

				debounce(() => {
					state.isLoading = false;
				}, 100);
			});
			

			return {
				postComments: postComments,
				state: state,
				commentsCount: computed(() => {
					return postData.value.comments_count;
				}),
				handleCommentDelete: (commentId) => {
                    colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.delete_comment.title'),
                        description: __t('prompt.delete_comment.description'),
                        onConfirm: () => {
                            colibriAPI().userTimeline().with({
                                id: commentId
                            }).delete('post/comment/delete').then((response) => {

								timelineStore.updateCommentCount(postData.value.id, response.data.data.post.comments_count);

                                let commentIndex = postComments.value.findIndex((item) => {
                                    return item.id == commentId;
                                });

                                if(commentIndex !== -1) {
                                    postComments.value.splice(commentIndex, 1);
                                }

                                postComments.value.forEach(function(item) {
                                    if(item.parent_id && item.parent_id == commentId) {
                                        item.deleted = true;
                                    }
                                });

                                toastSuccess(__t('toast.media.comment_deleted'));
                            }).catch((error) => {
                                if (error.response) {
                                    toastError(error.response.data.message);
                                }
                            });
                        }
                    });
                },
				handleCommentCreate: (commentData) => {
                    postComments.value.unshift(commentData.comment);

                    postData.value.comments_count = commentData.post.comments_count;
                },
				handleLoadMoreComments: async () => {
					await fetchComments();
				}
			}
		},
		components: {
			ActionSheet: ActionSheet,
			Comment: Comment,
			SheetTitle: SheetTitle,
			TimelineEmptyState: TimelineEmptyState,
			CommentEditor: CommentEditor,
			LoadmoreButton: LoadmoreButton
		},
	});
</script>