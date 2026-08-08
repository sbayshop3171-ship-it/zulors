<template>
    <div class="flex items-center gap-2 px-4">
        <div
            v-on:click="replyToStory"
            class="smoothing border border-white px-4 h-10 flex items-center overflow-hidden rounded-full flex-1"
            v-bind:class="[state.sendingMessage ? 'opacity-40 cursor-not-allowed' : 'opacity-80 cursor-pointer active:opacity-100']"
        >
            <span class="text-par-s text-white leading-none block truncate">
                {{ state.sendingMessage ? $t('story.reply_story_author_sending') : $t('story.reply_story_author', { name: playerState.storyAuthor.name }) }}
            </span>
        </div>
        <div class="shrink-0">
            <button
                type="button"
                v-on:click="toggleReactionPicker"
                class="outline-hidden cursor-pointer inline-flex min-h-11 min-w-11 touch-manipulation select-none active:bg-fill-tr items-center justify-center rounded-full leading-zero"
                v-bind:class="[selectedReactionImageUrl ? 'text-white' : hasLiked ? 'text-red-900' : 'text-white']"
            >
                <img v-if="selectedReactionImageUrl" class="size-6" v-bind:src="selectedReactionImageUrl" alt="Reaction">
                <SvgIcon v-else name="heart-rounded" type="line" classes="size-6"></SvgIcon>
            </button>
        </div>
    </div>
    <PublicationReactions
        v-if="state.showReactionPicker"
        v-on:close="closeReactionPicker"
        v-on:add="reactToStory"
    ></PublicationReactions>
</template>

<script>
    import { computed, defineComponent, inject, reactive } from 'vue';
    import { useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useStoriesStore } from '@M/store/stories/stories.store.js';

    import PublicationReactions from '@M/components/timeline/feed/parts/reactions/PublicationReactions.vue';

    export default defineComponent({
        setup: function() {
            const playerState = inject('playerState');
            const storiesStore = useStoriesStore();
            const router = useRouter();
            const state = reactive({
                sendingMessage: false,
                togglingReaction: false,
                showReactionPicker: false
            });

            return {
                playerState: playerState,
                state: state,
                hasLiked: computed(() => {
                    return !! playerState.frameData?.activity?.has_liked;
                }),
                selectedReactionImageUrl: computed(() => {
                    return playerState.frameData?.activity?.reaction_image_url || null;
                }),
                replyToStory: async () => {
                    if(state.sendingMessage) {
                        return false;
                    }

                    state.sendingMessage = true;

                    await colibriAPI().messenger().with({
                        user_id: playerState.storyAuthor.id
                    }).sendTo('chats/create').then((response) => {
                        const chatData = response.data.data;

                        router.push({
                            name: 'messenger_chat',
                            params: {
                                chat_id: chatData.chat_id
                            }
                        });
                    }).catch((error) => {
                        if(error.response) {
                            alert(error.response.data.message);
                        }
                    }).finally(() => {
                        state.sendingMessage = false;
                    });
                },
                closeReactionPicker: () => {
                    state.showReactionPicker = false;
                },
                toggleReactionPicker: () => {
                    state.showReactionPicker = ! state.showReactionPicker;
                },
                reactToStory: async (reactionId) => {
                    if(state.togglingReaction || ! playerState.frameData?.id) {
                        return;
                    }

                    state.togglingReaction = true;

                    await storiesStore.toggleStoryReaction(playerState.frameData.id, reactionId).then(() => {
                        state.showReactionPicker = false;
                    }).catch((error) => {
                        alert(error.message);
                    }).finally(() => {
                        state.togglingReaction = false;
                    });
                }
            }
        },
        components: {
            PublicationReactions: PublicationReactions
        }
    });
</script>
