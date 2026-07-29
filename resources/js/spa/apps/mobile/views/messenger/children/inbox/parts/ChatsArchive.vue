<template>
	<div v-if="state.isLoading" class="block">
		<ChatItemSkeleton v-for="i in 7"></ChatItemSkeleton>
	</div>
	<div v-else class="block">
        <div class="px-4 pt-4">
            <QuickSearch v-on:cancel="handleSearchCancel" v-model.lazy="chatsSearchQuery" v-bind:placeholder="$t('chat.search')"></QuickSearch>
        </div>
        <ChatCallout iconName="archive" v-bind:title="$t('chat.archived_chats.title')" v-bind:description="$t('chat.archived_chats.description')"></ChatCallout>
        <Border></Border>
		<template v-if="isEmptyArchive">
			<div class="py-24 text-center">
				<p class="text-par-s text-lab-sc">
					{{  $t('chat.no_chat_archive') }}
				</p>
			</div>
		</template>
		<template v-else>
			<template v-if="isSearching">
				<ChatItem v-if="searchResults.length" v-for="chatData in searchResults" v-bind:chatData="chatData" v-bind:key="chatData.chat_id"></ChatItem>

				<div v-else class="py-24 text-center">
					<p class="text-par-s text-lab-sc">
						{{  $t('chat.no_chats_found') }}
					</p>
				</div>
			</template>
			<template v-else>
                
				<ChatItem v-for="chatData in chatsArchive" v-bind:chatData="chatData"></ChatItem>
			</template>
		</template>
	</div>
</template>

<script>
    import { defineComponent, onMounted, ref, computed, reactive, watch } from 'vue';
    
    import { useInboxStore } from '@M/store/chats/inbox.store.js';

    import ChatItemSkeleton from '@M/views/messenger/children/inbox/parts/ChatItemSkeleton.vue';
    import ChatItem from '@M/views/messenger/children/inbox/parts/ChatItem.vue';
    import QuickSearch from '@M/components/general/search/QuickSearch.vue';
    import ChatCallout from '@M/views/messenger/children/chat/parts/ChatCallout.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: false
            });

            const chatsSearchQuery = ref('');
            const inboxStore = useInboxStore();
            const searchResults = ref([]);
            const chatsArchive = computed(() => {
                return inboxStore.chatsArchive;
            });
            
            const isSearching = computed(() => {
                return chatsSearchQuery.value.length > 0;
            });

            watch(chatsSearchQuery, (queryValue) => {
                searchResults.value = chatsArchive.value.filter((item) => {
					return item.chat_info.name.toLowerCase().includes(queryValue.toLowerCase());
                });
            });

            onMounted(() => {
                let hasCachedArchive = inboxStore.hydrateChatsArchiveCache() || chatsArchive.value.length > 0;

                state.isLoading = ! hasCachedArchive;

                inboxStore.fetchChatsArchive({
                    preferCache: true
                }).finally(() => {
                    state.isLoading = false;
                });
            });

            return {
                state: state,
                isSearching: isSearching,
                chatsArchive: chatsArchive,
                searchResults: searchResults,
                chatsSearchQuery: chatsSearchQuery,
                ChatCallout: ChatCallout,
                isEmptyArchive: computed(() => {
                    return ! chatsArchive.value.length;
                }),
                handleSearchCancel: () => {
                    chatsSearchQuery.value = '';
                    searchResults.value = [];
                }
            }
        },
        components: {
            ChatItem: ChatItem,
            ChatItemSkeleton: ChatItemSkeleton,
            QuickSearch: QuickSearch,
            ChatCallout: ChatCallout
        }
    });
</script>
