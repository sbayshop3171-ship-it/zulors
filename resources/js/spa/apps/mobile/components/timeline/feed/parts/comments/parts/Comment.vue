<template>
    <div class="flex px-4 py-2 select-none active:bg-fill-qt relative" v-longpress="openReactionsPicker">
        <div class="w-avatar-small shrink-0">
            <AvatarExtraSmall v-bind:avatarSrc="commentData.relations.user.avatar_url"></AvatarExtraSmall>
        </div>
        <div class="flex-1 overflow-hidden pl-2">
            <div class="block leading-none mb-1">
                <div class="inline-flex items-center gap-1">
                    <h3 class="text-par-n font-semibold text-lab-pr2">
                        {{ commentData.relations.user.name }}
                    </h3>
                    <span class="text-par-s text-lab-sc">
                        {{ commentData.date.time_ago }}
                    </span>
                </div>
            </div>
            <div v-if="commentData.has_parent" class="overflow-hidden mb-1">
                <div class="cursor-pointer">
                    <!-- TODO: Show parent comment in popup element if clicked -->
                    <template v-if="commentData.deleted">
                        <p class="text-cap-l text-lab-sc break-words italic">
                            {{ $t('labels.comment_was_deleted') }}
                        </p>
                    </template>
                    <template v-else>
                        <h3 class="inline-flex items-center gap-1 text-par-s font-semibold text-brand-900 leading-none">
                            <SvgIcon name="share-06" type="line" classes="size-icon-x-small transform -scale-x-100"></SvgIcon> {{ commentData.relations.parent.user.name }}
                        </h3>
                        <p class="text-par-n text-lab-pr3 break-words line-clamp-1">
                            {{ commentData.relations.parent.content }}
                        </p>
                    </template>
                </div>
            </div>
            <CommentText v-bind:commentContent="commentData.content"></CommentText>
            <div v-if="! commentData.deleted">
                <div class="mt-2" v-if="hasReactions">
                    <ReactionsViewer v-on:add="addReaction" v-bind:reactions="commentData.relations.reactions"></ReactionsViewer>
                </div>
            </div>
            <div v-if="! commentData.deleted">
                <MarginalTextButton
                    buttonColor="text-lab-sc/70"
                    v-on:click="reply"
                v-bind:buttonText="$t('labels.reply')"></MarginalTextButton>
            </div>
        </div>

        <div class="absolute top-1 right-4 opacity-40 active:opacity-100">
            <DropdownButton v-on:click="toggleMenu"></DropdownButton>
        </div>
    </div>

    <ActionSheet v-if="state.isMenuOpen" v-on:close="toggleMenu" v-bind:isMuted="true">
        <div v-on:click="toggleMenu">
            <ActionSheetGroup>
                <ActionSheetItem v-on:click="openReactionsPicker" iconName="heart-rounded" v-bind:textLabel="$t('dd.add_reaction')"></ActionSheetItem>
                <ActionSheetItem v-on:click="reply" iconName="pencil-line" v-bind:textLabel="$t('dd.comment.reply_to_comment', {name: commentData.relations.user.name})"></ActionSheetItem>
                <ActionSheetItem v-on:click="copyCommentText" iconName="type-01" v-bind:textLabel="$t('dd.copy_text')"></ActionSheetItem>
            </ActionSheetGroup>
    
            <!-- TODO: Add report comment -->
            <!-- <ActionSheetItem itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.comment.report_comment')"></ActionSheetItem> -->
            
            <template v-if="canDeleteComment">
                <div class="mt-4">
                    <ActionSheetGroup>
                        <ActionSheetItem v-on:click="deleteComment" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.comment.delete_comment')"></ActionSheetItem>
                    </ActionSheetGroup>
                </div>
            </template>
        </div>
    </ActionSheet>

    <ActionSheet v-if="state.isReactionPickerOpen" v-on:close="closeReactionsPicker">
        <ReactionsPicker v-on:add="addReaction"></ReactionsPicker>
    </ActionSheet>
</template>

<script>
    import { defineComponent, defineAsyncComponent, ref, reactive, computed } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    
    import AvatarExtraSmall from '@M/components/general/avatars/AvatarExtraSmall.vue';
    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
    import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
    import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
    import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
    import MarginalTextButton from '@M/components/inter-ui/buttons/MarginalTextButton.vue';
    import CommentText from '@M/components/timeline/feed/parts/comments/parts/CommentText.vue';
    import DropdownButton from '@M/components/general/dropdowns/DropdownButton.vue';

    export default defineComponent({
        props: {
            commentData: {
                type: Object,
                default: {}
            }
        },
        emits: ['reply', 'delete'],
        setup: function(props, context) {
            const commentData = ref(props.commentData);
            const state = reactive({
                isMenuOpen: false,
                isReactionPickerOpen: false
            });

            const openReactionsPicker = function() {
                state.isReactionPickerOpen = true;
            }

            const closeReactionsPicker = function() {
                state.isReactionPickerOpen = false;
            }

            return {
                state: state,
                commentData: commentData,
                closeReactionsPicker: closeReactionsPicker,
                openReactionsPicker: openReactionsPicker,
                hasReactions: computed(() => {
                    return commentData.value.relations.reactions.length;
                }),
                reply: () => {
                    colibriEventBus.emit('publication-comment:reply', {
                        commentData: commentData.value
                    });
                },
                toggleMenu: () => {
                    debounce(() => {
                        state.isMenuOpen = !state.isMenuOpen;
                    }, 50);
                },
                copyCommentText: () => {
                    try {
                        navigator.clipboard.writeText(commentData.value.content).then(() => {
                            toastSuccess(__t('toast.comment.comment_text_copied'), 1000);
                        });
                    } catch (error) {
                        toastError(error);
                    }
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
                }
            }
        },
        components: {
            ReactionsViewer: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/reactions/ReactionsViewer.vue');
            }),
            ReactionsPicker: defineAsyncComponent(() => {
                return import('@M/components/reactions/ReactionsPicker.vue');
            }),
            AvatarExtraSmall: AvatarExtraSmall,
            PrimaryIconButton: PrimaryIconButton,
            ActionSheet: ActionSheet,
            ActionSheetItem: ActionSheetItem,
            ActionSheetGroup: ActionSheetGroup,
            MarginalTextButton: MarginalTextButton,
            CommentText: CommentText,
            DropdownButton: DropdownButton
        }
    });
</script>