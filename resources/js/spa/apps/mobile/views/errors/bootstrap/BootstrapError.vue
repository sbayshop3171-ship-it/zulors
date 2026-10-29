<template>
	<div class="fixed inset-0 bg-bg-pr flex-center px-8">
		<div class="text-center">
			<div class="flex justify-center mb-5">
				<img v-bind:src="$embedder('assets.logos.url')" alt="Zulors" class="w-10">
			</div>
			<h1 class="text-title-2 font-bold text-lab-pr2 mb-3">
				{{ isRouteLoadError ? 'This screen could not load' : 'Connecting to Zulors' }}
			</h1>
			<p class="text-par-m text-lab-sc mb-8">
				{{ isRouteLoadError ? 'The mobile screen could not be downloaded. Your conversation is safe. Please retry.' : 'The app could not finish loading. Please try again in a moment.' }}
			</p>
			<a href="/" v-on:click.prevent="retryApplication" class="rounded-full smoothing bg-fill-tr hover:bg-fill-sc text-brand-900 text-par-m font-semibold block px-6 py-4 text-center cursor-pointer">
				Try again
			</a>
		</div>
	</div>
</template>

<script>
import { MOBILE_ROUTE_LOAD_RETRY_KEY, buildMobileRouteRetryUrl } from '@M/core/services/route-load-recovery/index.js';

export default {
	computed: {
		isRouteLoadError: function() {
			return this.$route.query.reason === 'route_load';
		}
	},
	methods: {
		retryApplication: function() {
			try {
				sessionStorage.removeItem('bootstrap-reload-attempted');
				sessionStorage.removeItem('bootstrap-hard-reload-attempted');
				sessionStorage.removeItem(MOBILE_ROUTE_LOAD_RETRY_KEY);
			}
			catch(error) {
				//
			}

			const requestedPath = this.$route.query.return_to;

			if(this.isRouteLoadError && typeof requestedPath === 'string' && requestedPath.startsWith('/') && ! requestedPath.startsWith('//')) {
				window.location.replace(buildMobileRouteRetryUrl(requestedPath, window.location.origin));
				return;
			}

			const retryUrl = new URL('/', window.location.origin);

			retryUrl.searchParams.set('bootstrap_retry', Date.now().toString());
			window.location.replace(retryUrl.toString());
		}
	}
};
</script>
