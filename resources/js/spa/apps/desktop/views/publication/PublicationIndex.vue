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
                        <TimelinePublication v-bind:postData="postData" v-on:delete="handlePostDelete"></TimelinePublication>
                    </div>
                    <Border></Border>
                    <div v-if="! state.isLoading" class="sticky top-0 bg-bg-pr z-10">
                        <PublicationCommentEditor v-bind:postId="postData.id" v-on:add="handleCommentAdding"></PublicationCommentEditor>

                        <div class="px-4 bg-fill-fv py-2">
                            <span class="text-par-s text-lab-sc text-center block font-semibold">
                                {{ $t('labels.comment_number', postData.comments_count.raw )}}
                            </span>
                        </div>
                    </div>
                    <div class="block" v-if="postComments.length">

                        <div v-for="(commentItem, idx) in postComments">
                            <PublicationComment
                                v-on:delete="handleCommentDelete"
                                v-on:reply="handleCommentReply"
                                v-bind:key="commentItem.id"
                            v-bind:commentData="commentItem"></PublicationComment>
                            <Border v-if="idx != (postComments.length - 1)"></Border>
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
    import { defineComponent, ref, reactive, computed, onMounted, onUnmounted } from 'vue';

    import { useRoute, useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
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

            const fetchComments = async () => {
                if (postComments.value.length && ! state.noMoreComments && ! state.isLoadingComments) {
                    const cursorId = postComments.value.at(-1).id;

                    if (cursorId) {
                        state.isLoadingComments = true;

                        await colibriAPI().userTimeline().params({
                            cursor: cursorId
                        }).getFrom(`post/${route.params.hash_id}/comments`).then(function(response) {
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
            }

            useInfiniteScroll({
				callback: fetchComments
			});

            onMounted(() => {
                colibriAPI().userTimeline().getFrom(`post/${route.params.hash_id}`).then(function(response) {
                    postData.value = response.data.data.post;
                    postAuthor.value = response.data.data.author;
                    postComments.value = response.data.data.comments;
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
                postAuthor: postAuthor,
                postComments: computed(() => {
                    return postComments.value
                }),
                handleCommentReply: (commentId) => {
                    let commentData = postComments.value.find((item) => {
                        if(item.id == commentId) {
                            return item;
                        }
                    });

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
                handleCommentAdding: (commentData) => {
                    postComments.value.unshift(commentData.comment);

                    postData.value.comments_count = commentData.post.comments_count;
                },
                handlePostDelete: (postData) => {
                    colibriEventBus.emit('timeline:post-deleted', postData.id);

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
