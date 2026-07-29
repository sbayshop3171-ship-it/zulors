<template>
    <div class="relative markdown-text py-3 group">
        <div class="absolute overflow-hidden top-4 left-4">
            <AvatarSmall v-bind:avatarSrc="commentData.relations.user.avatar_url"></AvatarSmall>
        </div>
        <div class="ml-4 pr-4 pb-1 max-w-full">
            <div class="ml-small-avatar pl-2">
                <div class="block leading-none mb-1">
                    <div class="inline-flex items-center gap-2">
						<h3 class="text-par-n font-semibold text-lab-pr2">
                            {{ commentData.relations.user.name }}
                        </h3>
                        <span class="text-par-s text-lab-sc ">
                            {{ commentData.date.time_ago }}
                        </span>
                    </div>
                </div>
                <div class="block">
                    <div v-if="commentData.has_parent" class="overflow-hidden leading-tight">
                        <!-- TODO: Show parent comment in popup element if clicked -->
                        <template v-if="commentData.deleted">
                            <p class="text-cap-l text-lab-sc break-words italic">
                                {{ $t('labels.comment_was_deleted') }}
                            </p>
                        </template>
                        <template v-else>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-flex shrink-0 items-center gap-1 text-par-s font-semibold text-brand-900 leading-none">
                                    <SvgIcon name="share-06" type="line" classes="size-icon-x-small transform -scale-x-100"></SvgIcon> {{ commentData.relations.parent.user.name }}
                                </span>
                                <span class="text-par-n text-lab-pr3 break-words line-clamp-1">
                                    {{ commentData.relations.parent.content }}
                                </span>
                            </span>
                        </template>
                    </div>
                    <div class="pr-6">
                        <CommentText v-bind:commentContent="commentContent"></CommentText>
                    </div>
                    <div v-if="state.isTranslated" class="mt-2">
                        <TranslationService></TranslationService>
                    </div>
                </div>
                <div v-if="! commentData.deleted">
                    <div class="block mt-2" v-if="hasReactions">
                        <ReactionsViewer v-on:add="addReaction" v-bind:reactions="commentData.relations.reactions"></ReactionsViewer>
                    </div>
                </div>
                <div v-if="! commentData.deleted" class="flex items-center leading-none mt-2 gap-3">
                    <div class="shrink-0 relative">
                        <MarginalTextButton
                            buttonColor="text-lab-sc"
                            v-on:click.stop="openReactionsPicker" 
                        v-bind:buttonText="$t('labels.like')"></MarginalTextButton>

                        <PrimaryTransition>
                            <div class="absolute left-0 top-4 origin-top-left z-20">
                                <ReactionsPicker 
                                    v-if="state.isReactionPickerOpen" 
                                    v-on:add="addReaction"
                                v-outside-click="closeReactionsPicker"></ReactionsPicker>
                            </div>
                        </PrimaryTransition>
                    </div>
                    <div class="shrink-0">
                        <MarginalTextButton
                            buttonColor="text-lab-sc"
                            v-on:click="replyToComment"
                        v-bind:buttonText="$t('labels.reply')"></MarginalTextButton>
                    </div>
                    <div class="shrink-0" v-if="commentData.meta.is_translatable">
                        <MarginalTextButton
                            v-if="state.isTranslated"
                            buttonColor="text-lab-sc"
                            v-on:click="cancelTranslation"
                        v-bind:buttonText="$t('labels.show_untranslated')"></MarginalTextButton>
                        <MarginalTextButton
                            v-else
                            buttonColor="text-lab-sc"
                            v-on:click="translate"
                        v-bind:buttonText="state.isTranslating ? $t('labels.translating') : $t('labels.show_translation')"></MarginalTextButton>
                    </div>
                </div>
            </div>
        </div>
        
        <div v-if="! commentData.deleted" class="absolute top-2 right-2.5 leading-none">
            <div class="relative">
                <div class="opacity-30 hover:opacity-100">
                    <DropdownButton v-on:click.stop="toggleMainDropdown"></DropdownButton>
                </div>
                <div class="absolute top-full right-0 z-50" v-if="state.isDropdownOpen">
                    <DropdownMenu v-outside-click="toggleMainDropdown" v-on:click="toggleMainDropdown">
                        <DropdownReactions v-on:add="addReaction"></DropdownReactions>
                        <DropdownMenuItem v-on:click="openReactionsPicker" iconName="heart-rounded" v-bind:textLabel="$t('dd.add_reaction')"></DropdownMenuItem>
                        
                        <template v-if="commentData.meta.is_translatable">
                            <DropdownMenuItem v-if="state.isTranslated" v-on:click="cancelTranslation" iconName="translate-01" v-bind:textLabel="$t('labels.show_untranslated')"></DropdownMenuItem>
                            <DropdownMenuItem v-else v-on:click="translate" iconName="translate-01" v-bind:textLabel="$t('dd.translate')"></DropdownMenuItem>
                        </template>
                        <DropdownMenuItem v-on:click="replyToComment" iconName="pencil-line" v-bind:textLabel="$t('dd.comment.reply_to_comment', {name: commentData.relations.user.name})"></DropdownMenuItem>
                        <DropdownMenuItem v-on:click="copyCommentText" iconName="type-01" v-bind:textLabel="$t('dd.copy_text')"></DropdownMenuItem>
                        <Border/>
                        <!-- TODO: Add report comment -->
                        <!-- <DropdownMenuItem itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.comment.report_comment')"></DropdownMenuItem> -->
                        <template v-if="canDeleteComment">
                            <DropdownMenuItem v-on:click="deleteComment" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.comment.delete_comment')"></DropdownMenuItem>
                        </template>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, defineAsyncComponent, ref, reactive, computed } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { colibriTranslator } from '@/kernel/services/translator/index.js';
    
    import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
    import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';
    import DropdownMenu from '@D/components/general/dropdowns/parts/DropdownMenu.vue';
    import DropdownMenuItem from '@D/components/general/dropdowns/parts/DropdownMenuItem.vue';
    import DropdownReactions from '@D/components/general/dropdowns/parts/DropdownReactions.vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
    import MarginalTextButton from '@D/components/inter-ui/buttons/MarginalTextButton.vue';
    import CommentText from '@D/components/timeline/feed/parts/comment/parts/CommentText.vue';
	import TranslationService from '@D/components/general/TranslationService.vue';

    export default defineComponent({
        props: {
            commentData: {
                type: Object,
                default: {}
            }
        },
        emits: ['reply', 'delete'],
        setup: function(props, context) {
            const commentTranslatedContent = ref('');
            const commentData = ref(props.commentData);
            const state = reactive({
                isDropdownOpen: false,
                isReactionPickerOpen: false,
                isTranslated: false,
                isTranslating: false
            });

            const openReactionsPicker = function() {
                setTimeout(() => {
                    state.isReactionPickerOpen = true;
                }, 50);
            }

            const closeReactionsPicker = function() {
                state.isReactionPickerOpen = false;
            }

            return {
                state: state,
                commentData: commentData,
                closeReactionsPicker: closeReactionsPicker,
                openReactionsPicker: openReactionsPicker,
                commentTranslatedContent: commentTranslatedContent,
                hasReactions: computed(() => {
                    return commentData.value.relations.reactions.length;
                }),
                commentContent: computed(() => {
                    return state.isTranslated ? commentTranslatedContent.value : commentData.value.content;
                }),
                replyToComment: () => {
                    context.emit('reply', commentData.value.id);
                },
                toggleMainDropdown: () => {
                    state.isDropdownOpen = !state.isDropdownOpen;
                },
                copyCommentText: () => {
                    navigator.clipboard.writeText(commentData.value.content).then(() => {
                        toastSuccess(__t('toast.comment.comment_text_copied'), 1000);
                    });
                },
                canDeleteComment: computed(() => {
                    return commentData.value.meta.permissions.can_delete;
                }),
                deleteComment: () => {
                    context.emit('delete', commentData.value.id);
                },
                addReaction: (reactionId) => {
                    closeReactionsPicker();

                    colibriAPI().userTimeline().with({
                        unified_id: reactionId,
                        comment_id: commentData.value.id
                    }).sendTo('comment/reaction/add').then((response) => {
                        commentData.value.relations.reactions = response.data.data;
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
                    const translatedText = await colibriTranslator().translate(commentData.value.content);

                    if (translatedText) {
                        commentTranslatedContent.value = translatedText;
                        state.isTranslated = true;
                    }

                    state.isTranslating = false;
                },
                cancelTranslation: () => {
                    state.isTranslated = false;
                    commentTranslatedContent.value = '';
                }
            }
        },
        components: {
            ReactionsViewer: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/reactions/ReactionsViewer.vue');
            }),
            ReactionsPicker: defineAsyncComponent(() => {
                return import('@D/components/reactions/ReactionsPicker.vue');
            }),
            AvatarSmall: AvatarSmall,
            DropdownButton: DropdownButton,
            DropdownMenu: DropdownMenu,
            DropdownMenuItem: DropdownMenuItem,
            PrimaryIconButton: PrimaryIconButton,
            MarginalTextButton: MarginalTextButton,
            DropdownReactions: DropdownReactions,
            CommentText: CommentText,
            TranslationService: TranslationService
        }
    });
</script>