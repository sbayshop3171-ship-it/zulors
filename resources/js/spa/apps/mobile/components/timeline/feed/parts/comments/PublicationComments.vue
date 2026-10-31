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
				<div class="px-4 py-2 border-b border-bord-pr flex justify-end">
					<label class="text-par-s text-lab-sc flex items-center gap-2">Sort
						<select v-model="sort" class="bg-transparent text-lab-pr outline-hidden">
							<option value="relevant">Most relevant</option>
							<option value="newest">Newest</option>
						</select>
					</label>
				</div>
				<div class="flex-1 overflow-y-auto">
					<div v-if="postComments.length">
						<div v-for="commentItem in postComments" v-bind:key="commentItem.id" class="border-b border-bord-pr/60">
							<Comment v-on:delete="handleCommentDelete" v-bind:commentData="commentItem"></Comment>
							<div v-if="commentItem.reply_count > 0" class="pl-16 pb-2">
								<button class="text-par-s font-semibold text-lab-sc" v-on:click="toggleThread(commentItem)">
									{{ openThreads[commentItem.id] ? 'Hide replies' : `View ${commentItem.reply_count} ${commentItem.reply_count === 1 ? 'reply' : 'replies'}` }}
								</button>
							</div>
							<div v-if="openThreads[commentItem.id]" class="border-l border-bord-pr">
								<div v-for="reply in (threadReplies[commentItem.id] || [])" v-bind:key="reply.id">
									<Comment v-on:delete="handleCommentDelete" v-bind:commentData="reply"></Comment>
								</div>
								<div v-if="threadState[commentItem.id]?.loading" class="flex justify-center py-3"><div class="colibri-primary-animation"></div></div>
								<button v-else-if="threadState[commentItem.id]?.hasMore" class="px-3 pb-3 text-par-s font-semibold text-lab-sc" v-on:click="loadMoreReplies(commentItem)">Load more replies</button>
							</div>
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
				<CommentEditor v-on:created="handleCommentCreate" v-on:edited="refreshThreads" v-on:uploaded="refreshThreads" v-bind:postId="postData.id"></CommentEditor>
				</div>
			</template>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, reactive, ref, computed, onMounted, watch } from 'vue';
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
		emits: ['close', 'created'],
		props: {
			postData: {
				type: Object,
				required: true,
			},
		},
		setup: (props, context) => {
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
			const openThreads = reactive({});
			const threadReplies = reactive({});
			const threadState = reactive({});
			const sort = ref('relevant');

			const fetchComments = async (reset = false) => {
				if (state.isLoadingComments || (!reset && state.noMoreComments)) return;
				state.isLoadingComments = true;
				try {
					const cursor = reset ? 0 : (postComments.value.nextCursor || 0);
					const response = await colibriAPI().userTimeline().params({ cursor, sort: sort.value }).getFrom(`post/${postData.value.hash_id}/comment-threads`);
					const comments = response.data.data || [];
					postComments.value = reset ? comments : postComments.value.concat(comments);
					postComments.value.nextCursor = response.data.meta?.next_cursor;
					state.noMoreComments = !response.data.meta?.has_more;
					if (response.data.meta?.comments_count !== undefined) {
						postData.value.comments_count = { ...postData.value.comments_count, raw: response.data.meta.comments_count, formatted: response.data.meta.comments_count_formatted };
						timelineStore.updateCommentCount(postData.value.id, postData.value.comments_count);
					}
				} catch (error) {
					if (error.response) toastError(error.response.data.message);
				} finally { state.isLoadingComments = false; }
			}

			const fetchReplies = async (root, reset = false) => {
				const current = threadState[root.id] || (threadState[root.id] = { cursor: 0, hasMore: true, loading: false });
				if (current.loading || (!reset && !current.hasMore)) return;
				current.loading = true;
				try {
					const cursor = reset ? 0 : current.cursor;
					const response = await colibriAPI().userTimeline().params({ cursor }).getFrom(`post/${postData.value.hash_id}/comment-threads/${root.id}/replies`);
					const replies = response.data.data || [];
					threadReplies[root.id] = reset ? replies : (threadReplies[root.id] || []).concat(replies);
					current.cursor = response.data.meta?.next_cursor || current.cursor;
					current.hasMore = Boolean(response.data.meta?.has_more);
				} catch (error) { if (error.response) toastError(error.response.data.message); }
				finally { current.loading = false; }
			}

			const refreshThreads = async () => {
				await fetchComments(true);
				for (const root of postComments.value) if (openThreads[root.id]) await fetchReplies(root, true);
			}

			watch(sort, () => { state.noMoreComments = false; fetchComments(true); });

			onMounted(async () => {
				await fetchComments(true);

				debounce(() => {
					state.isLoading = false;
				}, 100);
			});
			

			return {
				postComments: postComments,
				state: state,
				sort,
				openThreads,
				threadReplies,
				threadState,
				toggleThread: async (root) => {
					openThreads[root.id] = !openThreads[root.id];
					if (openThreads[root.id] && !threadReplies[root.id]) await fetchReplies(root, true);
				},
				loadMoreReplies: (root) => fetchReplies(root),
				refreshThreads,
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
								postData.value.comments_count = response.data.data.post.comments_count;

								refreshThreads();

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
                    postData.value.comments_count = commentData.post.comments_count;
						if (commentData.comment.parent_id) {
							const root = postComments.value.find(item => item.id === commentData.comment.thread_root_id);
						if (root) {
							root.reply_count = Number(root.reply_count || 0) + 1;
							if (openThreads[root.id]) {
								threadReplies[root.id] = [...(threadReplies[root.id] || []), commentData.comment];
							}
						}
						} else refreshThreads();
                    context.emit('created');
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
