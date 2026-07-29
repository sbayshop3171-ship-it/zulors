<template>
	<PageHeader v-if="userData" v-bind:titleText="$t('labels.hello_user', { name: userData.first_name })">
		<div class="relative">
			<div class="opacity-80 hover:opacity-100 translate-x-1">
				<PrimaryIconButton v-on:click.stop="state.mainMenu.open" iconSize="icon-normal" iconName="chevron-down" buttonColor="text-lab-sc"></PrimaryIconButton>
			</div>

			<div v-outside-click="state.mainMenu.close" v-if="state.mainMenu.status" class="absolute top-full right-0 w-96 z-50">
				<RichMenu>
					<RichMenuItem
						v-on:click="openAccountSwitcherModal"
						v-bind:title="userData.name"
						v-bind:description="$t('labels.nav_menu.switch_account.description')"
						trailingIconName="user-01"
					v-bind:avatarSrc="userData.avatar_url"></RichMenuItem>
					<RouterLink v-bind:to="{ name: 'settings_theme' }" class="block">
						<RichMenuItem
							v-bind:title="$t('labels.nav_menu.theme.title')"
							v-bind:description="$t('labels.nav_menu.theme.description')"
							trailingIconName="arrow-up-right"
						iconName="moon-02"></RichMenuItem>
					</RouterLink>
					<RouterLink v-bind:to="{ name: 'settings_language' }" class="block">
						<RichMenuItem
							v-bind:title="$t('labels.nav_menu.language.title')"
							v-bind:description="$t('labels.nav_menu.language.description')"
							iconType="line"
							trailingIconName="arrow-up-right"
						iconName="translate-01"></RichMenuItem>
					</RouterLink>
					<Border></Border>
					<RouterLink v-bind:to="{ name: 'bookmarks_index' }" class="block">
						<RichMenuItem
							v-bind:title="$t('labels.nav_menu.bookmarks.title')"
							v-bind:description="$t('labels.nav_menu.bookmarks.description')"
							iconType="solid"
							trailingIconName="arrow-up-right"
						iconName="bookmark"></RichMenuItem>
					</RouterLink>
				</RichMenu>
			</div>
		</div>
	</PageHeader>
</template>

<script>
	import { defineComponent, computed, reactive } from 'vue';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

	import PageHeader from '@D/components/layout/PageHeader.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
	import RichMenu from '@D/components/general/rich-menu/RichMenu.vue';
	import RichMenuItem from '@D/components/general/rich-menu/RichMenuItem.vue';

	export default defineComponent({
		setup: function() {
			const authStore = useAuthStore();

			const state = reactive({
				mainMenu: useMenu(),
			});

			return {
				state: state,
				userData: computed(() => {
					return authStore.userData;
				}),
				openAccountSwitcherModal: () => {
                    colibriEventBus.emit('account-switcher:open');
                },
			}
		},
		components: {
			PageHeader: PageHeader,
			AvatarSmall: AvatarSmall,
			RichMenu: RichMenu,
			RichMenuItem: RichMenuItem,
			PrimaryIconButton: PrimaryIconButton,
		}
	})
</script>
