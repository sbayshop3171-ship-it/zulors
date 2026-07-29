<template>
	<div class="flex items-center gap-2 leading-none">
		<template v-if="permissions.can_follow">
			<FollowPillButton v-bind:relationship="profileData.meta.relationship.follow" v-bind:followableId="profileData.id"></FollowPillButton>
		</template>
		<template v-if="permissions.can_message">
			<PrimaryPillButton
				v-on:click="sendMessage"
				v-bind:loading="state.sendingMessage"
				v-bind:buttonText="$t('labels.message')"
				buttonSize="md"
				buttonRole="stroked"
			></PrimaryPillButton>
		</template>
		<template v-if="profileData.meta.is_owner">
			<PrimaryPillButton v-on:click="state.isModalOpen = true" v-bind:buttonText="$t('labels.edit_profile')" buttonSize="md"></PrimaryPillButton>
		</template>
		<div class="w-8 shrink-0 relative">
			<div class="opacity-30 hover:opacity-100">
				<DropdownButton v-on:click.stop="toggleMainDropdown"></DropdownButton>
			</div>
			<div class="absolute top-full right-0 z-50" v-if="state.isDropdownOpen">
                <DropdownMenu v-outside-click="toggleMainDropdown" v-on:click="toggleMainDropdown">
					<template v-if="permissions.can_sanction">
						<DropdownMenuItem v-on:click="applySanctions" itemColor="text-red-900" iconName="shield-01" v-bind:textLabel="$t('dd.user.apply_sanctions')"></DropdownMenuItem>
						<Border/>
					</template>
					<template v-if="permissions.can_mention">
						<DropdownMenuItem v-on:click="mentionUser" iconName="at-sign" v-bind:textLabel="$t('dd.user.mention', { username: profileData.username})"></DropdownMenuItem>
                        <Border/>
					</template>
					<DropdownMenuItem v-on:click="copyProfileLink" iconName="link-01" iconType="solid" v-bind:textLabel="$t('dd.user.copy_link')"></DropdownMenuItem>
					<RouterLink v-bind:to="{ name: 'profile_info', params: { id: profileData.username } }">
						<DropdownMenuItem iconName="info-circle" v-bind:textLabel="$t('dd.user.about')"></DropdownMenuItem>
					</RouterLink>

					<template v-if="permissions.can_block">
						<Border/>
						<DropdownMenuItem
                            v-on:click="blockUser"
                            v-bind:itemColor="blockRelationship.blocking ? '' : 'text-red-900'"
                            v-bind:iconName="blockRelationship.blocking ? 'user-check-01' : 'slash-circle-01'"
                            v-bind:textLabel="$t(blockRelationship.blocking ? 'dd.user.unblock' : 'dd.user.block', { username: profileData.username })"
                        v-bind:loading="state.blockingUser"></DropdownMenuItem>
					</template>
					<template v-if="permissions.can_report">
						<DropdownMenuItem v-on:click="reportProfile" itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.user.report', { username: profileData.username })"></DropdownMenuItem>
					</template>
					<template v-if="permissions.can_mute">
                        <Border/>
                        <DropdownMenuItem
                            v-on:click="muteUser"
                            v-bind:itemColor="muteRelationship.muting ? '' : 'text-red-900'"
                            v-bind:iconName="muteRelationship.muting ? 'volume-max' : 'volume-x'"
                            v-bind:textLabel="$t(muteRelationship.muting ? 'dd.user.unmute' : 'dd.user.mute', { username: profileData.username })"
                        v-bind:loading="state.mutingUser"></DropdownMenuItem>
					</template>
				</DropdownMenu>
			</div>
		</div>
	</div>
	<template v-if="state.isModalOpen">
		<ProfileEditModal v-on:close="state.isModalOpen = false"></ProfileEditModal>
	</template>
</template>

