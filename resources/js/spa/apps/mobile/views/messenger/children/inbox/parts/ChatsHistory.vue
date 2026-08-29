<template>
	<div class="px-4 pt-4" v-on:click="openSearch">
		<QuickSearch v-model="searchQuery" v-on:cancel="cancelSearch" v-bind:placeholder="searchPlaceholder"></QuickSearch>
	</div>

	<template v-if="isSearchPanelOpen">
		<div class="pb-4">
			<template v-if="hasSearchQuery">
				<div v-if="state.isSearching" class="py-12 text-center">
					<p class="text-par-s text-lab-sc">
						{{ $t('labels.loading') }}...
					</p>
				</div>
				<template v-else>
					<div v-if="searchResults.chats.length" class="mb-2">
						<h4 class="px-4 py-3 text-par-s font-semibold text-lab-sc">
							{{ $t('chat.search_chats') }}
						</h4>
						<ChatItem v-for="chatData in searchResults.chats" v-bind:chatData="chatData" v-bind:key="chatData.chat_id" v-on:click="storeChatResultRecent(chatData)"></ChatItem>
					</div>

					<div v-if="searchResults.users.length" class="mb-2">
						<h4 class="px-4 py-3 text-par-s font-semibold text-lab-sc">
							{{ $t('chat.search_people') }}
						</h4>
						<SearchUserItem v-for="userData in searchResults.users" v-bind:key="userData.id" v-bind:userData="userData" v-on:select="openUserChat"></SearchUserItem>
					</div>

					<div v-if="isEmptySearchResults" class="py-12 text-center">
						<p class="text-par-s text-lab-sc">
							{{  $t('chat.no_chats_found') }}
						</p>
					</div>
				</template>
			</template>

			<template v-else>
			<div v-if="state.isBootstrapLoading" class="py-12 text-center">
				<p class="text-par-s text-lab-sc">
					{{ $t('labels.loading') }}...
				</p>
			</div>
				<template v-else>
					<div v-if="recentUsers.length" class="mb-2">
						<div class="flex items-center justify-between gap-4 px-4 py-3">
							<h4 class="text-par-s font-semibold text-lab-sc">
								{{ $t('chat.search_recent') }}
							</h4>
							<button v-if="recentUsers.length > 3" type="button" class="text-par-s font-medium text-brand-900" v-on:click="state.showAllRecents = ! state.showAllRecents">
								{{ state.showAllRecents ? $t('labels.show_less') : $t('chat.search_see_all') }}
							</button>
						</div>
						<SearchUserItem v-for="userData in visibleRecentUsers" v-bind:key="userData.id" v-bind:userData="userData" removable v-on:select="openUserChat" v-on:remove="removeRecent"></SearchUserItem>
					</div>

					<div v-if="suggestionUsers.length" class="mb-2">
						<h4 class="px-4 py-3 text-par-s font-semibold text-lab-sc">
							{{ $t('chat.search_more_suggestions') }}
						</h4>
						<SearchUserItem v-for="userData in suggestionUsers" v-bind:key="userData.id" v-bind:userData="userData" v-on:select="openUserChat"></SearchUserItem>
					</div>

					<div v-if="isEmptyBootstrap" class="py-12 text-center">
						<p class="text-par-s text-lab-sc">
							{{  $t('chat.no_chats_found') }}
						</p>
					</div>
				</template>
			</template>
		</div>
	</template>

	<template v-else-if="isEmptyInbox">
		<div class="py-16 text-center">
			<p class="text-par-s text-lab-sc">
				{{  $t('chat.no_chat_history') }}
			</p>
		</div>
	</template>

	<template v-else>
		<template v-if="isSearching">
			<ChatItem v-if="localSearchResults.length > 0" v-for="chatData in localSearchResults" v-bind:chatData="chatData" v-bind:key="chatData.chat_id"></ChatItem>
			<div v-else class="py-16 text-center">
				<p class="text-par-s text-lab-sc">
					{{  $t('chat.no_chats_found') }}
				</p>
			</div>
		</template>
		<template v-else>
			<ChatItem v-for="chatData in chatsHistory" v-bind:chatData="chatData" v-bind:key="chatData.chat_id"></ChatItem>
		</template>
	</template>
