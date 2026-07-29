<template>
	<div class="h-16 flex justify-between items-center px-6">
		<div v-on:click="openInfo" class="inline-flex items-center cursor-pointer leading-none">
			<span class="relative shrink-0">
				<AvatarSmall v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarSmall>
				<span v-if="presence?.is_online" class="absolute right-0 bottom-0 size-2.5 rounded-full bg-green-500 ring-2 ring-bg-pr"></span>
			</span>
			<div class="ml-3">
				<h4 class="text-par-l font-bold mb-1 text-lab-pr">
					<span v-html="chatData.chat_info.name"></span> <VerificationBadge v-if="chatData.chat_info.verified" size="sm"></VerificationBadge>
				</h4>
				<span class="block text-par-s text-lab-sc">
					<template v-if="chatData.is_group">
						{{ $t('chat.participants_count', { count: chatData.chat_info.participants_count.formatted }) }}
					</template>
					<template v-else>
						<template v-if="isTyping">
							{{ $t('chat.typing') }}
						</template>
						<template v-else>
							<span v-if="statusText" v-bind:class="[presence?.is_online ? 'text-green-600 font-medium' : '']">
								{{ statusText }}
							</span>
						</template>
					</template>
				</span>
			</div>
		</div>
		<div class="inline-flex gap-2.5">
			<template v-if="chatData.is_group">
				<RouterLink v-bind:to="{ name: 'messenger_group_show', params: { chat_id: chatData.id } }">
					<PrimaryIconButton iconName="info-circle" iconType="line"></PrimaryIconButton>
				</RouterLink>
			</template>
			<template v-else>
				<PrimaryIconButton v-on:click="openInfo" iconName="info-circle" iconType="line"></PrimaryIconButton>
			</template>
		</div>
	</div>
    <div v-if="showSoundbar" class="border-t border-bord-card">
        <SoundbarPlayer context="chat"></SoundbarPlayer>
    </div>
</template>

<script>
	import { defineComponent, ref, computed, onMounted, onBeforeUnmount } from 'vue';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	import { useRouter } from 'vue-router';
    import { useAudioStore } from '@D/store/audio/audio.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
    import SoundbarPlayer from '@D/components/soundbar/SoundbarPlayer.vue';

	export default defineComponent({
		props: {
			typingUser: {
				type: Object,
				default: {
					is_typing: false,
					user: null
				}
			}
		},
		setup: function (props, context) {
			const chatStore = useChatStore();
			const chatData = ref(chatStore.chatData);
			const router = useRouter();
            const audioStore = useAudioStore();

            onMounted(() => {
                colibriEventBus.emit('soundbar:reset');
            });

            onBeforeUnmount(() => {
                colibriEventBus.emit('soundbar:reset');
            });

			return {
				chatData: chatData,
				isTyping: computed(() => {
					return props.typingUser.is_typing === true;
				}),
				presence: computed(() => {
					return chatData.value.chat_info?.presence || {};
				}),
				statusText: computed(() => {
					const presence = chatData.value.chat_info?.presence || {};

					if(presence.is_online) {
						return __t('labels.active_now');
					}
					else if(presence.recent) {
						return __t('labels.active_minutes_ago', { time: presence.minutes_ago });
					}
					else if(presence.last_seen_at?.time_ago) {
						return __t('labels.last_seen_ago', { time: presence.last_seen_at.time_ago });
					}
					else if(chatData.value.chat_info?.last_active?.formatted) {
						return __t('labels.was_online_at', { time: chatData.value.chat_info.last_active.formatted });
					}

					return '';
				}),
				openInfo: () => {
					if(chatData.value.type == 'group') {
						router.push({ name: 'messenger_group_show', params: {
								chat_id: chatData.value.chat_id,
								group_id: chatData.value.chat_info.id
							}
						});
					} else {
						router.push({
							name: 'messenger_chat_info',
							params: { chat_id: chatData.value.chat_id }
						});
					}
				},
                showSoundbar: computed(() => {
                    return audioStore.audioData !== null;
                })
			};
		},
		components: {
			AvatarSmall: AvatarSmall,
			PrimaryIconButton: PrimaryIconButton,
			SoundbarPlayer: SoundbarPlayer
		}
	});
</script>
