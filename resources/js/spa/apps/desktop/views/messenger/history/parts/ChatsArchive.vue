<template>
	<div class="block">
        <div class="px-4 mb-4">
            <QuickSearch v-on:cancel="handleSearchCancel" v-model.lazy="chatsSearchQuery" v-bind:placeholder="$t('chat.search')"></QuickSearch>
        </div>
        <Border></Border>
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
				<ChatItem v-if="searchResults.length" v-for="chatData in searchResults" v-bind:chatData="chatData"></ChatItem>

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
    
    import { useInboxStore } from '@D/store/chats/inbox.store.js';

    import ChatItemSkeleton from '@D/views/messenger/history/parts/ChatItemSkeleton.vue';
    import ChatItem from '@D/views/messenger/history/parts/ChatItem.vue';
    import QuickSearch from '@D/components/general/search/QuickSearch.vue';
    import ChatCallout from '@D/views/messenger/parts/ChatCallout.vue';

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
                    if(item.chat_info.name.toLowerCase().includes(queryValue.toLowerCase())) {
                        return true;
                    }
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
            ChatItemSkeleton: ChatItemSkeleton,
            ChatCallout: ChatCallout,
            QuickSearch: QuickSearch,
            ChatItem: ChatItem
        }
    });
</script>
