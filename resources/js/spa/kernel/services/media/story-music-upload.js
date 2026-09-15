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
}

function storyMusicPublishPayload(selectedTrack) {
	if(selectedTrack?.id) {
		return {
			story_music_track_id: selectedTrack.id
		};
	}

	return {};
}

export { appendStoryMusicUploadMetadata, storyMusicPublishPayload, storyMusicUploadOptions };
