<template>
	<div class="p-4 border-b border-bord-pr">
		<div class="mb-2">
			<div class="flex gap-3 items-center">
				<div class="shrink-0">
					<AvatarMedium v-bind:avatarSrc="profileData.avatar_url"></AvatarMedium>
				</div>

				<div class="flex-1">
					<h4 class="text-title-3 mb-1 font-bold text-lab-pr2 leading-none">
						{{ profileData.name }}
		
						<span class="size-icon-x-small inline-block text-brand-900">
							<SvgIcon name="check-verified-02"></SvgIcon>
						</span>
					</h4>
					<div class="flex gap-3">
						<RouterLink v-bind:to="{ name: 'profile_index', params: { id: profileData.username }}" class="text-par-s text-lab-sc hover:underline">
							{{ profileData.caption }}
						</RouterLink>
						<span class="text-par-s text-lab-sc">
							<span class="font-mono">{{ profileData.followers_count.formatted }}</span> {{ $t('labels.followers_count', profileData.followers_count.raw) }}
						</span>
					</div>
				</div>
				<div v-if="! profileData.is_me" class="shrink-0 self-start">
					<FollowPillButton
						buttonSize="lm"
						v-bind:followableId="profileData.id"
					v-bind:relationship="profileData.meta.relationship.follow"></FollowPillButton>
				</div>
			</div>
		</div>
		<div class="block">
			<p v-if="profileData.description.length" v-html="$mdInline(profileData.description)" class="text-par-m max-w-8/12 line-clamp-3 text-lab-pr2"></p>
			<span v-if="profileData.meta.relationship.follow.followed_by" class="block mt-2 text-par-s text-lab-sc">
				{{ $t('labels.following_you_on', { app_name: $embedder('config.app.name') }) }}
			</span>
			<div class="mt-2 text-cap-l text-lab-sc">
				{{ $t('labels.was_online_at', { time: profileData.last_active.formatted }) }}
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import AvatarMedium from '@D/components/general/avatars/AvatarMedium.vue';
	import FollowPillButton from '@D/components/inter-ui/buttons/follows/FollowPillButton.vue';

	export default defineComponent({
		props: {
			profileData: {
				type: Object,
				required: true
			}
		},
		components: {
			AvatarMedium: AvatarMedium,
			FollowPillButton: FollowPillButton
		}
	});
</script>