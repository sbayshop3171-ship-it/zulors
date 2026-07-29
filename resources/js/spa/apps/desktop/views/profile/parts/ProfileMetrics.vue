<template>
	<div class="flex gap-4">
		<span v-if="profileData.followers_count" v-on:click="state.isFollowersModalOpen = true" class="cursor-pointer text-lab-sc text-par-n">
			<span class="text-lab-pr2 font-mono">
				{{ profileData.followers_count.formatted }}
			</span>
			{{ $t('labels.followers_count', profileData.followers_count.raw) }}
		</span>
		<span v-if="profileData.following_count" v-on:click="state.isFollowingsModalOpen = true" class="cursor-pointer text-lab-sc text-par-n">
			<span class="text-lab-pr2 font-mono">
				{{ profileData.following_count.formatted }}
			</span>
			{{ $t('labels.following_count', profileData.following_count.raw) }}
		</span>
		<span v-if="profileData.publications_count" class="text-lab-sc text-par-n">
			<span class="text-lab-pr2 font-mono">
				{{ profileData.publications_count.formatted }}
			</span>
			{{ $t('labels.posts_count', profileData.publications_count.raw) }}
		</span>
	</div>
	<template v-if="profileData.followers_count && state.isFollowersModalOpen">
		<ProfileFollowersModal v-on:close="state.isFollowersModalOpen = false"></ProfileFollowersModal>
	</template>
	<template v-if="profileData.following_count && state.isFollowingsModalOpen">
		<ProfileFollowingsModal v-on:close="state.isFollowingsModalOpen = false"></ProfileFollowingsModal>
	</template>
</template>

<script>
	import { defineComponent, reactive, inject } from 'vue';
	import ProfileFollowersModal from '@D/views/profile/parts/modals/ProfileFollowersModal.vue';
	import ProfileFollowingsModal from '@D/views/profile/parts/modals/ProfileFollowingsModal.vue';

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
			ProfileFollowersModal: ProfileFollowersModal,
			ProfileFollowingsModal: ProfileFollowingsModal
		}
	});
</script>
