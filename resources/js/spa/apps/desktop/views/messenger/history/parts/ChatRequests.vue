<template>
    <ChatCallout iconName="message-question-circle" v-bind:title="$t('chat.chat_requests.title')" v-bind:description="$t('chat.chat_requests.description')"></ChatCallout>
    <Border></Border>
	<div v-if="state.isLoading" class="block">
		<ChatItemSkeleton v-for="i in 7"></ChatItemSkeleton>
	</div>
	<template v-else-if="isEmptyList">
		<div class="py-24 text-center">
			<p class="text-par-s text-lab-sc">
				{{  $t('chat.no_chat_requests') }}
			</p>
		</div>
	</template>
	<template v-else>
		<RequestItem v-for="requestData in chatRequests" v-bind:requestData="requestData" v-bind:key="requestData.id"></RequestItem>
	</template>
</template>

<script>
    import { defineComponent, onMounted, reactive, computed } from 'vue';
    
    import { useInboxStore } from '@D/store/chats/inbox.store.js';

    import ChatItemSkeleton from '@D/views/messenger/history/parts/ChatItemSkeleton.vue';
	import RequestItem from '@D/views/messenger/history/parts/RequestItem.vue';
    import ChatCallout from '@D/views/messenger/parts/ChatCallout.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: false
            });

            const inboxStore = useInboxStore();

			const chatRequests = computed(() => {
				return inboxStore.chatRequests;
			});

            onMounted(() => {
                let hasCachedRequests = inboxStore.hydrateChatRequestsCache() || chatRequests.value.length > 0;

                state.isLoading = ! hasCachedRequests;

				inboxStore.fetchChatRequests({
                    preferCache: true
                }).finally(() => {
                    state.isLoading = false;
                });
            });

            return {
                state: state,
				chatRequests: chatRequests,
				isEmptyList: computed(() => {
                    return ! chatRequests.value.length;
                }),
            }
        },
        components: {
            ChatItemSkeleton: ChatItemSkeleton,
			RequestItem: RequestItem,
			ChatCallout: ChatCallout
        }
    });
</script>
