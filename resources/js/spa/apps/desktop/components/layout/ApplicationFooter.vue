<template>
	<div class="flex flex-wrap mb-2 leading-5">
		<a v-if="$config('features.business_accounts.enabled')" v-bind:href="$getRoute('business_dashboard_index')" class="hover:underline text-cap-s text-lab-sc mr-2">
			{{ $t('labels.business_account') }}
		</a>
		<RouterLink v-if="$config('features.dark_theme.enabled')" v-bind:to="{ name: 'settings_theme' }" class="hover:underline text-cap-s text-lab-sc mr-2">
			{{ $t('labels.theme') }}
		</RouterLink>
		<RouterLink v-if="$config('features.jobs.enabled')" v-bind:to="{ name: 'jobs_index' }" class="hover:underline text-cap-s text-lab-sc mr-2">
			{{ $t('labels.jobs') }}
		</RouterLink>
		<a v-bind:href="$getRoute('help_center')" target="_blank" class="hover:underline text-cap-s text-lab-sc mr-2">
			{{ $t('labels.help_center') }}
		</a>
		<a v-bind:href="$getRoute('api_developers')" target="_blank" v-if="$config('features.api.enabled')" class="hover:underline text-cap-s text-lab-sc mr-2">
			{{ $t('labels.api_developers') }}
		</a>
		<a v-bind:href="$getRoute('privacy_policy')" target="_blank" class="hover:underline text-cap-s text-lab-sc mr-2">
			{{ $t('labels.privacy_policy') }}
		</a>
		<a v-bind:href="$getRoute('cookies_policy')" target="_blank" class="hover:underline text-cap-s text-lab-sc mr-2">
			{{ $t('labels.cookies_policy') }}
		</a>
		<a v-bind:href="$getRoute('terms_of_use')" target="_blank" class="hover:underline text-cap-s text-lab-sc mr-2">
			{{ $t('labels.terms') }}
		</a>
		<RouterLink v-bind:to="{ name: 'settings_language' }" class="hover:underline text-cap-s text-lab-sc mr-2">
			{{ $t('labels.language') }}
		</RouterLink>
	</div>
	<div class="leading-5">
		<span class="text-cap-s text-lab-sc">
			{{ $config('app.name') }} &copy; {{ currentYear }}
			<RouterLink v-if="! hideAuthorAttribution" v-bind:to="{ name: 'mtl_index' }">
				<span class="block whitespace-nowrap hover:underline hover:text-brand-900">Created by Mansur Terla</span>
			</RouterLink>
		</span>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		setup: function() {
			return {
				currentYear: (new Date()).getFullYear(),
				hideAuthorAttribution: window.HIDE_AUTHOR_ATTRIBUTION
			}
		}
	});
</script>
