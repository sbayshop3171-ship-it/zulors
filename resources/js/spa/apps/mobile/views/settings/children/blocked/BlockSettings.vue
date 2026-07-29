<template>
    <Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.blocked_accounts')"></Toolbar>
    <SettingsDesc v-bind:text="$t('settings.forms.blocked.page_desc')"></SettingsDesc>
    <div class="mb-3">
        <Border height="h-2" opacity="opacity-50"></Border>
    </div>

    <template v-if="state.isLoading">
        <PeopleListItemSkeleton v-for="i in 7"></PeopleListItemSkeleton>
    </template>
    <template v-else>
        <template v-if="blockedUsers.length">
            <div v-for="blockedUser in blockedUsers" class="p-4 flex items-center justify-between cursor-pointer">
                <AvatarRightSided
                    v-bind:avatarSrc="blockedUser.avatar_url"
                    v-bind:name="blockedUser.name"
                    v-bind:verified="blockedUser.verified"
                    v-bind:username="blockedUser.username"
                    v-bind:linkRoute="{ name: 'profile_index', params: { id: blockedUser.username } }"
                v-bind:caption="blockedUser.caption + ' - ' + blockedUser.date.formatted"></AvatarRightSided>

                <div class="shrink-0">
                    <PrimaryPillButton v-on:click="unblockUser(blockedUser.id)" buttonRole="danger" v-bind:buttonText="$t('labels.unblock')" buttonSize="md"></PrimaryPillButton>
                </div>
            </div>
        </template>
        <template v-else>
            <TimelineEmptyState v-bind:desc="$t('settings.forms.blocked.no_blocked_users')"></TimelineEmptyState>
        </template>
    </template>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted } from 'vue';

    import { useRelationsStore } from '@M/store/relations/relations.store.js';

    import Toolbar from '@M/components/layout/Toolbar.vue';
    import SettingsDesc from '@M/views/settings/parts/SettingsDesc.vue';
    import PeopleListItemSkeleton from '@M/components/people/PeopleListItemSkeleton.vue';
    import TimelineEmptyState from '@M/components/timeline/state/TimelineEmptyState.vue';
    import AvatarRightSided from '@M/components/general/avatars/sided/small/AvatarRightSided.vue';
    import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true,
            });

            const relationsStore = useRelationsStore();

            onMounted(async () => {
                await relationsStore.fetchBlockedUsers();
                state.isLoading = false;
            });

            return {
                state: state,
                blockedUsers: computed(() => {
                    return relationsStore.blockedUsers;
                }),
                unblockUser: async function(userId) {
                    await relationsStore.blockUser(userId);
                    await relationsStore.fetchBlockedUsers();
                }
            }
        },
        components: {
            Toolbar: Toolbar,
            SettingsDesc: SettingsDesc,
            PeopleListItemSkeleton: PeopleListItemSkeleton,
            TimelineEmptyState: TimelineEmptyState,
            AvatarRightSided: AvatarRightSided,
            PrimaryPillButton: PrimaryPillButton
        }
    });
</script>
