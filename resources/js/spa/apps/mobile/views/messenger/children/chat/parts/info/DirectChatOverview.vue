<template>
	<div class="block">
		<div class="flex justify-center mb-2">
			<AvatarMedium v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarMedium>
		</div>
		<div class="text-center leading-snug">
			<h2 class="text-title-3 font-bold text-lab-pr">
				{{ chatData.chat_info.name }} <VerificationBadge v-if="chatData.chat_info.verified" size="sm"></VerificationBadge>
			</h2>
			<p class="text-par-s text-lab-sc">
				<span v-if="statusText" v-bind:class="[presence?.is_online ? 'text-green-600 font-medium' : '']">
					{{ statusText }}
				</span>
			</p>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	import AvatarMedium from '@M/components/general/avatars/AvatarMedium.vue';

	export default defineComponent({
		props: {
			chatData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			const presence = computed(() => {
				return props.chatData.chat_info?.presence || {};
			});

			return {
				presence: presence,
				statusText: computed(() => {
					if(presence.value.is_online) {
						return __t('labels.active_now');
					}
					else if(presence.value.recent) {
						return __t('labels.active_minutes_ago', { time: presence.value.minutes_ago });
					}
					else if(presence.value.last_seen_at?.time_ago) {
						return __t('labels.last_seen_ago', { time: presence.value.last_seen_at.time_ago });
					}
					else if(props.chatData.chat_info?.last_active?.formatted) {
						return __t('labels.was_online_at', { time: props.chatData.chat_info.last_active.formatted });
					}

					return '';
				})
			};
		},
		components: {
			AvatarMedium: AvatarMedium,
		}
	});
</script>
