<template>
    <div ref="publicationRootRef" class="base-publication min-h-40 border-b border-b-bord-tr">
        <div class="pt-4 max-w-full">
            <div class="flex overflow-hidden mb-2 px-4">
                <div class="flex-1 pr-2">
                    <PublicationHeader v-bind:postData="postData"></PublicationHeader>
                </div>
                <div class="shrink-0">
                    <div class="relative leading-none">
                        <div class="opacity-30 hover:opacity-100">
                            <DropdownButton v-on:click.stop="state.mainMenu.open"></DropdownButton>
                        </div>
                    </div>
                </div>
            </div>
            <div class="max-w-full">
                <template v-if="postHasContent">
                    <div class="overflow-hidden mb-4 px-4">
                        <PublicationText v-bind:postContent="postContent"></PublicationText>
                    </div>
                </template>
                <div class="mb-2" v-if="PostTypeUtils.isMedia(postData.type)">
                    <template v-if="PostTypeUtils.isImage(postData.type)">
                        <div v-on:click="lightboxImages" class="cursor-pointer">
                            <PublicationImage v-bind:isSensitive="isSensitive" v-bind:key="postData.hash_id" v-bind:postMedia="postMedia"></PublicationImage>
                        </div>
                    </template>
                    <template v-if="PostTypeUtils.isGif(postData.type)">
                        <PublicationGif v-bind:postMedia="postMedia"></PublicationGif>
                    </template>
                    <template v-else-if="PostTypeUtils.isVideo(postData.type)">
                        <PublicationVideo v-bind:postMedia="postMedia"></PublicationVideo>
                    </template>
                    <template v-else-if="PostTypeUtils.isAudio(postData.type)">
                        <PublicationAudio v-bind:postMedia="postMedia" v-bind:key="postData.id"></PublicationAudio>
                    </template>
                    <template v-else-if="PostTypeUtils.isDocument(postData.type)">
                        <PublicationDocument v-bind:postMedia="postMedia"></PublicationDocument>
                    </template>
                </div>
                <div class="overflow-hidden mb-4" v-else-if="PostTypeUtils.isPoll(postData.type)">
                    <PublicationPoll v-bind:postPoll="postPoll"></PublicationPoll>
                </div>
                <div v-else-if="postLinkSnapshot" class="overflow-hidden mb-2 px-4">
                    <a v-bind:href="postLinkSnapshot.url" target="_blank">
                        <LinkSnapshot v-bind:linkSnapshot="postLinkSnapshot"></LinkSnapshot>
                    </a>
                </div>
                <div v-else-if="postData.meta.is_quoting" class="overflow-hidden mb-2 px-4">
                    <PublicationQuote v-if="quotedPost" v-bind:quotedPost="quotedPost" v-bind:key="postData.id"></PublicationQuote>
                    <PublicationQuotePlaceholder v-else></PublicationQuotePlaceholder>
                </div>
                <div class="px-4" v-if="postReactions.length">
                    <ReactionsViewer v-on:add="addReaction" v-bind:reactions="postReactions"></ReactionsViewer>
                </div>
                <div class="pr-4 pl-3 mb-3">
                    <div class="flex items-center gap-1.5">
                        <div class="shrink-0 relative leading-zero">
                            <PrimaryIconButton v-on:click.stop="openReactions" v-bind:disabled="isPendingPost" buttonColor="text-lab-pr2" iconSize="6" iconName="heart-rounded" iconType="line"></PrimaryIconButton>
                        </div>
                        <div class="shrink-0 leading-zero relative">
                            <PrimaryIconButton v-on:click.stop="sharePost" v-bind:disabled="isPendingPost" buttonColor="text-lab-pr2" iconSize="6" iconName="share-06" iconType="line"></PrimaryIconButton>
                        </div>
                        <div class="shrink-0 leading-zero relative">
                            <div class="inline-flex items-center">
                                <PrimaryIconButton 
                                    v-on:click.stop="openComments"
                                    v-bind:disabled="isPendingPost"
                                    buttonColor="text-lab-pr2"
                                    iconSize="6"
                                    iconName="message-circle-02"
                                iconType="line"></PrimaryIconButton>

                                <span v-if="postData.comments_count.raw" class="text-lab-pr2 text-sm font-medium">
                                    {{ postData.comments_count.formatted }}
                                </span>
                            </div>
                        </div>
                        <div class="shrink-0 ml-auto">
                            <ViewsCounter v-bind:counterValue="postData.views_count.formatted"></ViewsCounter>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    

    <PublicationReactions v-if="state.reactionMenu.status"
        v-on:add="addReaction"
    v-on:close="state.reactionMenu.close"></PublicationReactions>

    <PublicationShare v-if="state.shareMenu.status"
        v-bind:postLink="postLink"
        v-on:close="state.shareMenu.close"></PublicationShare>

    <PublicationComments v-bind:postData="postData" v-if="state.commentsMenu.status" v-on:close="state.commentsMenu.close"></PublicationComments>

    <ActionSheet v-if="state.mainMenu.status" v-on:close="state.mainMenu.close" v-bind:isMuted="true">
        <div v-on:click.stop="state.mainMenu.close" class="h-full overflow-y-auto">
            <div v-if="! isPendingPost" class="mb-4">
                <ActionSheetReactions v-on:add="addReaction"></ActionSheetReactions>
            </div>

            <div class="mb-4">
                <ActionSheetGroup>
                    <ActionSheetItem
                        v-if="! isPendingPost"
                        v-on:click="state.reactionMenu.open"
                        iconName="heart-rounded"
                    v-bind:textLabel="$t('dd.add_reaction')"></ActionSheetItem>
        
                    <RouterLink v-if="! isOptimisticPost" v-bind:to="{ name: 'publication_index', params: { hash_id: postData.hash_id }}">
                        <ActionSheetItem v-bind:notLast="true" iconName="arrow-up-right" v-bind:textLabel="$t('dd.post.open_post')"></ActionSheetItem>
                    </RouterLink>
            
                    <ActionSheetItem
                        v-if="! isPendingPost"
                        v-on:click="bookmarkPost"
                        v-bind:iconName="postData.meta.activity.bookmarked ? 'bookmark-minus' : 'bookmark'"
                    v-bind:textLabel="postData.meta.activity.bookmarked ? $t('dd.post.unbookmark') : $t('dd.post.bookmark')"></ActionSheetItem>
                </ActionSheetGroup>
            </div>
            <div class="mb-4">
                <ActionSheetGroup>
                    <ActionSheetItem v-if="! isPendingPost" v-on:click="sharePost" iconName="share-06" v-bind:textLabel="$t('dd.post.share')"></ActionSheetItem>
                    <ActionSheetItem v-if="! isOptimisticPost" v-on:click="copyLink" iconName="copy-06" v-bind:textLabel="$t('dd.post.copy_link')"></ActionSheetItem>
                    <ActionSheetItem
                        v-if="postHasContent"
                        v-on:click="copyContent"
                        iconName="type-01"
                    v-bind:textLabel="$t('dd.copy_text')"></ActionSheetItem>
                </ActionSheetGroup>
            </div>
	            <ActionSheetGroup>
	                <template v-if="canEditPost">
	                    <ActionSheetItem v-on:click="editPost" iconName="pencil-02" v-bind:textLabel="$t('dd.post.edit_post')"></ActionSheetItem>
	                </template>
	                <template v-if="canDeletePost">
	                    <ActionSheetItem v-on:click="$emit('delete', postData)" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.post.delete_post')"></ActionSheetItem>
	                </template>
                <template v-if="canReportPost">
                    <ActionSheetItem v-on:click="reportPost" itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.post.report_post')"></ActionSheetItem>
                </template>
            </ActionSheetGroup>
        </div>
    </ActionSheet>
