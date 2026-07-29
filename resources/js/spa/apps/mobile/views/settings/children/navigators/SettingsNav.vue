<template>
	<div class="mb-4">
		<Toolbar v-on:close="$router.back()" v-bind:title="$t('labels.account_settings')"></Toolbar>
	</div>

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

	<div class="mb-4 mt-2">
		<Border height="h-2" opacity="opacity-50"></Border>
	</div>

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

	<div class="mb-4 mt-2">
		<Border height="h-2" opacity="opacity-50"></Border>
	</div>

	<LinksGroupTitle v-bind:textLabel="$t('settings.blocked_accounts')"></LinksGroupTitle>
	<div class="block">
		<LinkItem
			iconName="slash-circle-01"
			v-bind:textLabel="$t('settings.blocked_people')"
			v-bind:routeData="{ name: 'settings_blocked' }"></LinkItem>
	</div>

	<div class="mb-4 mt-2">
		<Border height="h-2" opacity="opacity-50"></Border>
	</div>

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

	<div class="mb-4 mt-2">
		<Border height="h-2" opacity="opacity-50"></Border>
	</div>

	<LinksGroupTitle v-bind:textLabel="$t('settings.additional_settings')"></LinksGroupTitle>
	<div class="block">
		<a v-bind:href="$getRoute('user_linker_index')" class="block">
			<NavItem
				iconName="user-plus-01"
				iconType="line"
				v-bind:textLabel="$t('labels.switch_account')"></NavItem>
		</a>

		<NavItem
			v-on:click="logoutUser"
			iconName="log-out-01"
			iconType="solid"
			v-bind:textLabel="$t('labels.logout')"></NavItem>

		<LinkItem
			iconName="alert-triangle"
			v-bind:linkColor="['text-red-900']"
			v-bind:textLabel="$t('settings.account_actions')"
			v-bind:routeData="{ name: 'settings_actions' }"></LinkItem>
	</div>

	<div class="mb-4 mt-2">
		<Border height="h-2" opacity="opacity-50"></Border>
	</div>
	<div class="px-4 flex flex-col gap-3 text-par-m text-lab-sc">
		<a v-bind:href="$getRoute('help_center')" class="block">
			{{ $t('labels.help_center') }}
		</a>
		<a v-bind:href="$getRoute('privacy_policy')" class="block">
			{{ $t('labels.privacy_policy') }}
		</a>
		<a v-bind:href="$getRoute('terms_of_use')" class="block">
			{{ $t('labels.terms') }}
		</a>
		<a v-bind:href="$getRoute('cookies_policy')" class="block">
			{{ $t('labels.cookies_policy') }}
		</a>
		<a v-bind:href="$getRoute('api_developers')" class="block">
			{{ $t('labels.api_developers') }}
		</a>

		<div class="leading-5">
			<span class="text-par-s text-lab-tr">
				{{ $config('app.name') }} &copy; {{ currentYear }} - <a href="https://www.terla.me" target="_blank" class="underline">Created by Mansur Terla</a>
			</span>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';

	import LinksGroupTitle from '@M/views/settings/parts/LinksGroupTitle.vue';
	import LinkItem from '@M/views/settings/parts/LinkItem.vue';
	import NavItem from '@M/views/settings/parts/NavItem.vue';

	export default defineComponent({
		setup: function() {
			return {
				currentYear: new Date().getFullYear(),
				hideAuthorAttribution: window.HIDE_AUTHOR_ATTRIBUTION,
				logoutUser: () => {
					colibriEventBus.emit('auth:logout');
                },
			};
		},
		components: {
			Toolbar: Toolbar,
			LinksGroupTitle: LinksGroupTitle,
			LinkItem: LinkItem,
			NavItem: NavItem
		}
	});
</script>
