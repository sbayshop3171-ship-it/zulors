<template>
    <div class="flex items-center gap-2 px-3">
        <div v-on:click="replyToStory"
            class="smoothing border border-white px-4 h-8 flex items-center overflow-hidden rounded-full flex-1" 
        v-bind:class="[state.sendingMessage ? 'opacity-40 cursor-not-allowed' : 'opacity-70 cursor-pointer hover:opacity-100']">
            <span class="text-cap-l text-white leading-none block truncate">
                {{ state.sendingMessage ? $t('story.reply_story_author_sending') : $t('story.reply_story_author', { name: playerState.storyAuthor.name }) }}
            </span>
        </div>
        <div class="shrink-0">
            <div ref="reactionMenuRef" class="relative">
                <button
                    type="button"
                    v-on:click.stop="toggleReactionPicker"
                    class="outline-hidden transition-transform duration-300 inline-flex items-center justify-center rounded-full leading-none size-8 hover:bg-fill-tr"
                    v-bind:class="[selectedReactionImageUrl ? 'text-white' : hasLiked ? 'text-red-900' : 'text-white']"
                >
                    <img v-if="selectedReactionImageUrl" class="size-5" v-bind:src="selectedReactionImageUrl" alt="Reaction">
                    <SvgIcon v-else name="heart-rounded" type="line" classes="size-6"></SvgIcon>
                </button>
                <div v-if="state.showReactionPicker" v-on:click.stop class="absolute bottom-full right-0 mb-2 z-20">
                    <ReactionsPicker v-on:add="reactToStory"></ReactionsPicker>
                </div>
            </div>
        </div>
        <div class="shrink-0">
            <StoryShareButton></StoryShareButton>
        </div>
    </div>
</template>

<script>
    import { computed, defineComponent, inject, onMounted, onUnmounted, reactive, ref } from 'vue';
    import { useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useStoriesStore } from '@D/store/stories/stories.store.js';

    import ReactionsPicker from '@D/components/reactions/ReactionsPicker.vue';
    import StoryShareButton from '@D/views/stories/parts/StoryShareButton.vue';

    export default defineComponent({
        setup: function() {
            // TODO: Implement reply to story.
            // 1. Open a message form.
            // 2. Send a message to the story author.
            // 3. Attach a story snapshot to the message. Use LQIP for the image. Base64 encoded image.
            // 4. Redirect to the chat page.
            
            const playerState = inject('playerState');
            const storiesStore = useStoriesStore();
            const router = useRouter();
            const state = reactive({
                sendingMessage: false,
                togglingReaction: false,
                showReactionPicker: false
            });
            const reactionMenuRef = ref(null);

            const closeReactionPicker = () => {
                state.showReactionPicker = false;
            };

            const handleOutsideClick = (event) => {
                if(! state.showReactionPicker || ! reactionMenuRef.value) {
                    return;
                }

                if(! reactionMenuRef.value.contains(event.target)) {
                    closeReactionPicker();
                }
            };

            onMounted(() => {
                window.addEventListener('click', handleOutsideClick);
            });

            onUnmounted(() => {
                window.removeEventListener('click', handleOutsideClick);
            });

            return {
                playerState: playerState,
                state: state,
                reactionMenuRef: reactionMenuRef,
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
                    else {
                        state.sendingMessage = true;

                        await colibriAPI().messenger().with({
                            user_id: playerState.storyAuthor.id
                        }).sendTo('chats/create').then((response) => {
                            let chatData = response.data.data;
    
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
                    }
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
                        closeReactionPicker();
                    }).catch((error) => {
                        alert(error.message);
                    }).finally(() => {
                        state.togglingReaction = false;
                    });
                }
            }
        },
        components: {
            ReactionsPicker: ReactionsPicker,
            StoryShareButton: StoryShareButton
        }
    });
</script>