</template>

<script>
	import { defineComponent, reactive, watch, onMounted, ref, computed } from 'vue';
	import { useRouter } from 'vue-router';

	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';
	import { useChatStore } from '@M/store/chats/chat.store.js';
	import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';

	import ChatItemSkeleton from '@M/views/messenger/children/inbox/parts/ChatItemSkeleton.vue';
	import ChatItem from '@M/views/messenger/children/inbox/parts/ChatItem.vue';
	import SearchUserItem from '@M/views/messenger/children/inbox/parts/SearchUserItem.vue';
	import QuickSearch from '@M/components/general/search/QuickSearch.vue';

	export default defineComponent({
		props: {
			historyType: {
				type: String,
				default: 'chats'
			},
			searchCancelTick: {
				type: Number,
				default: 0
			}
		},
		emits: ['search-mode-change'],
		setup: function(props, context) {
			const searchQuery = ref('');
			const localSearchResults = ref([]);
			const searchResults = ref({
				chats: [],
				users: []
			});
			const inboxStore = useInboxStore();
			const chatStore = useChatStore();
			const router = useRouter();
			const hasInitialHistory = inboxStore.chatsHistory.length > 0 || inboxStore.hydrateChatsHistoryCache();
			let searchDebounceTimer = null;

			const state = reactive({
				isLoading: ! hasInitialHistory,
				isSearchOpen: false,
				isSearching: false,
				isBootstrapLoading: false,
				bootstrapLoaded: false,
				showAllRecents: false,
				openingUserId: null,
				isClosingSearch: false
			});

			const chatsHistory = computed(() => {
                if(props.historyType == 'groups') {
					return inboxStore.chatsHistory.filter((chatItem) => {
						return chatItem.type == 'group';
					});
				}
				
				return inboxStore.chatsHistory;
            });

			const isDirectHistory = computed(() => {
				return props.historyType === 'chats';
			});

			const refreshHistory = async () => {
				await Promise.allSettled([
					inboxStore.fetchChatsHistory({
						force: true,
						preferCache: false
					}),
					inboxStore.fetchUnreadCount()
				]);
			};

			useInstantRevalidation(refreshHistory, {
				interval: 10000,
				minDelay: 1500
			});

			const hasSearchQuery = computed(() => {
				return searchQuery.value.trim().length > 0;
			});

			const recentUsers = computed(() => {
				return inboxStore.messengerSearch.recents;
			});

			const suggestionUsers = computed(() => {
				return inboxStore.messengerSearch.suggestions;
			});

			const fetchSearchBootstrap = async () => {
				if(isDirectHistory.value) {
					let hasCachedBootstrap = inboxStore.hydrateMessengerSearchCache();

					state.isBootstrapLoading = ! hasCachedBootstrap && ! recentUsers.value.length && ! suggestionUsers.value.length;

					await inboxStore.fetchSearchBootstrap({
						preferCache: true
					});

					state.bootstrapLoaded = true;
					state.isBootstrapLoading = false;
				}
			};

			watch(searchQuery, (queryValue) => {
				if(searchDebounceTimer) {
					window.clearTimeout(searchDebounceTimer);
				}

				if(! isDirectHistory.value) {
					localSearchResults.value = chatsHistory.value.filter((item) => {
						return item.chat_info.name.toLowerCase().includes(queryValue.toLowerCase());
					});

					return;
				}

				if(state.isClosingSearch) {
					return;
				}

				state.isSearchOpen = true;
				context.emit('search-mode-change', true);
				searchResults.value = {
					chats: [],
					users: []
				};

				if(queryValue.trim().length === 0) {
					state.isSearching = false;
					return;
				}

				state.isSearching = true;

				searchDebounceTimer = window.setTimeout(async () => {
					const currentSearchQuery = queryValue.trim();

					const responseData = await inboxStore.searchMessenger(currentSearchQuery);

					if(searchQuery.value.trim() === currentSearchQuery) {
						searchResults.value = {
							chats: responseData.chats || [],
							users: responseData.users || []
						};
					}

					state.isSearching = false;
				}, 250);
            });

			watch(() => {
				return props.searchCancelTick;
			}, () => {
				cancelSearchToList();
			});

				onMounted(() => {
					let hasCachedHistory = inboxStore.hydrateChatsHistoryCache() || chatsHistory.value.length > 0;

					state.isLoading = ! hasCachedHistory;

					refreshHistory().finally(() => {
						state.isLoading = false;
					});

					if(isDirectHistory.value && inboxStore.hydrateMessengerSearchCache()) {
						state.bootstrapLoaded = true;
					}
            });
			
			return {
				searchQuery: searchQuery,
				state: state,
				chatsHistory: chatsHistory,
				localSearchResults: localSearchResults,
				searchResults: searchResults,
				hasSearchQuery: hasSearchQuery,
				recentUsers: recentUsers,
				suggestionUsers: suggestionUsers,
				visibleRecentUsers: computed(() => {
					return (state.showAllRecents) ? recentUsers.value : recentUsers.value.slice(0, 3);
				}),
				isSearchPanelOpen: computed(() => {
					return isDirectHistory.value && state.isSearchOpen;
				}),
				isEmptySearchResults: computed(() => {
					return (! searchResults.value.chats.length && ! searchResults.value.users.length);
				}),
				isEmptyBootstrap: computed(() => {
					return (! recentUsers.value.length && ! suggestionUsers.value.length);
				}),
				isSearching: computed(() => {
					return searchQuery.value.length > 0;
				}),
				isEmptyInbox: computed(() => {
                    return chatsHistory.value.length === 0;
                }),
				cancelSearch: () => {
					cancelSearchToList();
				},
				openSearch: async () => {
					if(state.isClosingSearch) {
						return;
					}

					if(isDirectHistory.value) {
						state.isSearchOpen = true;
						context.emit('search-mode-change', true);

						if(! state.bootstrapLoaded) {
							fetchSearchBootstrap();
						}
					}
				},
				removeRecent: async (userData) => {
					await inboxStore.deleteSearchRecent(userData.id).catch(() => {});
				},
				storeChatResultRecent: (chatData) => {
					if(! chatData.is_group && chatData.chat_info && chatData.chat_info.id) {
						inboxStore.storeSearchRecent(chatData.chat_info.id).catch(() => {});
					}
				},
				openUserChat: async (userData) => {
					if(state.openingUserId) {
						return;
					}

					state.openingUserId = userData.id;

					try {
						await inboxStore.storeSearchRecent(userData.id);

						await colibriAPI().messenger().with({
							user_id: userData.id
						}).sendTo('chats/create').then(async (response) => {
							let chatData = response.data.data;

							if(chatData.chat) {
								chatStore.primeChatDataFromInbox(chatData.chat);
							}

							inboxStore.fetchChatsHistory({
								force: true,
								preferCache: false
							});

							router.push({
								name: 'messenger_chat',
								params: {
									chat_id: chatData.chat_id
								}
							});
						});
					}
					catch(error) {
						alert(error.response?.data?.message || __t('chat.blocked_user_message'));
					}
					finally {
						state.openingUserId = null;
					}
				},
				searchPlaceholder: computed(() => {
					return __t('chat.search');
				})
			};

			function cancelSearchToList() {
				state.isClosingSearch = true;

				if(searchDebounceTimer) {
					window.clearTimeout(searchDebounceTimer);
				}

				searchQuery.value = '';
				localSearchResults.value = [];
				searchResults.value = {
					chats: [],
					users: []
				};
				state.isSearchOpen = false;
				state.isSearching = false;
				state.showAllRecents = false;
				context.emit('search-mode-change', false);

				window.setTimeout(() => {
					state.isClosingSearch = false;
				}, 0);
			}
		},
		components: {
			ChatItemSkeleton: ChatItemSkeleton,
			ChatItem: ChatItem,
			SearchUserItem: SearchUserItem,
			QuickSearch: QuickSearch
		}
	});
</script>
