<template>
	<div class="px-4 py-3 last:border-b-none">
		<div class="flex items-center justify-between cursor-pointer mb-1">
			<AvatarRightSided 
				v-bind:avatarSrc="userData.avatar_url"
				v-bind:name="userData.name"
				v-bind:verified="userData.verified"
				v-bind:linkRoute="{ name: 'profile_index', params: { id: userData.username } }"
			v-bind:caption="userData.caption"></AvatarRightSided>
			<div class="shrink-0">
				<FollowPillButton v-bind:followableId="userData.id" v-bind:relationship="userData.meta.relationship.follow"></FollowPillButton>
			</div>
		</div>
		<div class="pl-small-avatar pr-24">
			<div class="pl-2">
				<div class="block">
					<p v-if="userData.bio.length" class="text-par-m text-lab-pr2 markdown-text line-clamp-2" v-html="$mdInline(userData.bio)"></p>
					<a v-if="userData.website" v-bind:href="userData.website" class="inline-block text-par-m text-lab-pr2 markdown-text hover:underline leading-tight" target="_blank">
						{{ userData.website }}
					</a>
				</div>
				<div v-if="userData.followers_count.raw" class="flex items-center gap-2 mt-2">
					<span class="text-par-n text-lab-pr3">
						<span class="font-semibold">{{ userData.followers_count.formatted }}</span> {{ $t('labels.followers_count', { count: userData.followers_count.raw }) }}
					</span>
				</div>
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
			AvatarRightSided: AvatarRightSided,
			FollowPillButton: FollowPillButton
		}
	});
</script>