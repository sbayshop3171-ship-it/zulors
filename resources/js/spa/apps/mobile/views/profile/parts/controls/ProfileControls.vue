<template>
	<div class="flex items-center leading-none gap-2">
		<span v-on:click="goBack" class="flex items-center -translate-x-1.5 cursor-pointer">
			<PrimaryIconButton iconName="chevron-left" buttonColor="text-lab-pr2"></PrimaryIconButton>
			<span class="text-lab-pr2 font-medium text-par-l">
				{{ profileData.username	 }}
			</span>
		</span>
		<div class="shrink-0 ml-auto">
			<NotificationsButton></NotificationsButton>
		</div>
		<div class="shrink-0">
			<PrimaryIconButton v-on:click.stop="state.mainMenu.open" iconName="circle-dots" iconType="line" buttonColor="text-lab-pr2"></PrimaryIconButton>
		</div>
	</div>

	<ActionSheet v-if="state.mainMenu.status" v-on:close="state.mainMenu.close" v-bind:isMuted="true">
		<div v-on:click.stop="state.mainMenu.close">
			<div class="mb-4">
				<ActionSheetGroup>
					<template v-if="permissions.can_mention">
						<ActionSheetItem v-on:click="mentionUser" iconName="at-sign" v-bind:textLabel="$t('dd.user.mention', { username: profileData.username})"></ActionSheetItem>
					</template>
					<ActionSheetItem v-on:click="copyProfileLink" iconName="link-01" iconType="solid" v-bind:textLabel="$t('dd.user.copy_link')"></ActionSheetItem>
				</ActionSheetGroup>
			</div>
			<ActionSheetGroup>
				<template v-if="permissions.can_report">
					<ActionSheetItem v-on:click="reportProfile" itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.user.report', { username: profileData.username })"></ActionSheetItem>
				</template>
				<template v-if="permissions.can_block">
					<ActionSheetItem
                        v-on:click="blockUser"
                        v-bind:itemColor="blockRelationship.blocking ? '' : 'text-red-900'"
                        v-bind:iconName="blockRelationship.blocking ? 'user-check-01' : 'slash-circle-01'"
                        v-bind:loading="state.blockingUser"
                    v-bind:textLabel="$t(blockRelationship.blocking ? 'dd.user.unblock' : 'dd.user.block', { username: profileData.username })"></ActionSheetItem>
				</template>
				<template v-if="permissions.can_mute">
					<ActionSheetItem
                        v-bind:loading="state.mutingUser"
                        v-on:click="muteUser"
                        v-bind:itemColor="muteRelationship.muting ? '' : 'text-red-900'"
                        v-bind:iconName="muteRelationship.muting ? 'volume-max' : 'volume-x'"
                    v-bind:textLabel="$t(muteRelationship.muting ? 'dd.user.unmute' : 'dd.user.mute', { username: profileData.username })"></ActionSheetItem>
				</template>

				<RouterLink v-bind:to="{ name: 'profile_info', params: { id: profileData.username } }">
					<ActionSheetItem v-bind:notLast="true" iconName="info-circle" v-bind:textLabel="$t('dd.user.about')"></ActionSheetItem>
				</RouterLink>
				<template v-if="profileData.meta.is_owner">
					<ActionSheetItem v-on:click="state.editProfileMenu.open" iconName="pencil-02" v-bind:textLabel="$t('labels.edit_profile')"></ActionSheetItem>

					<a v-if="$config('features.business_accounts.enabled')" v-bind:href="$getRoute('business_dashboard_index')">
						<ActionSheetItem iconName="building-08" v-bind:textLabel="$t('labels.business_account')"></ActionSheetItem>
					</a>

					<RouterLink v-bind:to="{ name: 'settings_navigator' }">
						<ActionSheetItem iconName="settings-04" v-bind:textLabel="$t('labels.account_settings')"></ActionSheetItem>
					</RouterLink>
				</template>
			</ActionSheetGroup>
		</div>
	</ActionSheet>

	<ActionSheet v-bind:isMuted="true" v-if="state.editProfileMenu.status && profileData.meta.is_owner" v-on:close="state.editProfileMenu.close">
		<ProfileEditor v-on:close="state.editProfileMenu.close"></ProfileEditor>
	</ActionSheet>
</template>

<script>
	import { defineComponent, reactive, ref, onMounted, inject } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';
    import { useRelationsStore } from '@M/store/relations/relations.store.js';

	import DropdownButton from '@M/components/general/dropdowns/DropdownButton.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import NotificationsButton from '@M/components/layout/parts/NotificationsButton.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ProfileEditor from '@M/views/profile/parts/controls/ProfileEditor.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				mainMenu: useMenu(),
                mutingUser: false,
                blockingUser: false,
				editProfileMenu: useMenu()
			});

			const router = useRouter();
            const muteRelationship = ref({});
            const blockRelationship = ref({});
            const profileData = inject('profileData');
            const relationsStore = useRelationsStore();

            onMounted(() => {
                muteRelationship.value = profileData.value.meta.relationship.muting;
                blockRelationship.value = profileData.value.meta.relationship.block;
            });

			return {
				permissions: profileData.value.meta.permissions,
				state: state,
				profileData: profileData,
				muteRelationship: muteRelationship,
				blockRelationship: blockRelationship,
				goBack: function() {
					router.go(-1);
				},
				mentionUser: () => {
					colibriEventBus.emit('post-editor:open', {
                        mentionName: profileData.value.username
                    });
				},
				copyProfileLink: () => {
					try {
						navigator.clipboard.writeText(profileData.value.profile_url).then(() => {
							toastSuccess(__t('toast.profile_link_copied'), 1000);
						});
					} catch (error) {
						toastError(error, 1000);
					}
				},
				reportProfile: () => {
                    colibriEventBus.emit('report:open', {
                        type: 'user',
                        reportableId: profileData.value.id
                    });
                },
                muteUser: async () => {
                    if(state.mutingUser) {
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
			DropdownButton: DropdownButton,
			ActionSheet: ActionSheet,
			ActionSheetItem: ActionSheetItem,
			NotificationsButton: NotificationsButton,
			ActionSheetGroup: ActionSheetGroup,
			PrimaryIconButton: PrimaryIconButton,
			ProfileEditor: ProfileEditor
		}
	});
</script>
