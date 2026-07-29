<template>
	<div class="flex gap-4">
		<span v-if="profileData.followers_count" v-on:click="state.isFollowersModalOpen = true" class="cursor-pointer text-lab-pr2 text-par-m">
			<span class="font-semibold">
				{{ profileData.followers_count.formatted }}
			</span>
			{{ $t('labels.followers_count', profileData.followers_count.raw) }}
		</span>
		<span v-if="profileData.following_count" v-on:click="state.isFollowingsModalOpen = true" class="cursor-pointer text-lab-pr2 text-par-m">
			<span class="font-semibold">
				{{ profileData.following_count.formatted }}
			</span>
			{{ $t('labels.following_count', profileData.following_count.raw) }}
		</span>
		<span v-if="profileData.publications_count" class="text-lab-pr2 text-par-m">
			<span class="font-semibold">
				{{ profileData.publications_count.formatted }}
			</span>
			{{ $t('labels.posts_count', profileData.publications_count.raw) }}
		</span>
	</div>
	<template v-if="profileData.followers_count && state.isFollowersModalOpen">
		<ProfileFollowers v-on:close="state.isFollowersModalOpen = false"></ProfileFollowers>
	</template>
	<template v-if="profileData.following_count && state.isFollowingsModalOpen">
		<ProfileFollowings v-on:close="state.isFollowingsModalOpen = false"></ProfileFollowings>
	</template>
</template>

<script>
	import { defineComponent, reactive, inject } from 'vue';

	import ProfileFollowers from '@M/views/profile/parts/relationship/ProfileFollowers.vue';
	import ProfileFollowings from '@M/views/profile/parts/relationship/ProfileFollowings.vue';

	export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const state = reactive({
				isFollowersModalOpen: false,
				isFollowingsModalOpen: false
			});

			return {
				state: state,
				profileData: profileData
			}
		},
		components: {
			ProfileFollowers: ProfileFollowers,
			ProfileFollowings: ProfileFollowings
		}
	});
</script>
