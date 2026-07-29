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
            <StoryShareButton></StoryShareButton>
        </div>
    </div>
</template>

<script>
    import { defineComponent, inject, reactive } from 'vue';
    import { useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import StoryShareButton from '@D/views/stories/parts/StoryShareButton.vue';

    export default defineComponent({
        setup: function() {
            // TODO: Implement reply to story.
            // 1. Open a message form.
            // 2. Send a message to the story author.
            // 3. Attach a story snapshot to the message. Use LQIP for the image. Base64 encoded image.
            // 4. Redirect to the chat page.
            
            const playerState = inject('playerState');
            const router = useRouter();
            const state = reactive({
                sendingMessage: false
            });

            return {
                playerState: playerState,
                state: state,
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
                        });
                    }
                }
            }
        },
        components: {
            StoryShareButton: StoryShareButton
        }
    });
</script>