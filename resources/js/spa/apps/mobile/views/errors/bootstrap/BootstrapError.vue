<template>
	<div class="fixed inset-0 bg-bg-pr flex-center px-8">
		<div class="text-center">
			<div class="flex justify-center mb-5">
				<img v-bind:src="$embedder('assets.logos.url')" alt="Zulors" class="w-10">
			</div>
			<h1 class="text-title-2 font-bold text-lab-pr2 mb-3">
				Connecting to Zulors
			</h1>
			<p class="text-par-m text-lab-sc mb-8">
				The app could not finish loading. Please try again in a moment.
			</p>
			<a href="/" v-on:click.prevent="retryApplication" class="rounded-full smoothing bg-fill-tr hover:bg-fill-sc text-brand-900 text-par-m font-semibold block px-6 py-4 text-center cursor-pointer">
				Try again
			</a>
		</div>
	</div>
</template>

<script>
export default {
	methods: {
		retryApplication: function() {
			sessionStorage.removeItem('bootstrap-reload-attempted');
			sessionStorage.removeItem('bootstrap-hard-reload-attempted');

			const retryUrl = new URL('/', window.location.origin);

			retryUrl.searchParams.set('bootstrap_retry', Date.now().toString());
			window.location.replace(retryUrl.toString());
		}
	}
};
</script>
