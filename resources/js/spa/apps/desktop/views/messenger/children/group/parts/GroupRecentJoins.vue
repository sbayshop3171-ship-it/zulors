<template>
	<div class="p-4">
		<div v-if="state.isLoading" class="grid grid-cols-10 gap-2">
			<div v-for="i in 10">
				<div class="aspect-square skeleton-circle"></div>
			</div>
		</div>
		<template v-else>
			<h5 class="font-medium text-lab-sc mb-4 text-par-m">
				{{ $t('chat.recent_joins') }}
			</h5>

			<div v-if="groupRecentJoins.length" class="grid grid-cols-8 gap-2">
				<RouterLink target="_blank" v-for="participantData in groupRecentJoins" v-bind:to="{ name: 'profile_index', params: { id: participantData.relations.user.username } }">
					<div class="flex justify-center">
						<AvatarNormal v-bind:avatarSrc="participantData.relations.user.avatar_url"></AvatarNormal>
					</div>
					<div class="overflow-hidden">
						<span class="text-cap-l text-lab-sc font-medium block truncate text-center">
							{{ participantData.relations.user.name }}
						</span>
					</div>
				</RouterLink>
			</div>
			<div v-else class="py-4 text-center">
				<p class="text-par-s text-lab-sc">
					{{ $t('chat.no_recent_joins') }}
				</p>
			</div>
		</template>
	</div>
</template>

<script>
	import { defineComponent, reactive, computed, onMounted } from 'vue';

	import { useGroupStore } from '@D/store/chats/group.store.js';

	import AvatarNormal from '@D/components/general/avatars/AvatarNormal.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isLoading: true
			});

			const groupStore = useGroupStore();

			const groupRecentJoins = computed(() => {
				return groupStore.groupRecentJoins;
			});

			onMounted(async function() {
				await groupStore.fetchGroupRecentJoins();

				state.isLoading = false;
			});

			return {
				state: state,
				groupRecentJoins: groupRecentJoins
			}
		},
		components: {
			AvatarNormal: AvatarNormal
		}
	})
</script>