<template>
    <SidebarContainer>
        <template v-slot:sidebarTitle>
            <RouterLink class="flex items-center gap-2.5 px-5 mb-4 border-b border-bord-pr pb-4" v-bind:to="{ name: 'home_index' }">
                <img class="h-7" v-bind:src="$embedder('assets.logos.url')" alt="Logo">
                <span class="text-lab-pr2 text-par-l font-bold">
                    {{ $embedder('config.app.name') }}
                </span>
            </RouterLink>
            <div class="px-5 mb-8">
                <div class="bg-fill-fv border border-bord-tr p-4 rounded-2xl">
                    <div class="mb-4">
                        <AvatarRightSided
                            v-bind:avatarSrc="userData.avatar_url"
                            v-bind:name="userData.name"
                            v-bind:verified="userData.verified"
                            v-bind:linkRoute="{ name: 'profile_index', params: { id: userData.username } }"
                        v-bind:caption="userData.caption"></AvatarRightSided>
                    </div>
                    <h3 class="text-par-m font-bold text-lab-pr2 mb-1">
                        {{ $t('labels.account_settings') }}
                    </h3>

                    <p class="text-par-n font-normal text-lab-sc">
                        {{ $t('settings.settings_and_privacy_description') }}
                    </p>

                    <div class="mt-6">
                        <PrimaryTextButton v-bind:buttonText="$t('labels.switch_account')" v-on:click="openAccountSwitcherModal"></PrimaryTextButton>
                    </div>
                </div>
            </div>
        </template>

        <template v-slot:sidebarBody>
            <LinksGroupTitle v-bind:textLabel="$t('settings.settings_and_privacy')"></LinksGroupTitle>

            <div class="block">
                <LinkItem
                    iconName="user-01"
                    v-bind:textLabel="$t('settings.your_account')"
                v-bind:routeData="{ name: 'settings_account' }"></LinkItem>
                <LinkItem
                    iconName="star-04"
                    v-bind:textLabel="$t('settings.authorship')"
                v-bind:routeData="{ name: 'settings_authorship' }"></LinkItem>

                <LinkItem
                    iconName="fingerprint-04"
                    iconType="solid"
                    v-bind:textLabel="$t('settings.account_credentials')"
                v-bind:routeData="{ name: 'settings_credentials' }"></LinkItem>

                <LinkItem
                    iconName="bell-01"
                    v-bind:textLabel="$t('labels.notifications')"
                v-bind:routeData="{ name: 'settings_notifications' }"></LinkItem>

                <LinkItem
                    iconName="file-shield-01"
                    v-bind:textLabel="$t('settings.account_privacy')"
                v-bind:routeData="{ name: 'settings_privacy' }"></LinkItem>

                <LinkItem
                    iconName="translate-01"
                    v-bind:textLabel="$t('labels.language')"
                v-bind:routeData="{ name: 'settings_language' }"></LinkItem>

                <LinkItem
                    iconName="whatsapp"
                    iconType="social"
                    v-bind:textLabel="$t('labels.social_media')"
                v-bind:routeData="{ name: 'settings_social_media' }"></LinkItem>

                <LinkItem
                    v-if="$config('features.dark_theme.enabled')"
                    iconName="moon-02"
                    v-bind:textLabel="$t('labels.theme')"
                v-bind:routeData="{ name: 'settings_theme' }"></LinkItem>
            </div>

            <LinksGroupDivider></LinksGroupDivider>

            <LinksGroupTitle v-bind:textLabel="$t('settings.personal_info_and_verification')"></LinksGroupTitle>

            <div class="block">
                <LinkItem
                    iconName="file-shield-03"
                    v-bind:textLabel="$t('settings.personal_info')"
                v-bind:routeData="{ name: 'settings_personal_info' }"></LinkItem>

                <LinkItem
                    iconName="check-verified-02"
                    v-bind:textLabel="$t('settings.account_verification')"
                v-bind:routeData="{ name: 'settings_verification' }"></LinkItem>
            </div>

            <LinksGroupDivider></LinksGroupDivider>

            <LinksGroupTitle v-bind:textLabel="$t('settings.blocked_accounts')"></LinksGroupTitle>

            <div class="block">
                <LinkItem
                    iconName="slash-circle-01"
                    v-bind:textLabel="$t('settings.blocked_people')"
                v-bind:routeData="{ name: 'settings_blocked' }"></LinkItem>
            </div>

            <LinksGroupDivider></LinksGroupDivider>

            <LinksGroupTitle v-bind:textLabel="$t('settings.advanced_settings')"></LinksGroupTitle>

            <div class="block">
                <LinkItem
                    iconName="keyboard-01"
                    v-bind:textLabel="$t('settings.hotkeys_settings')"
                v-bind:routeData="{ name: 'settings_hotkey' }"></LinkItem>

                <LinkItem
                    iconName="folder-code"
                    v-bind:textLabel="$t('settings.api_for_developers')"
                v-bind:routeData="{ name: 'settings_api' }"></LinkItem>
            </div>
            <LinksGroupDivider></LinksGroupDivider>

            <LinksGroupTitle v-bind:textLabel="$t('settings.additional_settings')"></LinksGroupTitle>

            <div class="block">
                <LinkItem
                    iconName="alert-triangle"
                    v-bind:linkColor="['text-red-900']"
                    v-bind:textLabel="$t('settings.account_actions')"
                v-bind:routeData="{ name: 'settings_actions' }"></LinkItem>
            </div>

            <div class="px-6 mt-8">
                <ApplicationFooter></ApplicationFooter>
            </div>
        </template>
    </SidebarContainer>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import AvatarRightSided from '@D/components/general/avatars/sided/small/AvatarRightSided.vue';
    import SidebarContainer from '@D/components/general/contextual-sidebar/SidebarContainer.vue';
    import LinksGroupTitle from '@D/components/general/contextual-sidebar/parts/LinksGroupTitle.vue';
    import LinkItem from '@D/components/general/contextual-sidebar/parts/LinkItem.vue';
    import LinksGroupDivider from '@D/components/general/contextual-sidebar/parts/LinksGroupDivider.vue';
    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
    import ApplicationFooter from '@D/components/layout/ApplicationFooter.vue';

    export default defineComponent({
        setup: function() {
            const authStore = useAuthStore();

            return {
                userData: computed(() => {
					return authStore.userData;
				}),
                openAccountSwitcherModal: () => {
                    colibriEventBus.emit('account-switcher:open');
                },
            }
        },
        components: {
            SidebarContainer: SidebarContainer,
            LinksGroupTitle: LinksGroupTitle,
            LinkItem: LinkItem,
            LinksGroupDivider: LinksGroupDivider,
            AvatarRightSided: AvatarRightSided,
            PrimaryTextButton: PrimaryTextButton,
            ApplicationFooter: ApplicationFooter
        }
    });
</script>
