<template>
	<RouterLink v-bind:to="{ name: 'messenger_group_show', params: {chat_id: requestData.relations.chat.chat_id } }"
		class="flex gap-3 items-start pl-4 py-3"
		v-bind:class="[($route.params.chat_id === requestData.relations.chat.chat_id) ? 'bg-brand-900/5' : '']">
		<div class="shrink-0">
			<AvatarNormal v-bind:avatarSrc="requestData.relations.sender.avatar_url"></AvatarNormal>
		</div>

		<div class="flex-1 overflow-hidden pr-4">
			<div class="flex justify-between items-center gap-4">
				<strong class="font-semibold text-par-m whitespace-nowrap truncate text-lab-pr2">
					{{ requestData.relations.sender.name }}
					<VerificationBadge v-if="requestData.relations.sender.verified" size="xs"></VerificationBadge>
				</strong>
			</div>
			<div class="flex items-center justify-between gap-4">
				<div class="flex-1 overflow-hidden">
					<p class="text-lab-sc text-par-n">
						<span class="flex items-end gap-2.5 overflow-hidden max-w-full">
							<span class="line-clamp-3">
								{{ $t('chat.invited_you_to_group', { group_name: requestData.relations.group.name }) }}
							</span>
							<span class="text-par-s text-lab-sc whitespace-nowrap">
								{{ requestData.date.time_ago }}
							</span>
						</span>
					</p>
				</div>
			</div>
		</div>
	</RouterLink>
</template>

<script>
	import { defineComponent } from 'vue';

	import AvatarNormal from '@D/components/general/avatars/AvatarNormal.vue';

	export default defineComponent({
		props: {
			requestData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			return {
			}
		},
		components: {
			AvatarNormal: AvatarNormal
		}
	});
</script>