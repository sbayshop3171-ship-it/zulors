<template>
	<div class="mobile-safe-chat-header flex items-center leading-none gap-2.5">
		<div class="shrink-0">
			<PrimaryIconButton
				v-on:click="$emit('close')"
				buttonColor="text-lab-pr"
				iconSize="icon-medium"
			iconName="chevron-left"></PrimaryIconButton>
		</div>
		<div class="shrink-0 cursor-pointer relative" v-on:click="openInfo">
			<AvatarExtraSmall v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarExtraSmall>
			<span v-if="presence?.is_online" class="absolute right-0 bottom-0 size-2.5 rounded-full bg-green-500 ring-2 ring-bg-pr"></span>
		</div>
		<div class="flex-1 overflow-hidden cursor-pointer" v-on:click="openInfo">
			<Name v-bind:name="chatData.chat_info.name"></Name>
			<p class="text-par-s truncate mt-1 text-lab-sc">
				<template v-if="isTyping">
					<span class="text-green-900 font-medium">
						<template v-if="chatData.is_group">
							{{ $t('chat.user_is_typing', { name: typingUser.user.name }) }}
						</template>
						<template v-else>
							{{ $t('chat.typing') }}
						</template>
					</span>
				</template>
				<template v-else>
					<template v-if="chatData.is_group">
						{{ $t('chat.participants_count', { count: chatData.chat_info.participants_count.formatted }) }}
					</template>
					<template v-else>
						<span v-if="statusText" v-bind:class="[presence?.is_online ? 'text-green-600 font-medium' : '']">
							{{ statusText }}
						</span>
					</template>
				</template>
			</p>
		</div>
		<div class="shrink-0">
			<div class="flex items-center gap-1">
				<PrimaryIconButton v-if="canStartCall" v-on:click="startAudioCall" iconType="line" iconName="phone"></PrimaryIconButton>
				<PrimaryIconButton v-on:click="openInfo" iconType="line" v-bind:iconName="chatData.is_group ? 'info-circle' : 'info-circle'"></PrimaryIconButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';
	import { useRouter } from 'vue-router';
	import { useCallStore } from '@M/store/calls/call.store.js';

	import AvatarExtraSmall from '@M/components/general/avatars/AvatarExtraSmall.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		emits: ['close'],
		props: {
			typingUser: {
				type: Object,
				default: {
					is_typing: false,
					user: null
				}
			},
			chatData: {
				type: Object,
				required: true
			}
		},
		setup: function(props, context) {
			const router = useRouter();
			const callStore = useCallStore();

			return {
				isTyping: computed(() => {
					return props.typingUser.is_typing === true;
				}),
				presence: computed(() => {
					return props.chatData.chat_info?.presence || {};
				}),
				statusText: computed(() => {
					const presence = props.chatData.chat_info?.presence || {};

					if(presence.is_online) {
						return __t('labels.active_now');
					}
					else if(presence.recent) {
						return __t('labels.active_minutes_ago', { time: presence.minutes_ago });
					}
					else if(presence.last_seen_at?.time_ago) {
						return __t('labels.last_seen_ago', { time: presence.last_seen_at.time_ago });
					}
					else if(props.chatData.chat_info?.last_active?.formatted) {
						return __t('labels.was_online_at', { time: props.chatData.chat_info.last_active.formatted });
					}

					return '';
				}),
				canStartCall: computed(() => {
					return callStore.canStartCall(props.chatData);
				}),
				startAudioCall: () => {
					callStore.startCall(props.chatData).catch((error) => {
						toastError(error.message || 'Unable to start audio call.');
					});
				},
				openInfo: () => {
					if(props.chatData.is_group) {
						router.push({ name: 'messenger_group', params: { chat_id: props.chatData.id } });
					} else {
						router.push({ name: 'messenger_chat_info', params: { chat_id: props.chatData.id } });
					}
				},
			}
		},
		components: {
			AvatarExtraSmall: AvatarExtraSmall,
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>