<script>
	import { computed, defineComponent, reactive, inject, ref, onMounted } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useRelationsStore } from '@D/store/relations/relations.store.js';

	import FollowPillButton from '@D/components/inter-ui/buttons/follows/FollowPillButton.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';
    import DropdownMenu from '@D/components/general/dropdowns/parts/DropdownMenu.vue';
    import DropdownMenuItem from '@D/components/general/dropdowns/parts/DropdownMenuItem.vue';

	import ProfileEditModal from '@D/views/profile/parts/modals/ProfileEditModal.vue';

	export default defineComponent({
		setup: function() {
			const router = useRouter();
			const profileData = inject('profileData');
			const relationsStore = useRelationsStore();
			const state = reactive({
                isDropdownOpen: false,
				sendingMessage: false,
                mutingUser: false,
                blockingUser: false,
				isModalOpen: false
            });

			const muteRelationship = ref({});
            const blockRelationship = ref({});

            onMounted(() => {
                muteRelationship.value = profileData.value.meta.relationship.muting;
                blockRelationship.value = profileData.value.meta.relationship.block;
            });

			return {
				state: state,
				profileData: profileData,
                muteRelationship: muteRelationship,
                blockRelationship: blockRelationship,
				toggleMainDropdown: () => {
                    state.isDropdownOpen = !state.isDropdownOpen;
                },
				permissions: computed(() => {
					return profileData.value.meta.permissions;
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

						router.push({
							name: 'messenger_chat',
							params: {
								chat_id: chatData.chat_id
							}
						});
					}).catch((error) => {
						if(error.response) {
							alert(error.response.data.message);
						}
					}).finally(() => {
						state.sendingMessage = false;
					});
				},
				applySanctions: async () => {
					// TODO
					// Remove this API call and Remove Controller.

					// Redirect admin to admin panel, to the page,
					// where they can apply needed sanctions on user
					// on centralized and functional scalable page.

					const promptVal = confirm('Are you sure you want to delete this user?');

					if(promptVal) {
						await colibriAPI().admin().with({
							user_id: profileData.value.id
						}).delete('profile/delete').then((response) => {
							router.push({
								name: 'home_index'
							});
						}).catch((error) => {
							if(error.response) {
								alert(error.response.data.message);
							}
						});
					}
				},
				mentionUser: () => {
					colibriEventBus.emit('post-editor:open', {
						mentionName: profileData.value.username
					});
				},
				copyProfileLink: () => {
					navigator.clipboard.writeText(profileData.value.profile_url).then(() => {
                        toastSuccess(__t('toast.profile_link_copied'), 1000);
                    });
				},
				reportProfile: () => {
                    colibriEventBus.emit('report:open', {
                        type: 'user',
                        reportableId: profileData.value.id
                    });
                },
                muteUser: async () => {
                    if(state.mutingUser)  {
                        return false;
                    }

                    state.mutingUser = true;

                    const muteResponse = await relationsStore.muteUser(profileData.value.id);

                    if(muteResponse) {
                        muteRelationship.value = muteResponse.relationship.muting;

                        if(muteResponse.relationship.muting.muting) {
                            toastSuccess(__t('toast.user_muted'), 3000);
                        }
                        else {
                            toastSuccess(__t('toast.user_unmuted'), 3000);
                        }
                    }

                    state.mutingUser = false;
                },
                blockUser: async () => {
                    if(state.blockingUser) {
                        return false;
                    }

                    state.blockingUser = true;

                    const blockResponse = await relationsStore.blockUser(profileData.value.id);

                    if(blockResponse) {
                        blockRelationship.value = blockResponse.relationship.block;
                    }

                    window.location.reload();
                }
			}
		},
		components: {
			FollowPillButton: FollowPillButton,
			PrimaryPillButton: PrimaryPillButton,
			DropdownMenu: DropdownMenu,
			DropdownButton: DropdownButton,
			DropdownMenuItem: DropdownMenuItem,
			ProfileEditModal: ProfileEditModal
		}
	});
</script>
