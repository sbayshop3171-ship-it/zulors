<template>
	<div class="pb-4">
		<div class="flex justify-center">
			<img class="size-24" v-bind:src="$asset('assets/icons/bookmark.png')" alt="Image">
		</div>
		<div class="block text-center">
			<h3 class="text-title-3 text-lab-pr font-bold">
				{{ $t('labels.bookmarks') }}
			</h3>
			<p class="text-par-m text-lab-sc">
				{{ $t('labels.bookmarks_privacy_description') }}
			</p>
		</div>
	</div>
</template>