<template>
	<div class="block py-2">
		<h5 class="text-white text-par-s mb-1 font-semibold">
			{{ $t('story.story_privacy_alert.title') }}
		</h5>
		<p class="text-white/70 text-par-s">
			{{ $t('story.story_privacy_alert.desc', { app_name: $embedder('config.app.name') }) }}
		</p>
	</div>
</template>
