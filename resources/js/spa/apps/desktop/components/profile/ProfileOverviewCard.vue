<template>
    <div class="flex justify-center">
        <div class="w-content">
            <div class="flex justify-center">
                <AvatarMedium v-bind:avatarSrc="profileData.avatar_url"></AvatarMedium>
            </div>
            <div class="text-center my-2 flex flex-col gap-1">
                <h1 class="text-title-3 font-bold text-lab-pr2">
                    {{ profileData.name }} <VerificationBadge v-if="profileData.verified" size="sm"></VerificationBadge>
                </h1>
                <span class="block text-par-s text-lab-sc">
                    {{ $t('labels.was_online_at', { time: profileData.last_active.formatted }) }}
                </span>
                <span v-if="profileData.meta.relationship.follow.followed_by" class="block text-par-s text-lab-sc">
                    {{ $t('labels.following_you_on', { app_name: $embedder('config.app.name') }) }}
                </span>
                <span v-if="profileData.description" class="block text-par-m text-lab-pr2 markdown-text" v-html="$mdInline(profileData.description)"></span>
                <span  class="block text-par-s text-lab-sc">
                    {{ profileData.followers_count.formatted }} {{ $t('labels.followers_count', profileData.followers_count.raw) }}
                </span>
            </div>
            <div class="flex justify-center">
                <RouterLink v-bind:to="{ name: 'profile_index', params: { id: profileData.username }}">
                    <PrimaryPillButton buttonType="submit" buttonSize="md" v-bind:buttonText="$t('labels.view_profile')"></PrimaryPillButton>
                </RouterLink>
            </div>
        </div>
    </div>
</template>

<script>
	import { defineComponent } from 'vue';

	import AvatarMedium from '@D/components/general/avatars/AvatarMedium.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		props: {
			profileData: {
				type: Object,
				required: true
			}
		},
		setup: function () {
			return {
			};
		},
		components: {
            PrimaryPillButton: PrimaryPillButton,
			AvatarMedium: AvatarMedium
		}
	});
</script>
