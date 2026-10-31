<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <PageHeader v-bind:hasBack="true" v-bind:titleText="$t('labels.publication_page_title')"></PageHeader>
                <Border></Border>
                <div v-if="state.isLoading" class="block">
                    <ProfileSidebarSkeleton></ProfileSidebarSkeleton>
                </div>
                <div v-else class="block">
                    <ProfileTimelineCard v-bind:profileData="postAuthor"></ProfileTimelineCard>
                </div>
                <Border height="h-3" opacity="opacity-70"></Border>
                <div v-if="state.isLoading">
                    <TimelinePublicationSkeleton v-for="i in 2"></TimelinePublicationSkeleton>
                </div>
                <div v-else class="block">
                    <div class="block">
                        <TimelinePublication v-bind:postData="postData" v-bind:campaignTracking="campaignTracking" v-on:delete="handlePostDelete"></TimelinePublication>
                    </div>
                    <Border></Border>
                    <div v-if="! state.isLoading" class="sticky top-0 bg-bg-pr z-10">
                        <PublicationCommentEditor v-bind:postId="postData.id" v-on:add="handleCommentAdding" v-on:uploaded="refreshThreads" v-on:edited="handleCommentEdited"></PublicationCommentEditor>

                        <div class="px-4 bg-fill-fv py-2">
                            <span class="text-par-s text-lab-sc text-center block font-semibold">
                                {{ $t('labels.comment_number', postData.comments_count.raw )}}
                            </span>
                        </div>
                    </div>
                    <div v-if="!state.isLoading" class="px-4 py-2 flex justify-end border-b border-bord-pr">
                        <label class="text-par-s text-lab-sc flex items-center gap-2">Sort
                            <select v-model="sort" class="bg-transparent text-lab-pr outline-hidden">
                                <option value="relevant">Most relevant</option>
                                <option value="newest">Newest</option>
                            </select>
                        </label>
                    </div>
                    <div class="block" v-if="postComments.length">

                        <div v-for="commentItem in postComments" v-bind:key="commentItem.id" class="border-b border-bord-pr/70">
                            <PublicationComment v-on:delete="handleCommentDelete" v-on:reply="handleCommentReply" v-bind:commentData="commentItem"></PublicationComment>
                            <div v-if="commentItem.reply_count > 0" class="ml-20 pb-3">
                                <button class="text-par-s font-semibold text-lab-sc" v-on:click="toggleThread(commentItem)">
                                    {{ openThreads[commentItem.id] ? 'Hide replies' : `View ${commentItem.reply_count} ${commentItem.reply_count === 1 ? 'reply' : 'replies'}` }}
                                </button>
                            </div>
                            <div v-if="openThreads[commentItem.id]" class="border-l border-bord-pr">
                                <PublicationComment v-for="reply in (threadReplies[commentItem.id] || [])" v-bind:key="reply.id" v-on:delete="handleCommentDelete" v-on:reply="handleCommentReply" v-bind:commentData="reply"></PublicationComment>
                                <div v-if="threadState[commentItem.id]?.loading" class="flex justify-center my-3"><div class="colibri-primary-animation"></div></div>
                                <button v-else-if="threadState[commentItem.id]?.hasMore" class="pb-3 text-par-s font-semibold text-lab-sc" v-on:click="loadMoreReplies(commentItem)">Load more replies</button>
                            </div>
                        </div>
                        <div v-if="state.isLoadingComments">
                            <div class="flex justify-center my-4">
                                <div class="colibri-primary-animation"></div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="block py-40 border-t border-bord-pr">
                        <TimelineEmptyState v-bind:desc="$t('empty_state.comments.desc')"></TimelineEmptyState>
                    </div>
                </div>
            </TimelineContainer>
        </template>

        <template v-slot:sidebar>
            <FollowRecommendationList></FollowRecommendationList>

            <AdGridItem></AdGridItem>
        </template>
    </SidedContentLayout>
    <ScrollTopButton></ScrollTopButton>
</template>

