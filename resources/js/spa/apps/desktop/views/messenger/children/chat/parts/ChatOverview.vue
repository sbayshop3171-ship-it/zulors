<template>
	<div class="block">
		<div class="flex justify-center">
			<div class="w-content">
				<div class="flex justify-center mb-2">
					<div class="relative">
						<AvatarMedium v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarMedium>
						<span v-if="presence?.is_online" class="absolute right-1 bottom-1 size-3 rounded-full bg-green-500 ring-2 ring-bg-pr"></span>
					</div>
				</div>
				<div class="text-center flex flex-col gap-1">
					<h2 class="text-par-l font-bold text-lab-pr">
						{{ chatData.chat_info.name }} <VerificationBadge v-if="chatData.chat_info.verified" size="md"></VerificationBadge>
					</h2>
					<span class="block text-par-s text-lab-sc">
						<template v-if="chatData.is_group">
							{{ $t('labels.group') }} — {{ $t('chat.participants_count', { count: chatData.chat_info.participants_count.formatted }) }}
						</template>
						<template v-else>
							<span v-if="statusText" v-bind:class="[presence?.is_online ? 'text-green-600 font-medium' : '']">
								{{ statusText }}
							</span>
						</template>
					</span>
					<template v-if="! chatData.is_group">
						<span v-if="isFollowedBy" class="block text-par-s text-lab-sc">
							{{ $t('labels.following_you_on', { app_name: $embedder('config.app.name') }) }}
						</span>
					</template>
					<span v-if="hasDescription" class="block text-par-l text-lab-pr2 markdown-text" v-html="$mdInline(chatData.chat_info.description, { breaks: false })"></span>
					<template v-if="! chatData.is_group">
						<span v-if="chatData.chat_info.followers_count" class="block text-par-s text-lab-sc">
							{{ chatData.chat_info.followers_count.formatted }} {{ $t('labels.followers_count', chatData.chat_info.followers_count.raw) }}
						</span>
					</template>
				</div>
				<div v-if="isDirect" class="flex justify-center mt-4">
					<RouterLink v-if="! chatData.chat_info.meta.relationship.block.blocking" v-bind:to="{ name: 'profile_index', params: { id: chatData.chat_info.username }}">
						<PrimaryPillButton buttonType="submit" buttonSize="md" v-bind:buttonText="$t('labels.view_profile')"></PrimaryPillButton>
					</RouterLink>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed } from 'vue';
	import { useChatStore } from '@D/store/chats/chat.store.js';

	import AvatarMedium from '@D/components/general/avatars/AvatarMedium.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function () {
			const chatStore = useChatStore();
			const chatData = ref(chatStore.chatData);

			return {
				chatData: chatData,
				isDirect: chatStore.isDirect,
				hasDescription: chatStore.hasDescription,
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
				isFollowedBy: computed(() => {
					if(chatData.value.is_group) {
						return false;
					}
					else {
						return chatData.value.chat_info.meta.relationship.follow.followed_by;
					}
				})
			};
		},
		components: {
            PrimaryPillButton: PrimaryPillButton,
			AvatarMedium: AvatarMedium
		}
	});
</script>
