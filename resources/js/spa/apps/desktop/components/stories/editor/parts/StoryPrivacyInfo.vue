<template>
	<div class="block px-4 py-4">
		<h5 class="text-lab-pr2 text-par-n mb-1 font-medium">
			{{ $t('story.story_privacy_alert.title') }}
		</h5>
		<p class="text-lab-sc text-par-s">
			{{ $t('story.story_privacy_alert.desc', { app_name: $embedder('config.app.name') }) }}
		</p>
	</div>
</template>