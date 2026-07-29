<template>
    <div class="h-screen overflow-y-auto bg-bg-pr">
        <div v-if="state.isLoading" class="flex h-full items-center justify-center">
            <div class="colibri-primary-animation"></div>
        </div>

        <div v-else class="mx-auto min-h-full max-w-[560px] border-x border-bord-pr">
            <ChatInfo v-on:close="goBack"></ChatInfo>
        </div>
    </div>
</template>

<script>
    import { defineComponent, reactive, onMounted } from 'vue';
    import { useRoute, useRouter } from 'vue-router';
    import { useChatStore } from '@D/store/chats/chat.store.js';

    import ChatInfo from '@D/views/messenger/children/chat/parts/ChatInfo.vue';

    export default defineComponent({
        setup: function() {
            const route = useRoute();
            const router = useRouter();
            const chatStore = useChatStore();
            const state = reactive({
                isLoading: true
            });

            const goBack = () => {
                router.push({
                    name: 'messenger_chat',
                    params: { chat_id: route.params.chat_id }
                });
            };

            onMounted(async () => {
                try {
                    if(chatStore.chatId !== route.params.chat_id || ! chatStore.chatData?.chat_info) {
                        await chatStore.fetchChatData(route.params.chat_id);
                    }

                    state.isLoading = false;
                } catch (error) {
                    router.push({
                        name: 'messenger_inbox'
                    });
                }
            });

            return {
                state: state,
                goBack: goBack
            };
        },
        components: {
            ChatInfo: ChatInfo
        }
    });
</script>
