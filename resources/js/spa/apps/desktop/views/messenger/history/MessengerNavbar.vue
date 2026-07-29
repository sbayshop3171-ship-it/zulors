<template>
    <SidebarContainer>
        <template v-slot:sidebarTitle>
            <div class="px-4 mb-4">
                <PageTitle
                    v-bind:hasBack="true"
                    v-bind:backHome="true"
                    v-bind:navigateBack="! state.searchMode"
                    v-on:back="closeSearchMode"
                v-bind:titleText="$t('labels.messages')"></PageTitle>
            </div>

            <div v-if="! isWSEstablished">
                <WSConnectionAlert></WSConnectionAlert>
            </div>
        </template>

        <template v-slot:sidebarBody>
            <div v-if="! state.searchMode" class="border-b border-b-bord-card mb-4">
                <ContentTabs>
                    <TabsButton v-on:click="state.activeTab = 'chats'" v-bind:isActive="state.activeTab === 'chats'">
                        {{ $t('chat.tabs.chats') }}
                    </TabsButton>
                    <TabsButton v-on:click="state.activeTab = 'groups'" v-bind:isActive="state.activeTab === 'groups'">
                        {{ $t('chat.tabs.groups') }}
                    </TabsButton>
                    <TabsButton v-on:click="state.activeTab = 'requests'" v-bind:isActive="state.activeTab === 'requests'">
                        {{ $t('chat.tabs.requests') }} <template v-if="requestsCount">({{ requestsCount }})</template>
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
            <template v-else-if="state.activeTab === 'requests'">
                <ChatRequests></ChatRequests>
            </template>
        </template>
    </SidebarContainer>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted } from 'vue';
    import { useInboxStore } from '@D/store/chats/inbox.store.js';
    import { useWSConnectionStatus } from '@/kernel/vue/composables/ws-status/index.js';
    import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SidebarContainer from '@D/components/general/contextual-sidebar/SidebarContainer.vue';
    import WSConnectionAlert from '@D/views/messenger/history/parts/WSConnectionAlert.vue';
    import ContentTabs from '@D/components/general/tabs/content/ContentTabs.vue';
    import TabsButton from '@D/components/general/tabs/content/parts/TabsButton.vue';
    import ChatsHistory from '@D/views/messenger/history/parts/ChatsHistory.vue';
    import ChatRequests from '@D/views/messenger/history/parts/ChatRequests.vue';

    export default defineComponent({
        setup: function() {

            const inboxStore = useInboxStore();
            const { isWSEstablished } = useWSConnectionStatus();
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
                requestsCount: computed(() => {
                    return inboxStore.chatRequestsCount;
                }),
                state: state,
                isWSEstablished: isWSEstablished,
                handleSearchModeChange: (isSearchMode) => {
                    state.activeTab = 'chats';
                    state.searchMode = isSearchMode;
                },
                closeSearchMode: () => {
                    state.activeTab = 'chats';
                    state.searchMode = false;
                    state.searchCancelTick++;
                },
            }
        },
        components: {
            PageTitle: PageTitle,
            SidebarContainer: SidebarContainer,
            WSConnectionAlert: WSConnectionAlert,
            ContentTabs: ContentTabs,
            ChatsHistory: ChatsHistory,
            ChatRequests: ChatRequests,
            TabsButton: TabsButton
        }
    });
</script>
