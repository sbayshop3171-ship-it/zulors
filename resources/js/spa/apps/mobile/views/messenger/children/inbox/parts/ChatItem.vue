<template>
		<RouterLink v-on:click="markAsRead" v-bind:to="{ name: 'messenger_chat', params: {chat_id: chatData.chat_id } }" class="flex gap-2.5 py-3 items-center px-4" v-bind:class="[hasUnread ? 'bg-green-50/40' : '']">
			<div class="shrink-0 relative">
				<AvatarNormal v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarNormal>
				<span v-if="presence?.is_online" class="absolute right-0 bottom-0 size-3 rounded-full bg-green-500 ring-2 ring-bg-pr"></span>
				<span v-else-if="presence?.recent" class="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full bg-green-600 px-1.5 py-0.5 text-[10px] leading-none font-semibold text-white ring-2 ring-bg-pr">
					{{ presence.short_label }}
				</span>
			</div>

			<div class="flex-1 overflow-hidden">
				<div class="flex justify-between items-start gap-3">
					<div class="min-w-0">
						<Name v-bind:name="chatData.chat_info.name" v-bind:isVerified="chatData.chat_info.verified"></Name>
					</div>
					<time v-if="chatData.last_message || isTyping" class="shrink-0 text-par-s whitespace-nowrap" v-bind:class="[hasUnread ? 'font-semibold text-green-600' : 'text-lab-sc']">
						{{ chatData.last_activity.time_ago }}
					</time>
				</div>
				<div class="flex items-center justify-between gap-3 mt-0.5">
					<div class="flex-1 overflow-hidden">
						<p class="text-par-m truncate" v-bind:class="[hasUnread ? 'font-semibold text-lab-pr' : 'text-lab-pr2']">
							<template v-if="isTyping">
								<span class="text-green-900 font-medium">
									{{ $t('chat.typing') }}
								</span>
							</template>
							<template v-else-if="chatData.is_deleted">
								{{ $t('chat.message_is_deleted') }}
							</template>
							<template v-else-if="chatData.last_message">
								<span class="truncate" v-html="chatData.last_message"></span>
							</template>
							<template v-else>
								<time class="text-par-s text-lab-sc whitespace-nowrap">
									{{ $t('labels.was_online_at', { time: chatData.last_activity.formatted }) }}
								</time>
							</template>
						</p>
					</div>
					<BadgeCounter color="bg-green-600" v-if="hasUnread" v-bind:count="chatData.unread_messages_count.formatted"></BadgeCounter>
				</div>
			</div>
	</RouterLink>
</template>

<script>
	import { defineComponent, computed, onMounted, reactive, toRef, onUnmounted } from 'vue';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';
	import { useChatStore } from '@M/store/chats/chat.store.js';

	import AvatarNormal from '@M/components/general/avatars/AvatarNormal.vue';
	import BRD from '@/kernel/websockets/brd/index.js';
	import BadgeCounter from '@M/components/general/counters/BadgeCounter.vue';

	export default defineComponent({
		props: {
			chatData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			const chatData = toRef(props, 'chatData');
			const authStore = useAuthStore();
			const inboxStore = useInboxStore();
			const chatStore = useChatStore();

			const state = reactive({
				typing: BRD.createEmptyTypingState(),
				realtimeReady: false
			});
			const remoteTyping = BRD.createIncomingTypingController((nextState) => {
				state.typing = nextState;
			});

			onUnmounted(() => {
				window.removeEventListener('colibri:ws-status', handleWSStatus);
				remoteTyping.stop();
				detachRealtimeListeners();
			});

			onMounted(() => {
				window.addEventListener('colibri:ws-status', handleWSStatus);
				attachRealtimeListeners();
			});

			const getChatChannel = () => {
				return BRD.getChannel('CHAT', [chatData.value.chat_id]);
			}

			const detachRealtimeListeners = () => {
				if(state.realtimeReady && window.ColibriBRD) {
					ColibriBRD.private(getChatChannel()).stopListeningForWhisper(BRD.getEvent('CHAT_MESSAGE_TYPING'));
					ColibriBRD.private(getChatChannel()).stopListening(BRD.getEvent('CHAT_MESSAGE_RECEIVED'));
					ColibriBRD.private(getChatChannel()).stopListening(BRD.getEvent('CHAT_MESSAGE_DELETED'));
					remoteTyping.stop();

					state.realtimeReady = false;
				}
			}

			const attachRealtimeListeners = () => {
				if(state.realtimeReady || ! window.ColibriBRD) {
					return false;
				}

				ColibriBRD.private(getChatChannel()).listenForWhisper(BRD.getEvent('CHAT_MESSAGE_TYPING'), remoteTyping.receive);

				ColibriBRD.private(getChatChannel()).listen(BRD.getEvent('CHAT_MESSAGE_RECEIVED'), function (event) {
					inboxStore.updateChatFromMessage(event.data, authStore.userData.id);
				});

				ColibriBRD.private(getChatChannel()).listen(BRD.getEvent('CHAT_MESSAGE_DELETED'), function (event) {
					inboxStore.markChatMessageAsDeleted(chatData.value.chat_id, event.data.message_id);
				});

				state.realtimeReady = true;
			}

			const handleWSStatus = (event) => {
				if(event.detail.connected) {
					attachRealtimeListeners();
				}
			}

			const hasUnread = computed(() => {
				return Number(chatData.value.unread_messages_count?.raw || 0) > 0;
			});

			return {
					state: state,
					hasUnread: hasUnread,
					presence: computed(() => {
						return chatData.value.chat_info?.presence || {};
					}),
					isTyping: computed(() => {
	                    return state.typing.is_typing;
					}),
					markAsRead: () => {
						chatStore.primeChatDataFromInbox(chatData.value);
					inboxStore.markChatAsRead(chatData.value.chat_id);
				}
			}
		},
		components: {
			AvatarNormal: AvatarNormal,
			BadgeCounter: BadgeCounter
		}
	});
</script>
