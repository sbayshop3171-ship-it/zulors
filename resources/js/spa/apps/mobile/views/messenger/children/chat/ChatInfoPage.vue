<template>
	<div class="fixed inset-0 z-50 flex flex-col bg-fill-fv">
		<div class="shrink-0 bg-bg-pr">
			<Toolbar v-bind:title="$t('labels.information')" v-on:close="goBack"></Toolbar>
			<Border></Border>
		</div>

		<div class="flex-1 overflow-y-auto pb-safe-bottom">
			<template v-if="state.isLoading">
				<div class="flex h-full items-center justify-center">
					<div class="colibri-primary-animation"></div>
				</div>
			</template>

			<template v-else-if="hasChatData">
				<div class="py-8">
					<DirectChatOverview v-bind:chatData="chatData"></DirectChatOverview>
				</div>

				<div class="mb-4">
					<ActionSheetGroup>
						<RouterLink v-bind:to="{ name: 'profile_index', params: { id: chatData.chat_info.username }}">
							<ActionSheetItem
								v-bind:notLast="true"
								v-bind:textLabel="$t('labels.view_profile')"
							iconName="arrow-up-right"></ActionSheetItem>
						</RouterLink>

						<ActionSheetItem
							v-if="chatData.meta?.is_archived"
							v-on:click="unarchiveChat"
							v-bind:notLast="true"
							v-bind:textLabel="$t('chat.unarchive_chat')"
						iconName="archive"></ActionSheetItem>

						<ActionSheetItem
							v-else
							v-on:click="archiveChat"
							v-bind:notLast="true"
							v-bind:textLabel="$t('chat.archive_chat')"
						iconName="archive"></ActionSheetItem>

						<ActionSheetItem
							v-on:click="clearChat"
							v-bind:textLabel="$t('chat.clear_conversation')"
						iconName="brush-03"></ActionSheetItem>
					</ActionSheetGroup>
				</div>

				<div class="mb-4">
					<div class="px-4 mb-2">
						<h3 class="text-par-m font-bold text-lab-pr2">
							{{ $t('chat.participants_count', { count: chatData.chat_info.participants_count.formatted }) }}
						</h3>
					</div>

					<ActionSheetGroup>
						<div v-if="state.isParticipantsLoading" class="flex-center h-24">
							<div class="colibri-primary-animation"></div>
						</div>
						<div v-else class="block">
							<ParticipantItem v-for="participantData in chatParticipants"
								v-bind:key="participantData.participant_id"
								v-bind:name="participantData.relations.user.name"
								v-bind:caption="$t('labels.was_online_at', { time: participantData.relations.user.last_active.time_ago })"
								v-bind:verified="participantData.relations.user.verified"
								v-bind:avatarSrc="participantData.relations.user.avatar_url"
							v-bind:username="participantData.relations.user.username">
								<template v-if="participantData.relations.user.is_auth_user" v-slot:caption>
									<span class="text-par-n text-green-900">
										~ {{ $t('labels.you') }}
									</span>
								</template>
							</ParticipantItem>
						</div>
					</ActionSheetGroup>
				</div>

				<div class="mb-4">
					<ActionSheetGroup>
						<ActionSheetItem
							v-on:click="reportChat"
							v-bind:notLast="true"
							itemColor="text-red-900"
							v-bind:textLabel="$t('labels.report_this_user', { user_name: chatData.chat_info.name })"
						iconName="annotation-alert"></ActionSheetItem>

						<ActionSheetItem
							itemColor="text-red-900"
							v-on:click="deleteChat"
							v-bind:textLabel="$t('chat.delete_chat')"
						iconName="trash-04"></ActionSheetItem>
					</ActionSheetGroup>
				</div>

				<div class="px-4 pb-6 text-center">
					<span class="text-lab-sc text-cap-l">
						{{ $t('chat.chat_created_date', { date: chatData.date.iso })}}
					</span>
				</div>
			</template>
		</div>
	</div>
</template>

<script>
	import { computed, defineComponent, onMounted, reactive } from 'vue';
	import { useRoute, useRouter } from 'vue-router';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useChatStore } from '@M/store/chats/chat.store.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import DirectChatOverview from '@M/views/messenger/children/chat/parts/info/DirectChatOverview.vue';
	import ParticipantItem from '@M/views/messenger/children/chat/parts/participants/ParticipantItem.vue';

	export default defineComponent({
		setup: function() {
			const route = useRoute();
			const router = useRouter();
			const chatStore = useChatStore();
			const state = reactive({
				isLoading: true,
				isParticipantsLoading: true
			});

			const chatData = computed(() => {
				return chatStore.chatData;
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

					await chatStore.fetchChatParticipants();
					state.isParticipantsLoading = false;
				} catch (error) {
					router.push({
						name: 'messenger_inbox'
					});
				}
			});

			return {
				state: state,
				chatData: chatData,
				chatParticipants: computed(() => {
					return chatStore.chatParticipants;
				}),
				hasChatData: computed(() => {
					return Boolean(chatData.value?.chat_info);
				}),
				goBack: goBack,
				archiveChat: async () => {
					await chatStore.archiveChat(chatData.value.chat_id);
					chatData.value.meta.is_archived = true;

					toastSuccess(__t('toast.chat.chat_archived'));
				},
				unarchiveChat: async () => {
					await chatStore.unarchiveChat(chatData.value.chat_id);
					chatData.value.meta.is_archived = false;

					toastSuccess(__t('toast.chat.chat_unarchived'));
				},
				clearChat: () => {
					colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.clear_conversation.title'),
                        description: __t('prompt.clear_conversation.desc'),
                        confirmButtonText: __t('prompt.clear_conversation.confirm'),
                        onConfirm: async () => {
                            try {
                                await chatStore.clearChat();

                                toastSuccess(__t('toast.chat.chat_cleared'), 3000);
                            } catch (error) {
                                toastError(error, 3000);
                            }
                        }
                    });
				},
				deleteChat: () => {
					colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.delete_chat.title'),
                        description: __t('prompt.delete_chat.desc'),
                        onConfirm: async () => {
                            try {
                                await chatStore.deleteChat();

                                router.push({
                                    name: 'messenger_index'
                                });

                                toastSuccess(__t('toast.chat.chat_deleted'), 3000);
                            } catch (error) {
                                toastError(error, 3000);
                            }
                        }
                    });
				},
				reportChat: () => {
                    colibriEventBus.emit('report:open', {
                        type: 'user',
                        reportableId: chatData.value.chat_info.id
                    });
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			ActionSheetGroup: ActionSheetGroup,
			ActionSheetItem: ActionSheetItem,
			DirectChatOverview: DirectChatOverview,
			ParticipantItem: ParticipantItem
		}
	});
</script>
