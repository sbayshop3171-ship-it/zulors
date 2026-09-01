<template>
		<RouterLink v-on:click="handleChatClick" v-bind:to="{ name: 'messenger_chat', params: {chat_id: chatData.chat_id } }" class="flex gap-3 items-center pl-4 h-18" v-bind:class="[isSelectedChat ? 'bg-brand-900/5' : '', hasUnread ? 'bg-green-50/40' : '']">
			<div class="shrink-0 relative">
				<AvatarNormal v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarNormal>
				<span v-if="presence?.is_online" class="absolute right-0 bottom-0 size-3 rounded-full bg-green-500 ring-2 ring-bg-pr"></span>
				<span v-else-if="presence?.recent" class="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full bg-green-600 px-1.5 py-0.5 text-[10px] leading-none font-semibold text-white ring-2 ring-bg-pr">
					{{ presence.short_label }}
				</span>
			</div>

			<div class="flex-1 overflow-hidden pr-4" v-bind:class="[isSelectedChat ? 'border-b-transparent' : '']">
				<div class="flex justify-between items-start gap-3">
					<strong class="font-semibold text-par-m whitespace-nowrap truncate text-lab-pr2">
						<span v-html="chatData.chat_info.name"></span>
						<VerificationBadge v-if="chatData.chat_info.verified" size="xs"></VerificationBadge>
					</strong>
					<time v-if="chatData.last_message || isTyping" class="shrink-0 text-par-s whitespace-nowrap" v-bind:class="[hasUnread ? 'font-semibold text-green-600' : 'text-lab-sc']">
						{{ chatData.last_activity.time_ago }}
					</time>
				</div>
				<div class="flex items-center justify-between gap-3 mt-0.5">
					<div class="flex-1 overflow-hidden">
						<p class="text-par-n truncate" v-bind:class="[hasUnread ? 'font-semibold text-lab-pr' : 'text-lab-sc']">
							<template v-if="isTyping">
								<span class="text-green-900 font-medium">
									<template v-if="chatData.is_group">
									{{ $t('chat.user_is_typing', { name: state.typing.user.name }) }}
								</template>
								<template v-else>
									{{ $t('chat.typing') }}
								</template>
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
					<BadgeCounter color="bg-green-600" v-if="!isSelectedChat && hasUnread"
					v-bind:count="chatData.unread_messages_count.formatted"></BadgeCounter>
				</div>
			</div>
	</RouterLink>
</template>

<script>
	import { defineComponent, computed, reactive, toRef } from 'vue';
	import { useRoute } from 'vue-router';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	import { useInboxStore } from '@D/store/chats/inbox.store.js';

	import AvatarNormal from '@D/components/general/avatars/AvatarNormal.vue';
	import BadgeCounter from '@D/components/general/counters/BadgeCounter.vue';

	export default defineComponent({
		props: {
			chatData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			const chatData = toRef(props, 'chatData');
			const chatStore = useChatStore();
			const inboxStore = useInboxStore();
			const route = useRoute();

			const state = reactive({
				typing: {
					is_typing: false,
					user: null
				}
			});

			return {
				state: state,
					isSelectedChat: computed(() => {
						const activeChatId = inboxStore.activeChatId || route.params.chat_id || chatStore.chatId;

						return activeChatId === chatData.value.chat_id;
					}),
					hasUnread: computed(() => {
						return chatData.value.unread_messages_count.raw > 0;
					}),
					presence: computed(() => {
						return chatData.value.chat_info?.presence || {};
					}),
					isTyping: computed(() => {
	                    return state.typing.is_typing;
	                }),
				handleChatClick: (event) => {
					if(route.name === 'messenger_chat' && route.params.chat_id === chatData.value.chat_id) {
						event?.preventDefault();
						inboxStore.setActiveChatId(chatData.value.chat_id);
						inboxStore.markChatAsRead(chatData.value.chat_id);

						return;
					}

					inboxStore.setActiveChatId(chatData.value.chat_id);
					inboxStore.markChatAsRead(chatData.value.chat_id);
					chatStore.prepareChatForRoute(chatData.value.chat_id, {
						preferCache: true,
						primeChatData: chatData.value
					});
				}
			}
		},
		components: {
			AvatarNormal: AvatarNormal,
			BadgeCounter: BadgeCounter
		}
	});
</script>
