const HLS_CONTENT_TYPE = 'application/vnd.apple.mpegurl';
const DASH_CONTENT_TYPE = 'application/dash+xml';

const asUrl = (value) => {
	return String(value || '').trim();
};

const isHlsUrl = (value) => {
	return /\.m3u8($|\?)/i.test(asUrl(value)) || /\/manifest\/video\.m3u8($|\?)/i.test(asUrl(value));
};

const isDashUrl = (value) => {
	return /\.mpd($|\?)/i.test(asUrl(value)) || /\/manifest\/video\.mpd($|\?)/i.test(asUrl(value));
};

const inferDirectContentType = (value, extension = '') => {
	if(isHlsUrl(value)) {
		return HLS_CONTENT_TYPE;
	}

	if(isDashUrl(value)) {
		return DASH_CONTENT_TYPE;
	}

	const normalizedExtension = String(extension || '').toLowerCase().replace(/^\./, '');

	if(normalizedExtension === 'webm') {
		return 'video/webm';
	}

	if(normalizedExtension === 'mov') {
		return 'video/quicktime';
	}

	return 'video/mp4';
};

function buildAdaptiveVideoSource(mediaItem = {}) {
	const playback = mediaItem?.metadata?.playback || {};
	const hlsUrl = asUrl(playback.hls || (isHlsUrl(mediaItem.source_url) ? mediaItem.source_url : ''));
	const dashUrl = asUrl(playback.dash || (isDashUrl(mediaItem.source_url) ? mediaItem.source_url : ''));
	const directUrl = asUrl(mediaItem.preview_url || (! isHlsUrl(mediaItem.source_url) && ! isDashUrl(mediaItem.source_url) ? mediaItem.source_url : ''));

	if(hlsUrl) {
		return {
			url: hlsUrl,
			type: HLS_CONTENT_TYPE,
			transport: 'hls'
		};
	}

	if(dashUrl) {
		return {
			url: dashUrl,
			type: DASH_CONTENT_TYPE,
			transport: 'dash'
		};
	}

	return {
		url: directUrl,
		type: inferDirectContentType(directUrl, mediaItem.extension),
		transport: directUrl ? 'direct' : 'unknown'
	};
}

function canVideoElementPlayNatively(videoElement, contentType = '') {
	if(! videoElement?.canPlayType || ! contentType) {
		return false;
	}

	if(contentType === HLS_CONTENT_TYPE) {
		return Boolean(
			videoElement.canPlayType(HLS_CONTENT_TYPE)
			|| videoElement.canPlayType('application/x-mpegURL')
		);
	}

	return Boolean(videoElement.canPlayType(contentType));
}

function getDirectPlaybackFallback(mediaItem = {}) {
	const previewUrl = asUrl(mediaItem.preview_url);
	const sourceUrl = asUrl(mediaItem.source_url);

	if(previewUrl && ! isHlsUrl(previewUrl) && ! isDashUrl(previewUrl)) {
		return {
			url: previewUrl,
			type: inferDirectContentType(previewUrl, mediaItem.extension),
			transport: 'direct'
		};
	}

	if(sourceUrl && ! isHlsUrl(sourceUrl) && ! isDashUrl(sourceUrl)) {
		return {
			url: sourceUrl,
			type: inferDirectContentType(sourceUrl, mediaItem.extension),
			transport: 'direct'
		};
	}

	return null;
}

export {
	HLS_CONTENT_TYPE,
	DASH_CONTENT_TYPE,
	isHlsUrl,
	isDashUrl,
	buildAdaptiveVideoSource,
	canVideoElementPlayNatively,
	getDirectPlaybackFallback
};
