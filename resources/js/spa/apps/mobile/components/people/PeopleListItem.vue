<template>
	<div class="px-4 py-3">
		<div class="flex items-center justify-between cursor-pointer mb-2">
			<AvatarRightSided 
				v-bind:avatarSrc="userData.avatar_url"
				v-bind:name="userData.name"
				v-bind:verified="userData.verified"
				v-bind:linkRoute="{ name: 'profile_index', params: { id: userData.username } }"
			v-bind:caption="userData.caption"></AvatarRightSided>
			<div v-if="! userData.meta.is_owner" class="shrink-0">
				<FollowPillButton buttonSize="sm" v-bind:followableId="userData.id" v-bind:relationship="userData.meta.relationship.follow"></FollowPillButton>
			</div>
		</div>
		<div class="pl-small-avatar">
			<div class="pl-2">
				<p v-if="userData.bio.length" class="text-par-m text-lab-pr2 content-text line-clamp-2" v-html="$mdInline(userData.bio)"></p>
				<div v-if="userData.website">
					<a v-bind:href="userData.website" class="inline-block underline text-par-s text-lab-pr2 content-text hover:underline leading-tight" target="_blank">
						{{ userData.website }}
					</a>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import AvatarRightSided from '@M/components/general/avatars/sided/small/AvatarRightSided.vue';
	import FollowPillButton from '@M/components/inter-ui/buttons/follows/FollowPillButton.vue';
	
	export default defineComponent({
		props: {
			userData: {
				type: Object,
				required: true
			}
		},
		components: {
			AvatarRightSided: AvatarRightSided,
			FollowPillButton: FollowPillButton
		}
	});
</script>