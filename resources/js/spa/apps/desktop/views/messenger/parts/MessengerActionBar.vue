<template>
	<div class="w-action-bar shrink-0">
		<div class="flex flex-col h-full w-full items-center py-6">
			<RouterLink v-bind:to="{ name: 'home_index' }" class="flex justify-center mb-4">
				<img class="h-7" v-bind:src="$embedder('assets.logos.url')" alt="Logo">
			</RouterLink>
			<div class="w-full px-4 pb-2">
				<Border></Border>
			</div>
			<div class="relative">
				<ActionBarButton v-on:click.stop="state.mainMenu.open" v-bind:hasBg="state.mainMenu.status" iconName="plus" iconType="solid"></ActionBarButton>
				<div class="absolute w-96 top-10 left-10 z-50">
					<RichMenu v-outside-click="state.mainMenu.close" v-on:click="state.mainMenu.close" v-if="state.mainMenu.status">
						<RouterLink v-bind:to="{ name: 'messenger_group_create' }">
							<RichMenuItem
								iconName="users-01"
								v-bind:title="$t('chat.main_menu.group.title')"
								trailingIconName="arrow-up-right"
							v-bind:description="$t('chat.main_menu.group.description')"></RichMenuItem>
						</RouterLink>
					</RichMenu>
				</div>
			</div>
			<div class="w-full px-4 py-2">
				<Border></Border>
			</div>
			<RouterLink v-bind:to="{ name: 'messenger_inbox' }" v-slot="{ isActive }" class="block">
				<ActionBarButton v-bind:hasBg="isActive" iconName="message-chat-circle" iconType="line"></ActionBarButton>
			</RouterLink>
			<RouterLink v-bind:to="{ name: 'messenger_archive' }" v-slot="{ isActive }" class="block">
				<ActionBarButton v-bind:hasBg="isActive" iconName="archive" iconType="line"></ActionBarButton>
			</RouterLink>

			<div class="mt-auto w-full">
				<div class="mb-2">
					<RouterLink v-bind:to="{ name: 'settings_privacy' }">
						<ActionBarButton iconName="settings-04" v-bind:hasBg="false" iconType="line"></ActionBarButton>
					</RouterLink>
				</div>
				<div class="flex justify-center">
					<div class="size-10 overflow-hidden rounded-full cursor-pointer" v-on:click="openAccountSwitcherModal">
						<img class="w-full" v-bind:src="userData.avatar_url" alt="Avatar">
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, reactive } from 'vue';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

	import ActionBarButton from '@D/components/layout/parts/navbar/ActionBarButton.vue';

	import RichMenu from '@D/components/general/rich-menu/RichMenu.vue';
	import RichMenuItem from '@D/components/general/rich-menu/RichMenuItem.vue';

	export default defineComponent({
		setup: function () {
			const authStore = useAuthStore();

			const state = reactive({
				mainMenu: useMenu()
			});

			return {
				state: state,
				userData: computed(() => {
                    return authStore.userData;
                }),
				openAccountSwitcherModal: () => {
                    colibriEventBus.emit('account-switcher:open');
                }
			}
		},
		components: {
			ActionBarButton: ActionBarButton,
			RichMenu: RichMenu,
			RichMenuItem: RichMenuItem
		}
	});
</script>
