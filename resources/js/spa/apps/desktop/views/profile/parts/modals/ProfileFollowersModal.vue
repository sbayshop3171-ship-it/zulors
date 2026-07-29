<template>
	<Teleport to="body">
		<ContentModal v-on:close="$emit('close')">
			<ModalHeader v-bind:modalTitle="$t('labels.followers_count', profileData.followers_count.raw) + ' ' + profileData.followers_count.formatted"></ModalHeader>
			<div class="max-h-[500px] overflow-y-auto">
				<template v-if="state.isLoading">
					<PeopleListItemSkeleton v-for="i in 3" v-bind:key="i"></PeopleListItemSkeleton>
				</template>
				<template v-else-if="followers.length === 0">
					<FluidEmptyState v-if="onlyVerified" iconName="users-03" v-bind:text="$t('labels.user_no_verified_followers', { name: profileData.name })"></FluidEmptyState>
					<FluidEmptyState v-else iconName="users-03" v-bind:text="$t('labels.user_no_followers', { name: profileData.name })"></FluidEmptyState>
				</template>
				<template v-else>
					<PeopleListItem v-for="userData in followers" v-bind:key="userData.id" v-bind:userData="userData"></PeopleListItem>
					<div v-if="! state.noMoreContent" class="flex justify-center py-3">
						<PrimaryPillButton buttonRole="stroked" v-bind:loading="state.isLoadingContent" v-on:click="loadMoreContent" buttonSize="md" v-bind:buttonText="$t('labels.load_more')"></PrimaryPillButton>
					</div>
				</template>
			</div>
			<Border height="h-2"></Border>
			<div class="leading-none">
				<ModalRowSwitcher v-model="onlyVerified" v-bind:buttonText="$t('labels.only_verified_people')" iconName="check-verified-02" iconType="solid"></ModalRowSwitcher>
			</div>

			<template v-slot:loadingSkeleton>
				<div class="flex justify-center py-20">
					<div class="colibri-primary-animation"></div>
				</div>
			</template>
		</ContentModal>
	</Teleport>
</template>

<script>
	import { defineComponent, reactive, ref, inject, onMounted, watch } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import PeopleListItem from '@D/components/people/PeopleListItem.vue';
	import PeopleListItemSkeleton from '@D/components/people/PeopleListItemSkeleton.vue';
	import ContentModal from '@D/components/general/modals/ContentModal.vue';
	import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue'; 
	import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';
	import ModalRowSwitcher from '@D/components/inter-ui/buttons/ModalRowSwitcher.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	
	export default defineComponent({
		emits: ['close'],
		setup: function() {
			const profileData = inject('profileData');
			const state = reactive({
				isLoading: true,
				isLoadingContent: false,
				noMoreContent: false
			});

			const followers = ref([]);
			const onlyVerified = ref(false);
			const cursor = ref(0);
			
			watch(onlyVerified, async () => {
				state.isLoading = true;
				cursor.value = 0;
				state.noMoreContent = false;
				await fetchFollowers();
				state.isLoading = false;
			});

			const fetchFollowers = async () => {
				await colibriAPI().userProfile().params({ 
					id: profileData.value.id,
					only_verified: onlyVerified.value,
					cursor: cursor.value
				}).getFrom('profile/followers').then(function(response) {
					if(cursor.value) {
						if(response.data.data.length) {
							followers.value = followers.value.concat(response.data.data);
						} else {
							state.noMoreContent = true;
						}
					} else {
						followers.value = response.data.data;
					}
				}).catch((error) => {
					followers.value = [];
				});
			}

			onMounted(async () => {
				await fetchFollowers();

				state.isLoading = false;
			});

			return {
				state: state,
				followers: followers,
				profileData: profileData,
				onlyVerified: onlyVerified,
				loadMoreContent: async () => {
					if(state.isLoadingContent || ! followers.value.length) {
						return false;
					}

					state.isLoadingContent = true;
					cursor.value = followers.value.at(-1).cursor_id;

					await fetchFollowers();
					state.isLoadingContent = false;
				}
			};
		},
		components: {
			ContentModal: ContentModal,
			ModalHeader: ModalHeader,
			PeopleListItem: PeopleListItem,
			PeopleListItemSkeleton: PeopleListItemSkeleton,
			FluidEmptyState: FluidEmptyState,
			ModalRowSwitcher: ModalRowSwitcher,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>