</template>

<script>
    import { defineComponent, defineAsyncComponent, reactive, computed, ref } from 'vue';
    import { PostTypeUtils } from '@/kernel/enums/post/post.type.js';
    import { PostStatusUtils } from '@/kernel/enums/post/post.status.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { colibriTranslator } from '@/kernel/services/translator/index.js';
    import { applyOptimisticReaction } from '@/kernel/services/reactions/optimistic.js';
    import { useLightboxStore } from '@M/store/lightbox/lightbox.store.js';
    import { useMenu } from '@/kernel/vue/composables/menu/index.js';
    import { useFeedPostTelemetry } from '@/kernel/vue/composables/feed-telemetry/index.js';

	// Mobile components
	import PublicationHeader from '@M/components/timeline/feed/parts/PublicationHeader.vue';
    import PublicationText from '@M/components/timeline/feed/parts/text/PublicationText.vue';
    import PublicationImage from '@M/components/timeline/feed/parts/media/PublicationImage.vue';
    import PublicationVideo from '@M/components/timeline/feed/parts/media/PublicationVideo.vue';
    import PublicationGif from '@M/components/timeline/feed/parts/media/PublicationGif.vue';
    import PublicationDocument from '@M/components/timeline/feed/parts/media/PublicationDocument.vue';
    import PublicationPoll from '@M/components/timeline/feed/parts/poll/PublicationPoll.vue';
    import DropdownButton from '@M/components/general/dropdowns/DropdownButton.vue';
    import LinkSnapshot from '@M/components/media/links/LinkSnapshot.vue';
    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
    import ViewsCounter from '@/kernel/vue/components/general/counters/ViewsCounter.vue';
    import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
    import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
    import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
    import ActionSheetReactions from '@M/components/general/sheets/ActionSheetReactions.vue';
    import PublicationComments from '@M/components/timeline/feed/parts/comments/PublicationComments.vue';

    // This component is used to display a publication in the timeline feed.
    // It is used in the BookmarksPostsPage component.
    // Changes to this component will affect the timeline feed and the bookmarks page.

    export default defineComponent({
        props: {
            postData: {
                type: Object,
                default: {}
            },
            feedSessionId: {
                type: String,
                default: ''
            },
            feedType: {
                type: String,
                default: 'for_you'
            },
            position: {
                type: Number,
                default: 0
            },
            source: {
                type: String,
                default: 'timeline'
            },
            refreshReason: {
                type: String,
                default: ''
            }
        },
        emits: ['delete'],
        setup: function(props) {
            const lightboxStore = useLightboxStore();
            const publicationRootRef = ref(null);

            const state = reactive({
                shareMenu: useMenu(),
                commentsMenu: useMenu(),
                reactionMenu: useMenu(),
                mainMenu: useMenu()
            });
            
            const postData = computed(() => {
                return props.postData;
            });

            useFeedPostTelemetry({
                targetRef: publicationRootRef,
                postData: postData,
                feedSessionId: computed(() => props.feedSessionId),
                feedType: computed(() => props.feedType),
                position: computed(() => props.position),
                source: computed(() => props.source),
                refreshReason: computed(() => props.refreshReason)
            });

            const postLink = computed(() => {
                return base_url(`publication/${postData.value.hash_id}`);
            });

            const isOptimisticPost = computed(() => {
                return Boolean(postData.value.meta?.is_optimistic);
            });

            const isPendingPost = computed(() => {
                return isOptimisticPost.value || ! PostStatusUtils.isActive(postData.value.status || 'active');
            });

            const postContent = computed(() => {
                return postData.value.content;
            });

            return {
                postContent: postContent,
                PostTypeUtils: PostTypeUtils,
                postData: postData,
                publicationRootRef: publicationRootRef,
                state: state,
                isPendingPost: isPendingPost,
                isOptimisticPost: isOptimisticPost,
                postHasContent: computed(() => {
                    return postData.value.content.length;
                }),
				postLink: postLink,
                quotedPost: computed(() => {
                    return postData.value.relations.quoted_post;
                }),
                postMedia: computed(() => {
                    return postData.value.relations.media;
                }),
                postPoll: computed(() => {
                    return postData.value.relations.poll;
                }),
                isSensitive: computed(() => {
                    return postData.value.meta.is_sensitive;
                }),
                postLinkSnapshot: computed(() => {
                    return postData.value.relations.link_snapshot;
                }),
                postReactions: computed(() => {
                    return postData.value.relations.reactions;
                }),
                canDeletePost: computed(() => {
                    return postData.value.meta.permissions.can_delete;
                }),
                canEditPost: computed(() => {
                    return postData.value.meta.permissions.can_edit && ! isOptimisticPost.value;
                }),
                canReportPost: computed(() => {
                    return postData.value.meta.permissions.can_report;
                }),
                editPost: () => {
                    if(isOptimisticPost.value) {
                        return false;
                    }

                    colibriEventBus.emit('post-editor:open', {
                        editPost: postData.value
                    });
                },
                addReaction: (reactionId) => {
                    if(isPendingPost.value) {
                        return false;
                    }

                    state.reactionMenu.close();

                    const rollbackReaction = applyOptimisticReaction(postData.value, reactionId);
                    colibriEventBus.emit('timeline:post-updated', postData.value);

                    colibriAPI().userTimeline().with({
                        unified_id: reactionId,
                        post_id: postData.value.id
                    }).sendTo('post/reaction/add').then((response) => {
                        postData.value.relations.reactions = response.data.data;
                        colibriEventBus.emit('timeline:post-updated', postData.value);
                    }).catch((error) => {
                        rollbackReaction();
                        colibriEventBus.emit('timeline:post-updated', postData.value);

                        if (error.response) {
                            toastError(error.response.data.message);
                        }
                    });
                },
                lightboxImages: () => {
                    lightboxStore.add({
                        albumName: `${postData.value.relations.user.name} ${postData.value.relations.user.caption}`,
                        date: postData.value.date.iso,
                        images: postData.value.relations.media.map((item) => {
                            return item.source_url;
                        })
                    });
                },
                reportPost: () => {
                    colibriEventBus.emit('report:open', {
                        type: 'post',
                        reportableId: postData.value.id
                    });
                },
                sharePost: () => {
                    if(isPendingPost.value) {
                        return false;
                    }

                    colibriAPI().userTimeline().with({
                        id: postData.value.id
                    }).sendTo('post/share/add').then((response) => {
                        postData.value.shares_count = response.data.data.shares_count;
                    }).catch((error) => {
                        if (error.response) {
                            toastError(error.response.data.message);
                        }
                    });

                    state.shareMenu.open();
                },
                bookmarkPost: () => {
                    if(isPendingPost.value) {
                        return false;
                    }

                    colibriAPI().userTimeline().with({
                        id: postData.value.id
                    }).sendTo('post/bookmarks/add').then((response) => {
                        postData.value.meta.activity.bookmarked = response.data.data.bookmarked;

                        if(response.data.data.bookmarked) {
                            toastSuccess(__t('toast.post.bookmarked'));
                        }
                        else {
                            toastError(__t('toast.post.unbookmarked'));
                        }
                    }).catch((error) => {
                        if (error.response) {
                            toastError(error.response.data.message);
                        }
                    });
                },
                openReactions: () => {
                    if(! isPendingPost.value) {
                        state.reactionMenu.open();
                    }
                },
                openComments: () => {
                    if(! isPendingPost.value) {
                        state.commentsMenu.open();
                    }
                },
                copyLink: () => {
                    try {
                        navigator.clipboard.writeText(postLink.value).then(() => {
                            toastSuccess(__t('toast.post.link_copied'));
                        });
                    } catch (error) {
                        toastError(error);
                    }
                },
                copyContent: () => {
                    try {
                        navigator.clipboard.writeText(postContent.value).then(() => {
                            toastSuccess(__t('toast.post.content_copied'));
                        });
                    } catch (error) {
                        toastError(error);
                    }
                }
            }
        },
        components: {
			PublicationHeader: PublicationHeader,
            PublicationText: PublicationText,
            PublicationImage: PublicationImage,
            PublicationVideo: PublicationVideo,
            PublicationGif: PublicationGif,
            PublicationDocument: PublicationDocument,
            ActionSheetReactions: ActionSheetReactions,
            PublicationPoll: PublicationPoll,
            DropdownButton: DropdownButton,
            LinkSnapshot: LinkSnapshot,
            PrimaryIconButton: PrimaryIconButton,
            ViewsCounter: ViewsCounter,
            ActionSheet: ActionSheet,
            ActionSheetItem: ActionSheetItem,
            ActionSheetGroup: ActionSheetGroup,
            PublicationComments: PublicationComments,
            ReactionsViewer: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/reactions/ReactionsViewer.vue');
            }),
            PublicationShare: defineAsyncComponent(() => {
				return import('@M/components/timeline/feed/parts/share/PublicationShare.vue');
			}),
            PublicationReactions: defineAsyncComponent(() => {
				return import('@M/components/timeline/feed/parts/reactions/PublicationReactions.vue');
			}),
            PublicationAudio: defineAsyncComponent(() => {
				return import('@M/components/timeline/feed/parts/media/PublicationAudio.vue');
			}),
            PublicationQuote: defineAsyncComponent(() => {
                return import('@M/components/timeline/feed/parts/quote/PublicationQuote.vue');
            }),
            PublicationQuotePlaceholder: defineAsyncComponent(() => {
                return import('@M/components/timeline/feed/parts/quote/PublicationQuotePlaceholder.vue');
            }),
		}
    });
</script>
