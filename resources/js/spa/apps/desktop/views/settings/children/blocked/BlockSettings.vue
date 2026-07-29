<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.blocked_accounts')"></PageTitle>
    </div>
    <div class="mb-4">
        <h6 class="text-par-m text-lab-sc">
            {{ $t('settings.forms.blocked.page_desc') }}
        </h6>
    </div>

    <div class="border border-bord-pr rounded-2xl mt-4 overflow-hidden">
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
                <FluidEmptyState iconName="users-03" iconType="line" v-bind:text="$t('settings.forms.blocked.no_blocked_users')"></FluidEmptyState>
            </template>
        </template>
    </div>
</template>

<script>
    import { defineComponent, onMounted, computed, reactive } from 'vue';
    import { useRelationsStore } from '@D/store/relations/relations.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { useI18n } from 'vue-i18n';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import PeopleListItemSkeleton from '@D/components/people/PeopleListItemSkeleton.vue';
    import AvatarRightSided from '@D/components/general/avatars/sided/small/AvatarRightSided.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true
            });

            const { t } = useI18n();
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
                unblockUser: function(userId) {
                    colibriEventBus.emit('confirmation-modal:open', {
                        title: t('prompt.unblock_user.title'),
                        description: t('prompt.unblock_user.description'),
                        confirmButtonText: t('prompt.unblock_user.confirm'),
                        onConfirm: async () => {
                            await relationsStore.blockUser(userId);
                            await relationsStore.fetchBlockedUsers();
                        }
                    });
                }
            };
        },
        components: {
            PageTitle: PageTitle,
            PeopleListItemSkeleton: PeopleListItemSkeleton,
            AvatarRightSided: AvatarRightSided,
            PrimaryPillButton: PrimaryPillButton,
            FluidEmptyState: FluidEmptyState
        }
    });
</script>