<script>
    import { defineComponent, ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';

    import { useRoute, useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { recordSponsoredSocialEvent } from '@/kernel/services/ads/sponsored-social.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';

    import TimelinePublication from '@D/components/timeline/feed/TimelinePublication.vue';
    import PublicationComment from '@D/components/timeline/feed/parts/comment/PublicationComment.vue';
    import PageHeader from '@D/components/layout/PageHeader.vue';

    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import TimelinePublicationSkeleton from '@D/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
    import TimelineEmptyState from '@D/components/timeline/state/TimelineEmptyState.vue';
    import PublicationCommentEditor from '@D/views/publication/editor/PublicationCommentEditor.vue';
    import ProfileTimelineCard from '@D/components/profile/ProfileTimelineCard.vue';
    import ProfileSidebarSkeleton from '@D/components/profile/parts/ProfileSidebarSkeleton.vue';
    import ScrollTopButton from '@D/components/inter-ui/buttons/ScrollTopButton.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';

    export default defineComponent({
        setup: function() {
            const route = useRoute();
            const router = useRouter();
            const state = reactive({
                isLoading: true,
                isLoadingComments: false,
                noMoreComments: false
            });

            const { postDeleter } = useDeletePost();
            const postData = ref({});
            const postAuthor = ref({});
            const postComments = ref([]);
            const openThreads = reactive({});
            const threadReplies = reactive({});
            const threadState = reactive({});
            const sort = ref('relevant');
            const campaignTracking = computed(() => {
                if(!route.query.ad_id || !route.query.delivery_id) return null;
                return {
                    id: route.query.ad_id,
                    delivery_id: route.query.delivery_id,
                    placement: route.query.ad_placement === 'reels' ? 'reels' : 'feed'
                };
            });

            const fetchComments = async (reset = false) => {
                if (state.isLoadingComments || (!reset && state.noMoreComments)) return;
                state.isLoadingComments = true;
                try {
                    const cursor = reset ? 0 : (postComments.value.nextCursor || 0);
                    const response = await colibriAPI().userTimeline().params({ cursor, sort: sort.value }).getFrom(`post/${route.params.hash_id}/comment-threads`);
                    const comments = response.data.data || [];
                    postComments.value = reset ? comments : postComments.value.concat(comments);
                    postComments.value.nextCursor = response.data.meta?.next_cursor;
                    state.noMoreComments = !response.data.meta?.has_more;
                    if (response.data.meta?.comments_count !== undefined) {
                        postData.value.comments_count = { ...postData.value.comments_count, raw: response.data.meta.comments_count, formatted: response.data.meta.comments_count_formatted };
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
                    const response = await colibriAPI().userTimeline().params({ cursor }).getFrom(`post/${route.params.hash_id}/comment-threads/${root.id}/replies`);
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

            useInfiniteScroll({
				callback: fetchComments
			});

            onMounted(() => {
                colibriAPI().userTimeline().params({ without_comments: 1 }).getFrom(`post/${route.params.hash_id}`).then(async function(response) {
                    postData.value = response.data.data.post;
                    postAuthor.value = response.data.data.author;
                    await fetchComments(true);
                    state.isLoading = false;
                }).catch(function(error) {
                    router.push({
                        name: 'error_404',
                        params: { pathMatch: route.path.substring(1).split('/') },
                        query: route.query,
                        hash: route.hash
                    });
                });

                colibriEventBus.on('timeline:post-updated', handlePostUpdated);
            });

            onUnmounted(() => {
                colibriEventBus.off('timeline:post-updated', handlePostUpdated);
            });

            const handlePostUpdated = (updatedPostData) => {
                if(postData.value?.id == updatedPostData.id) {
                    postData.value = updatedPostData;
                }
            };

            return {
                state: state,
                postData: postData,
                    campaignTracking: campaignTracking,
                postAuthor: postAuthor,
                postComments: computed(() => {
                    return postComments.value
                }),
                sort, openThreads, threadReplies, threadState, refreshThreads,
                toggleThread: async (root) => {
                    openThreads[root.id] = !openThreads[root.id];
                    if (openThreads[root.id] && !threadReplies[root.id]) await fetchReplies(root, true);
                },
                loadMoreReplies: (root) => fetchReplies(root),
                handleCommentReply: (commentId) => {
                    let commentData = postComments.value.find(item => item.id == commentId);
                    if (!commentData) for (const replies of Object.values(threadReplies)) { commentData = replies.find(item => item.id == commentId); if (commentData) break; }

                    if(commentData) {
                        colibriEventBus.emit('publication-comment:reply', {
                            commentData: commentData
                        });
                    }
                },
                handleCommentDelete: (commentId) => {
                    colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.delete_comment.title'),
                        description: __t('prompt.delete_comment.description'),
                        onConfirm: () => {
                            colibriAPI().userTimeline().with({
                                id: commentId
                            }).delete('post/comment/delete').then((response) => {

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
                handleCommentAdding: (commentData) => {
                    postData.value.comments_count = commentData.post.comments_count;
                    if (commentData.comment.parent_id) {
                        const root = postComments.value.find(item => item.id === commentData.comment.thread_root_id);
                        if (root) {
                            root.reply_count = Number(root.reply_count || 0) + 1;
                            if (openThreads[root.id]) threadReplies[root.id] = [...(threadReplies[root.id] || []), commentData.comment];
                        }
                    } else refreshThreads();
                    recordSponsoredSocialEvent(campaignTracking.value, campaignTracking.value?.placement || 'feed', 'comment');
                },
                handleCommentEdited: () => refreshThreads(),
                handlePostDelete: (postData) => {
                    postDeleter(postData, () => {
                        router.push({
                            name: 'home_index'
                        });
                    });
                }
            }
        },
        components: {
            TimelinePublication: TimelinePublication,
            PublicationComment: PublicationComment,
            PageHeader: PageHeader,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            TimelineContainer: TimelineContainer,
            PublicationCommentEditor: PublicationCommentEditor,
            TimelineEmptyState: TimelineEmptyState,
            ProfileTimelineCard: ProfileTimelineCard,
            ProfileSidebarSkeleton: ProfileSidebarSkeleton,
            ScrollTopButton: ScrollTopButton,
            SidedContentLayout: SidedContentLayout,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem
        }
    });
</script>
