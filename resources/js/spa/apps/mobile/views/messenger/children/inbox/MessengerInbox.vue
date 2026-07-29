<template>
	<div class="sticky top-0 popup-background-sc z-10">
		<div class="block">
			<ToastNotification></ToastNotification>
			<Toolbar v-on:close="leaveMessenger" v-bind:title="$t('labels.messages')"></Toolbar>
		</div>
	</div>
	<div v-if="! state.searchMode" class="border-b border-b-bord-pr">
		<ContentTabs cols="4">
			<TabsButton v-bind:isActive="state.activeTab === 'chats'" v-on:click="state.activeTab = 'chats'">
				{{ $t('chat.tabs.chats') }}
			</TabsButton>
			<TabsButton v-bind:isActive="state.activeTab === 'groups'" v-on:click="state.activeTab = 'groups'">
				{{ $t('chat.tabs.groups') }}
			</TabsButton>
			<TabsButton v-bind:isActive="state.activeTab === 'requests'" v-on:click="state.activeTab = 'requests'">
				{{ $t('chat.tabs.requests') }} <template v-if="requestsCount">({{ requestsCount }})</template>
			</TabsButton>
			<TabsButton v-bind:isActive="state.activeTab === 'archived'" v-on:click="state.activeTab = 'archived'">
				{{ $t('chat.tabs.archived') }}
			</TabsButton>
		</ContentTabs>
	</div>
	<template v-if="state.searchMode || state.activeTab === 'chats' || state.activeTab === 'groups'">
		<ChatsHistory
			v-bind:historyType="state.searchMode ? 'chats' : state.activeTab"
			v-bind:searchCancelTick="state.searchCancelTick"
			v-on:search-mode-change="handleSearchModeChange"
		></ChatsHistory>
	</template>
	<template v-else-if="state.activeTab === 'archived'">
		<ChatsArchive></ChatsArchive>
	</template>
	<template v-else-if="state.activeTab === 'requests'">
		<ChatRequests></ChatRequests>
	</template>
</template>

<script>
	import { defineComponent, reactive, computed, onMounted } from 'vue';
	import { useRouter } from 'vue-router';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';
	import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import ContentTabs from '@M/components/general/tabs/content/ContentTabs.vue';
    import TabsButton from '@M/components/general/tabs/content/parts/TabsButton.vue';
	import ToastNotification from '@M/components/notifications/toast/ToastNotification.vue';
	import ChatsHistory from '@M/views/messenger/children/inbox/parts/ChatsHistory.vue';
	import ChatsArchive from '@M/views/messenger/children/inbox/parts/ChatsArchive.vue';
	import ChatRequests from '@M/views/messenger/children/inbox/parts/ChatRequests.vue';

	export default defineComponent({
		setup: function() {
			const router = useRouter();
			const inboxStore = useInboxStore();
			const state = reactive({
				activeTab: 'chats',
				searchMode: false,
				searchCancelTick: 0
			});
			const refreshRequestCount = () => {
				return inboxStore.fetchChatRequestsCount({
					force: true,
					preferCache: false
				});
			};

			useInstantRevalidation(refreshRequestCount, {
				interval: 15000,
				minDelay: 1500
			});

			onMounted(() => {
				refreshRequestCount();
			});
			
			return {
				state: state,
				leaveMessenger: () => {
					if(state.searchMode) {
						state.activeTab = 'chats';
						state.searchMode = false;
						state.searchCancelTick++;

						return;
					}

					router.push({ name: 'home_index' });
				},
				handleSearchModeChange: (isSearchMode) => {
					state.activeTab = 'chats';
					state.searchMode = isSearchMode;
				},
				requestsCount: computed(() => {
                    return inboxStore.chatRequestsCount;
                }),
			};
		},
		components: {
			Toolbar: Toolbar,
			ContentTabs: ContentTabs,
			TabsButton: TabsButton,
			ToastNotification: ToastNotification,
			ChatsHistory: ChatsHistory,
			ChatsArchive: ChatsArchive,
			ChatRequests: ChatRequests
		}
	});
</script>
