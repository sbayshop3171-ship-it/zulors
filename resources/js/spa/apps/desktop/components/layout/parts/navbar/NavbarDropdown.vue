<template>
    <div class="relative cursor-pointer leading-none">
        <span class="flex items-center opacity-50 hover:opacity-100 text-lab-pr2" v-on:click.stop="state.mainMenu.toggle">
            <span class="size-icon-normal shrink-0">
                <SvgIcon name="dots-horizontal" type="solid"></SvgIcon>
            </span>
            <span class="text-par-l ml-3">
                {{ $t('labels.more') }}
            </span>
        </span>

        <div v-if="state.mainMenu.status" v-on:click.stop="state.mainMenu.close" v-outside-click="state.mainMenu.close" class="absolute top-full left-6 z-50">
            <DropdownMenu>
                <template v-if="canAccessAdminPanel">
                    <a v-bind:href="adminPanelUrl" target="_blank">
                        <DropdownMenuItem iconName="shield-02" v-bind:textLabel="$t('labels.admin_panel')"></DropdownMenuItem>
                    </a>
                    <Border/>
                </template>
                <RouterLink v-bind:to="{ name: 'settings_index' }" class="block w-full">
                    <DropdownMenuItem iconName="settings-01" v-bind:textLabel="$t('labels.account_settings')"></DropdownMenuItem>
                </RouterLink>
                <RouterLink v-if="$config('features.wallet.enabled')" v-bind:to="{ name: 'wallet_index' }" class="block w-full">
                    <DropdownMenuItem iconName="wallet-02" iconType="line" v-bind:textLabel="$t('labels.wallet')"></DropdownMenuItem>
                </RouterLink>
                <Border/>
                <DropdownMenuItem v-on:click="logoutUser" itemColor="text-red-900" iconName="log-out-01" iconType="solid" v-bind:textLabel="$t('labels.logout')"></DropdownMenuItem>
            </DropdownMenu>
        </div>
    </div>
</template>
<script>
    import { defineComponent, computed, reactive } from 'vue';
    import { useRouter } from 'vue-router';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useMenu } from '@/kernel/vue/composables/menu/index.js';

    import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';
    import DropdownMenu from '@D/components/general/dropdowns/parts/DropdownMenu.vue';
    import DropdownMenuItem from '@D/components/general/dropdowns/parts/DropdownMenuItem.vue';

    export default defineComponent({
        setup: function(props) {
            const Router = useRouter();
            const authStore = useAuthStore();
            const state = reactive({
                mainMenu: useMenu()
            });

            return {
                canAccessAdminPanel: computed(() => {
                    return authStore.userData.meta.is_admin || authStore.userData.meta.is_root;
                }),
                adminPanelUrl: computed(() => {
                    return authStore.userData.meta.admin.url;
                }),
                logoutUser: async () => {
                    await authStore.logoutUser();

                    window.location.href = embedder('routes.user_auth_index');
                },
                state: state
            }
        },
        components: {
            DropdownButton: DropdownButton,
            DropdownMenu: DropdownMenu,
            DropdownMenuItem: DropdownMenuItem
        }
    });
</script>
