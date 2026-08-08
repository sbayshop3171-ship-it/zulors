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
            <PrimaryIconButton
                v-on:click="toggleStoryLike"
                iconName="heart-rounded"
                iconType="line"
                v-bind:buttonColor="hasLiked ? 'text-red-900' : 'text-white'"
                v-bind:hoverText="hasLiked ? 'hover:text-red-900' : 'hover:text-white'"
            ></PrimaryIconButton>
        </div>
    </div>
</template>

<script>
    import { computed, defineComponent, inject, reactive } from 'vue';
    import { useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useStoriesStore } from '@M/store/stories/stories.store.js';

    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        setup: function() {
            const playerState = inject('playerState');
            const storiesStore = useStoriesStore();
            const router = useRouter();
            const state = reactive({
                sendingMessage: false,
                togglingLike: false
            });

            return {
                playerState: playerState,
                state: state,
                hasLiked: computed(() => {
                    return !! playerState.frameData?.activity?.has_liked;
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
                toggleStoryLike: async () => {
                    if(state.togglingLike || ! playerState.frameData?.id) {
                        return;
                    }

                    state.togglingLike = true;

                    await storiesStore.toggleStoryLike(playerState.frameData.id).catch((error) => {
                        alert(error.message);
                    }).finally(() => {
                        state.togglingLike = false;
                    });
                }
            }
        },
        components: {
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>
