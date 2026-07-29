<template>
	<div class="max-w-full overflow-hidden py-3">
		<div class="flex items-start gap-3">
			<div class="min-w-0 flex-1">
				<AvatarRightSided
					v-bind:avatarSrc="userData.avatar_url"
					v-bind:verified="userData.verified"
					v-bind:name="userData.name"
					v-bind:linkRoute="{ name: 'profile_index', params: { id: userData.username } }"
				v-bind:caption="userData.caption"></AvatarRightSided>
				<div class="ml-small-avatar pl-2 pr-1">
					<p class="break-words text-par-n text-lab-pr2 line-clamp-2" v-html="userData.bio"></p>
				</div>
			</div>
			<div class="shrink-0 pt-1">
				<FollowPillButton
					class="min-w-[64px] whitespace-nowrap text-center"
					v-bind:followableId="userData.id"
				v-bind:relationship="userData.meta.relationship.follow"></FollowPillButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import AvatarRightSided from '@D/components/general/avatars/sided/small/AvatarRightSided.vue';
	import FollowPillButton from '@D/components/inter-ui/buttons/follows/FollowPillButton.vue';

	export default defineComponent({
		props: {
			userData: {
				type: Object,
				required: true
			}
		},
		components: {
			FollowPillButton: FollowPillButton,
			AvatarRightSided: AvatarRightSided
		}
	});
</script>
