<template>
    <div ref="publicationRootRef" class="relative border-b border-b-bord-tr last:border-none">
        <div class="absolute overflow-hidden top-4 left-4">
            <AvatarSmall v-bind:avatarSrc="postData.relations.user.avatar_url" ></AvatarSmall>
        </div>
        <div class="px-4 pt-4 max-w-full">
            <div class="ml-small-avatar pl-2">
                <div class="overflow-hidden mb-1">
                    <div class="flex items-center overflow-hidden">
                        <div class="leading-4 flex-1 overflow-hidden">
                            <RouterLink v-bind:to="{ name: 'profile_index', params: { id: postData.relations.user.username } }" class="flex cursor-pointer gap-1">
                                <h3 class="text-par-m text-lab-pr2 truncate">
                                    <span class="flex items-center gap-1">
                                        <span class="shrink-0 font-semibold">
                                            {{ postData.relations.user.name }}
                                        </span>
                                        <VerificationBadge v-if="postData.relations.user.verified"></VerificationBadge>
                                        <span v-if="postData.meta.is_ai_generated" v-bind:title="$t('labels.ai_generated')" class="size-icon-x-small inline-block text-amber-500">
                                            <SvgIcon name="ai-icon"></SvgIcon>
                                        </span>
                                    </span>
                                </h3>
                                <span class="text-par-n text-lab-sc truncate">
                                    {{ postUserCaption }} · {{ postData.date.time_ago }}
                                    <template v-if="postData.meta.is_edited">
                                        · {{ $t('labels.edited') }}
                                    </template>
                                </span>
                                <span v-if="isPinned" class="size-icon-x-small inline-block text-brand-900 ml-1">
                                    <SvgIcon name="pin-02"></SvgIcon>
                                </span>
                            </RouterLink>
                        </div>
                    </div>
                </div>
                <div class="max-w-full">
                    <template v-if="postHasContent">
                        <div class="overflow-hidden mb-4">
                            <div v-if="postData.meta.is_translatable" class="leading-none mb-1">
                                <TextTranslateButton
                                    v-if="state.isTranslated"
                                    v-on:click="cancelTranslation"
                                v-bind:buttonText="$t('labels.show_untranslated')"></TextTranslateButton>

                                <TextTranslateButton
                                    v-else
                                    v-on:click="translate"
                                v-bind:buttonText="state.isTranslating ? $t('labels.translating') : $t('labels.translate_to', { language_name: userLocaleName })"></TextTranslateButton>
                            </div>
                            <PublicationText v-bind:postContent="postContent"></PublicationText>

                            <div v-if="state.isTranslated" class="mt-2">
								<TranslationService></TranslationService>
							</div>
                        </div>
                    </template>
                    <div class="overflow-hidden mb-2" v-if="postHasMedia">
                        <template v-if="PostTypeUtils.isImage(postData.type)">
                            <div v-on:click="lightboxImages" class="block cursor-pointer rounded-2xl overflow-hidden border border-bord-card">
                                <PublicationImage v-bind:key="postData.hash_id" v-bind:isSensitive="isSensitive" v-bind:postMedia="postMedia"></PublicationImage>
                            </div>
                        </template>
                        <template v-if="PostTypeUtils.isGif(postData.type)">
                            <PublicationGif v-on:click="lightboxImages" v-bind:postMedia="postMedia"></PublicationGif>
                        </template>
                        <template v-else-if="PostTypeUtils.isVideo(postData.type)">
                            <PublicationVideo
                                v-bind:postMedia="postMedia"
                                v-bind:openReelsOnTap="true"
                                v-on:open-reels="openReelsViewer"
                            v-bind:key="postData.id"></PublicationVideo>
                        </template>
                        <template v-else-if="PostTypeUtils.isDocument(postData.type)">
                            <PublicationDocument v-bind:postMedia="postMedia"></PublicationDocument>
                        </template>
                        <template v-else-if="PostTypeUtils.isAudio(postData.type)">
                            <PublicationAudio v-bind:postMedia="postMedia" v-bind:label="postData.relations.user.name" v-bind:key="postData.id"></PublicationAudio>
                        </template>
                    </div>
                    <div class="overflow-hidden mb-4" v-else-if="postHasPoll">
                        <PublicationPoll v-bind:postPoll="postPoll"></PublicationPoll>
                    </div>
                    <div v-else-if="postData.meta.is_quoting" class="overflow-hidden mb-2">
                        <PublicationQuote v-if="quotedPost" v-bind:quotedPost="quotedPost" v-bind:key="postData.id"></PublicationQuote>
                        <PublicationQuotePlaceholder v-else></PublicationQuotePlaceholder>
                    </div>
                    <div v-else-if="postLinkSnapshot" class="overflow-hidden mb-2">
                        <a v-bind:href="postLinkSnapshot.url" target="_blank">
                            <LinkSnapshot v-bind:linkSnapshot="postLinkSnapshot"></LinkSnapshot>
                        </a>
                    </div>
                    <div class="block" v-if="postReactions.length">
                        <ReactionsViewer v-on:add="addReaction" v-bind:reactions="postReactions"></ReactionsViewer>
                    </div>
                    <div class="block mb-3 -ml-1">
                        <div class="flex items-center">
                            <div class="shrink-0 relative leading-zero">
                                <PrimaryIconButton iconSize="icon-normal" v-on:click.stop="openReactionsPicker" iconName="heart-rounded" iconType="line"></PrimaryIconButton>
                                <PrimaryTransition v-if="state.isReactionPickerOpen">
                                    <div class="absolute left-0 bottom-8 origin-top-left z-20">
                                        <ReactionsPicker
                                            v-on:add="addReaction"
                                        v-outside-click="closeReactionsPicker"></ReactionsPicker>
                                    </div>
                                </PrimaryTransition>
                            </div>

                            <div class="shrink-0 leading-zero relative">
                                <PrimaryIconButton v-on:click.stop="sharePost" iconSize="icon-normal" iconName="share-06" iconType="line"></PrimaryIconButton>
								<PrimaryTransition v-if="state.isSharePostOpen">
									<div class="absolute left-0 bottom-8 origin-top-left z-20">
										<PublicationShare v-outside-click="cancelSharePost" v-on:click.stop="cancelSharePost" v-bind:postLink="postLink"></PublicationShare>
									</div>
								</PrimaryTransition>
							</div>
                            <div v-if="! postData.relations.comments.length && ! isOnPostPage" class="shrink-0">
                                <RouterLink v-bind:to="{ name: 'publication_index', params: { hash_id: postData.hash_id }}">
                                    <PrimaryIconButton iconSize="icon-normal" iconName="message-circle-02" iconType="line"></PrimaryIconButton>
                                </RouterLink>
                            </div>

                            <template v-if="isOnPostPage">
                                <PrimaryIconButton v-bind:disabled="true" iconSize="icon-normal" iconName="message-circle-02" iconType="line"></PrimaryIconButton>
                            </template>

                            <div class="flex-1 overflow-hidden">
                                <div v-if="! isOnPostPage" class="flex items-center h-x-small-avatar">
                                    <div v-if="postData.relations.comments.length" class="flex ml-1">
                                        <div v-for="comment in postData.relations.comments" v-bind:key="comment.id" class="-ml-2 first:ml-0 border rounded-full border-fill-pr">
                                            <AvatarExtraSmall v-bind:avatarSrc="comment.user.avatar_url"></AvatarExtraSmall>
                                        </div>
                                    </div>

                                    <div class="flex-1 overflow-hidden ml-2">
                                        <RouterLink v-bind:to="{ name: 'publication_index', params: { hash_id: postData.hash_id }}" class="text-par-s text-lab-sc truncate block hover:text-brand-900">
                                            <template v-if="postData.relations.comments.length">
                                                {{ $t('labels.show_all_comments') }} ({{ postData.comments_count.formatted }})
                                            </template>
                                            <template v-else>
                                                {{ $t('labels.leave_comment') }}
                                            </template>
                                        </RouterLink>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 self-end">
                                <ViewsCounter v-bind:counterValue="postData.views_count.formatted"></ViewsCounter>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="absolute top-2 right-2.5">
            <div class="relative leading-none">
                <div class="opacity-30 hover:opacity-100">
                    <DropdownButton v-on:click.stop="toggleMenu"></DropdownButton>
                </div>

                <div class="absolute top-full right-0 z-50" v-if="state.isMenuOpen">
                    <DropdownMenu v-outside-click="toggleMenu" v-on:click="toggleMenu">
                        <DropdownReactions v-on:add="addReaction"></DropdownReactions>
                        <DropdownMenuItem v-on:click="openReactionsPicker" iconName="heart-rounded" v-bind:textLabel="$t('dd.add_reaction')"></DropdownMenuItem>

                        <template v-if="postHasContent && isTranslatable">
                            <DropdownMenuItem
                                v-if="state.isTranslated"
                                v-on:click="cancelTranslation"
                                iconName="translate-01"
                            v-bind:textLabel="$t('labels.show_untranslated')"></DropdownMenuItem>

                            <DropdownMenuItem
                                v-else
                                v-on:click="translate"
                                iconName="translate-01"
                            v-bind:textLabel="$t('dd.translate')"></DropdownMenuItem>
                        </template>
                        <Border/>
                        <template v-if="! isOnPostPage">
                            <RouterLink v-bind:to="{ name: 'publication_index', params: { hash_id: postData.hash_id }}">
                                <DropdownMenuItem iconName="arrow-up-right" v-bind:textLabel="$t('dd.post.open_post')"></DropdownMenuItem>
                            </RouterLink>
                        </template>

                        <DropdownMenuItem v-on:click="quotePost" iconName="pencil-line" v-bind:textLabel="$t('dd.post.quote_post')"></DropdownMenuItem>
                        <DropdownMenuItem v-if="canEditPost" v-on:click="editPost" iconName="pencil-02" v-bind:textLabel="$t('dd.post.edit_post')"></DropdownMenuItem>
                        <DropdownMenuItem v-on:click="mentionAuthor" iconName="at-sign" v-bind:textLabel="$t('dd.post.mention_author', { name: `@${postData.relations.user.name}`})"></DropdownMenuItem>
                        <Border/>
                        <DropdownMenuItem
                            v-on:click="bookmarkPost"
                            v-bind:iconName="postData.meta.activity.bookmarked ? 'bookmark-minus' : 'bookmark'"
                        v-bind:textLabel="postData.meta.activity.bookmarked ? $t('dd.post.unbookmark') : $t('dd.post.bookmark')"></DropdownMenuItem>

                        <DropdownMenuItem v-on:click="sharePost" iconName="share-06" v-bind:textLabel="$t('dd.post.share')"></DropdownMenuItem>
                        <DropdownMenuItem v-on:click="copyLink" iconName="copy-06" v-bind:textLabel="$t('dd.post.copy_link')"></DropdownMenuItem>
                        <DropdownMenuItem v-if="postHasContent" v-on:click="copyContent" iconName="type-01" v-bind:textLabel="$t('dd.copy_text')"></DropdownMenuItem>
                        <Border/>
                        <DropdownMenuItem v-if="canReportPost" v-on:click="reportPost" itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.post.report_post')"></DropdownMenuItem>
                        <template v-if="canDeletePost">
                            <DropdownMenuItem v-on:click="$emit('delete', postData)" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.post.delete_post')"></DropdownMenuItem>
                        </template>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, defineAsyncComponent, reactive, computed, ref } from 'vue';
    import { useRoute, useRouter } from 'vue-router';
    import { PostTypeUtils } from '@/kernel/enums/post/post.type.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useLightboxStore } from '@D/store/lightbox/lightbox.store.js';
    import { colibriTranslator } from '@/kernel/services/translator/index.js';
    import { applyOptimisticReaction } from '@/kernel/services/reactions/optimistic.js';
    import { useFeedPostTelemetry } from '@/kernel/vue/composables/feed-telemetry/index.js';

    import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
    import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';
    import DropdownMenu from '@D/components/general/dropdowns/parts/DropdownMenu.vue';
    import DropdownMenuItem from '@D/components/general/dropdowns/parts/DropdownMenuItem.vue';
    import DropdownReactions from '@D/components/general/dropdowns/parts/DropdownReactions.vue';
    import ViewsCounter from '@/kernel/vue/components/general/counters/ViewsCounter.vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
    import TextTranslateButton from '@D/components/inter-ui/buttons/TextTranslateButton.vue';
    import TranslationService from '@D/components/general/TranslationService.vue';
    import PublicationQuote from '@D/components/timeline/feed/parts/quote/PublicationQuote.vue';

    // This component is used to display a publication in the timeline feed.
    // It is used in the BookmarksPostsPage component.
    // Changes to this component will affect the timeline feed and the bookmarks page.

    export default defineComponent({
        props: {
            isPinned: {
                type: Boolean,
                default: false
            },
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
        setup: function(props) {
            const route = useRoute();
            const router = useRouter();
            const state = reactive({
                isMenuOpen: false,
                isReactionPickerOpen: false,
                isTranslating: false,
                isTranslated: false,
                isSharePostOpen: false
            });

            const postTranslatedContent = ref('');
            const postData = computed(() => {
                return props.postData;
            });
            const publicationRootRef = ref(null);

            const lightboxStore = useLightboxStore();

            useFeedPostTelemetry({
                targetRef: publicationRootRef,
                postData: postData,
                feedSessionId: computed(() => props.feedSessionId),
                feedType: computed(() => props.feedType),
                position: computed(() => props.position),
                source: computed(() => props.source),
                refreshReason: computed(() => props.refreshReason)
            });

            const openReactionsPicker = function() {
                debounce(() => {
                    state.isReactionPickerOpen = true;
                }, 50);
            }

            const closeReactionsPicker = function() {
                state.isReactionPickerOpen = false;
            }

            const postContent = computed(() => {
                return state.isTranslated ? postTranslatedContent.value : postData.value.content;
            });

            const postLink = computed(() => {
                return base_url(`publication/${postData.value.hash_id}`);
            });

            return {
                toggleMenu: () => {
                    state.isMenuOpen = !state.isMenuOpen;
                },
                postContent: postContent,
                openReactionsPicker: openReactionsPicker,
                PostTypeUtils: PostTypeUtils,
                closeReactionsPicker: closeReactionsPicker,
                postData: postData,
                publicationRootRef: publicationRootRef,
                state: state,
                openReelsViewer: () => {
                    if(! postData.value?.hash_id) {
                        return;
                    }

                    router.push({
                        name: 'explore_reels',
                        params: {
                            hash_id: postData.value.hash_id
                        }
                    });
                },
                isSensitive: computed(() => {
                    return postData.value.meta.is_sensitive;
                }),
                userLocaleName: embedder('locale_name'),
                addReaction: (reactionId) => {
                    closeReactionsPicker();

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
                postHasMedia: computed(() => {
                    return postData.value.relations.media?.length;
                }),
                postHasPoll: computed(() => {
                    return postData.value.relations.poll;
                }),
                postHasContent: computed(() => {
                    return postData.value.content.length;
                }),
                postLinkSnapshot: computed(() => {
                    return postData.value.relations.link_snapshot;
                }),
                quotedPost: computed(() => {
                    return postData.value.relations.quoted_post;
                }),
                isTranslatable: computed(() => {
                    return postData.value.meta.is_translatable;
                }),
                postMedia: computed(() => {
                    return postData.value.relations.media;
                }),
                postPoll: computed(() => {
                    return postData.value.relations.poll;
                }),
				postLink: postLink,
                postReactions: computed(() => {
                    return postData.value.relations.reactions;
                }),
                lightboxImages: () => {
                    lightboxStore.add({
                        albumName: `${postData.value.relations.user.name} ${postData.value.relations.user.caption}`,
                        date: postData.value.date.iso,
                        images: postData.value.relations.media.map((item) => {
                            return item.source_url;
                        })
                    });
                },
                postUserCaption: computed(() => {
                    return postData.value.relations.user.caption;
                }),
                canDeletePost: computed(() => {
                    return postData.value.meta.permissions.can_delete;
                }),
                canEditPost: computed(() => {
                    return postData.value.meta.permissions.can_edit;
                }),
                canReportPost: computed(() => {
                    return postData.value.meta.permissions.can_report;
                }),
                editPost: () => {
                    colibriEventBus.emit('post-editor:open', {
                        editPost: postData.value
                    });
                },
                mentionAuthor: () => {
                    colibriEventBus.emit('post-editor:open', {
                        mentionName: postData.value.relations.user.username
                    });
                },
                bookmarkPost: () => {
                    colibriAPI().userTimeline().with({
                        id: postData.value.id
                    }).sendTo('post/bookmarks/add').then((response) => {
                        postData.value.meta.activity.bookmarked = response.data.data.bookmarked;

                        if(response.data.data.bookmarked) {
                            toastSuccess(__t('toast.post.bookmarked'));
                        }
                        else {
                            toastSuccess(__t('toast.post.unbookmarked'));
                        }
                    }).catch((error) => {
                        if (error.response) {
                            toastError(error.response.data.message);
                        }
                    });
                },
                translate: async () => {
                    if (state.isTranslating || state.isTranslated) {
                        return false;
                    }

                    state.isTranslating = true;
                    const translatedText = await colibriTranslator().translate(postContent.value);

                    if (translatedText) {
                        postTranslatedContent.value = translatedText;
                        state.isTranslated = true;
                    }

                    state.isTranslating = false;
                },
                cancelTranslation: () => {
                    state.isTranslated = false;
                    postTranslatedContent.value = '';
                },
                copyContent: () => {
                    navigator.clipboard.writeText(postContent.value).then(() => {
                        toastSuccess(__t('toast.post.content_copied'), 1000);
                    });
                },
                copyLink: () => {
                    navigator.clipboard.writeText(postLink.value).then(() => {
                        toastSuccess(__t('toast.post.link_copied'), 1000);
                    });
                },
                reportPost: () => {
                    colibriEventBus.emit('report:open', {
                        type: 'post',
                        reportableId: postData.value.id
                    });
                },
				quotePost: () => {
					colibriEventBus.emit('post-editor:open', {
						quotePostId: postData.value.id
					});
				},
                sharePost: () => {
                    colibriAPI().userTimeline().with({
                        id: postData.value.id
                    }).sendTo('post/share/add').then((response) => {
                        postData.value.shares_count = response.data.data.shares_count;
                    }).catch((error) => {
                        if (error.response) {
                            toastError(error.response.data.message);
                        }
                    });

					debounce(() => {
						state.isSharePostOpen = true;
					}, 50);
                },
				cancelSharePost: () => {
					state.isSharePostOpen = false;
				},
                isOnPostPage: computed(() => {
                    return route.name === 'publication_index';
                })
            }
        },
        components: {
            AvatarSmall: AvatarSmall,
            DropdownButton: DropdownButton,
            DropdownMenu: DropdownMenu,
            DropdownMenuItem: DropdownMenuItem,
            DropdownReactions: DropdownReactions,
            PrimaryIconButton: PrimaryIconButton,
            TextTranslateButton: TextTranslateButton,
            PublicationQuote: PublicationQuote,
            ReactionsViewer: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/reactions/ReactionsViewer.vue');
            }),
            ViewsCounter: ViewsCounter,
            TranslationService: TranslationService,
            ReactionsPicker: defineAsyncComponent(() => {
                return import('@D/components/reactions/ReactionsPicker.vue');
            }),
            PublicationImage: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/media/PublicationImage.vue');
            }),
            PublicationGif: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/media/PublicationGif.vue');
            }),
            PublicationVideo: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/media/PublicationVideo.vue');
            }),
            PublicationDocument: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/media/PublicationDocument.vue');
            }),
            PublicationAudio: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/media/PublicationAudio.vue');
            }),
            PublicationPoll: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/poll/PublicationPoll.vue');
            }),
            PublicationText: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/text/PublicationText.vue');
            }),
            AvatarExtraSmall: defineAsyncComponent(() => {
                return import('@D/components/general/avatars/AvatarExtraSmall.vue');
            }),
			PublicationShare: defineAsyncComponent(() => {
				return import('@D/components/timeline/feed/parts/share/PublicationShare.vue');
			}),
            PublicationQuotePlaceholder: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/quote/PublicationQuotePlaceholder.vue');
            }),
            LinkSnapshot: defineAsyncComponent(() => {
                return import('@D/components/media/links/LinkSnapshot.vue');
            })
        }
    });
</script>
