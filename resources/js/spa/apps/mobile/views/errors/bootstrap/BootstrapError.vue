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
			<a href="/" class="rounded-full smoothing bg-fill-tr hover:bg-fill-sc text-brand-900 text-par-m font-semibold block px-6 py-4 text-center cursor-pointer">
				Try again
			</a>
		</div>
	</div>
</template>
