<template>
	<div class="flex gap-4 flex-wrap">
		<span v-if="profileData.followers_count" v-on:click="state.isFollowersModalOpen = true" class="inline-flex items-center gap-1 whitespace-nowrap cursor-pointer text-lab-sc text-par-n">
			<span class="text-lab-pr2 font-semibold">
				{{ metricCount(profileData.followers_count) }}
			</span>
			{{ $t('labels.followers_count') }}
		</span>
		<span v-if="profileData.following_count" v-on:click="state.isFollowingsModalOpen = true" class="inline-flex items-center gap-1 whitespace-nowrap cursor-pointer text-lab-sc text-par-n">
			<span class="text-lab-pr2 font-semibold">
				{{ metricCount(profileData.following_count) }}
			</span>
			{{ $t('labels.following_count') }}
		</span>
		<span v-if="profileData.publications_count" class="inline-flex items-center gap-1 whitespace-nowrap text-lab-sc text-par-n">
			<span class="text-lab-pr2 font-semibold">
				{{ metricCount(profileData.publications_count) }}
			</span>
			{{ $t('labels.posts_count') }}
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
				profileData: profileData,
				metricCount: (metric) => {
					return metric?.formatted || metric?.raw || 0;
				}
			}
		},
		components: {
			ProfileFollowersModal: ProfileFollowersModal,
			ProfileFollowingsModal: ProfileFollowingsModal
		}
	});
</script>
