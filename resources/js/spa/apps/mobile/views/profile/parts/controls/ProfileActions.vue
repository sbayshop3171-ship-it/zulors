<template>
	<div class="grid gap-2" v-bind:class="[actionsGridClass]">
		<div v-if="permissions.can_follow" class="col-span-1">
			<FollowPillButton v-bind:buttonFluid="true" v-bind:relationship="profileData.meta.relationship.follow" v-bind:followableId="profileData.id" buttonSize="md"></FollowPillButton>
		</div>
		<div v-if="permissions.can_message" class="col-span-1">
			<PrimaryPillButton v-on:click="sendMessage" v-bind:loading="state.sendingMessage" v-bind:buttonFluid="true" v-bind:buttonText="$t('labels.message')" buttonSize="md" buttonRole="stroked"></PrimaryPillButton>
		</div>
	</div>
</template>

<script>
	import { computed, defineComponent, inject, reactive } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useRouter } from 'vue-router';
	import { useChatStore } from '@M/store/chats/chat.store.js';

	import FollowPillButton from '@M/components/inter-ui/buttons/follows/FollowPillButton.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
	
	export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const router = useRouter();
			const chatStore = useChatStore();
			const state = reactive({
				sendingMessage: false
			});

			return {
				profileData: profileData,
				state: state,
				permissions: computed(() => {
					return profileData.value.meta.permissions;
				}),
				actionsGridClass: computed(() => {
					if(profileData.value.meta.permissions.can_follow && profileData.value.meta.permissions.can_message) {
						return 'grid-cols-2';
					}

					return 'grid-cols-1';
				}),
				sendMessage: async () => {
					if(state.sendingMessage) {
						return false;
					}

					state.sendingMessage = true;

					await colibriAPI().messenger().with({
						user_id: profileData.value.id
					}).sendTo('chats/create').then((response) => {
						let chatData = response.data.data;

						if(chatData.chat) {
							chatStore.primeChatDataFromInbox(chatData.chat);
						}

						router.push({
							name: 'messenger_chat',
							params: {
								chat_id: chatData.chat_id
							}
						});
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					}).finally(() => {
						state.sendingMessage = false;
					});
				},
			}
		},
		components: {
			FollowPillButton: FollowPillButton,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
