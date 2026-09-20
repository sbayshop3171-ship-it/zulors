const STORY_MUSIC_METADATA_KEYS = [
	'story_music_category',
	'music_title',
	'music_artist',
	'lyrics_keywords',
	'search_keywords',
	'story_music_title',
	'story_music_artist',
	'story_music_album',
	'story_music_mood',
	'story_music_genre'
];

const STORY_EDITOR_METADATA_KEYS = [
	'editor_provider',
	'editor_export_format',
	'editor_export_resolution',
	'editor_export_width',
	'editor_export_height',
	'editor_export_fps',
	'editor_export_source_mime'
];

function isVideoFile(file) {
	return file?.type?.startsWith('video/');
}

function storyMusicUploadOptions(file, options = {}) {
	const uploadOptions = { ...options };

	if(isVideoFile(file) && ! uploadOptions.story_music_category) {
		uploadOptions.story_music_category = 'story_music_source';
	}

	return uploadOptions;
}

function appendStoryMusicUploadMetadata(formData, options = {}) {
	STORY_MUSIC_METADATA_KEYS.forEach((key) => {
		const value = options[key];

		if(value !== undefined && value !== null && value !== '') {
			formData.append(key, value);
		}
	});

	STORY_EDITOR_METADATA_KEYS.forEach((key) => {
		const value = options[key];

		if(value !== undefined && value !== null && value !== '') {
			formData.append(key, value);
		}
	});
}

function storyMusicPublishPayload(selectedTrack, options = {}) {
	if(selectedTrack?.id) {
		const payload = {
			story_music_track_id: selectedTrack.id
		};

		if(options.bakedIntoMedia) {
			payload.story_music_baked = true;
		}

		return payload;
	}

	return {};
}

export { appendStoryMusicUploadMetadata, storyMusicPublishPayload, storyMusicUploadOptions };
