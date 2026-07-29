<template>
	<div v-if="pendingInvitations.length" class="px-4 mb-4">
		<h5 class="font-medium text-lab-pr2 mb-4 text-par-m">
			{{ $t('chat.pending_invitations') }}
		</h5>
		<div class="opacity-50 select-none hover:opacity-100 smoothing">
			<swiper-container slides-per-view="9" autoplay-disable-on-interaction="true" space-between="16" speed="200" mousewheel="true" grab-cursor="true" class="w-full">
				<swiper-slide v-for="participantData in pendingInvitations" v-bind:key="participantData.id">
					<RouterLink target="_blank" v-bind:to="{ name: 'profile_index', params: { id: participantData.relations.user.username } }">
						<AvatarNormal v-bind:avatarSrc="participantData.relations.user.avatar_url"></AvatarNormal>
					</RouterLink>
				</swiper-slide>
			</swiper-container>
		</div>
	</div>
	<Border height="h-3" opacity="opacity-70"></Border>
</template>

<script>
	import { defineComponent, computed, ref, onMounted } from 'vue';
	import { useGroupStore } from '@D/store/chats/group.store.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import { register  } from 'swiper/element/bundle';

	register();

	import AvatarNormal from '@D/components/general/avatars/AvatarNormal.vue';

	export default defineComponent({
		setup: function() {
			const groupStore = useGroupStore();
			const groupData = computed(() => {
				return groupStore.groupData;
			});

			const pendingInvitations = ref([]);

			const fetchPendingInvitations = async () => {
				await colibriAPI().messenger().getFrom(`groups/${groupData.value.chat_id}/invite/pending`).then((response) => {
					pendingInvitations.value = response.data.data;
				}).catch((error) => {
					if(error.response) {
						pendingInvitations.value = [];
					}
				});
			};

			onMounted(async () => {
				await fetchPendingInvitations();
			});

			return {
				pendingInvitations: pendingInvitations
			}
		},
		components: {
			AvatarNormal: AvatarNormal
		}
	});
</script>