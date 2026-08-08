import { buildAdaptiveVideoSource, getDirectPlaybackFallback } from '@/kernel/services/media/adaptive-video/index.js';
import { getNetworkProfileSnapshot, isSlowNetworkProfile } from '@/kernel/services/network/index.js';

const prefetchedUrls = new Map();
const retainedVideoPrefetches = [];
let queuedPosts = [];
let queuedLimit = 0;
let prefetchHandle = null;

const videoPrefetchLevels = {
    metadata: 1,
    auto: 2
};

function canPrefetch() {
    return typeof window !== 'undefined' && typeof document !== 'undefined';
}

function prefetchImage(url) {
    if(! url || prefetchedUrls.has(url) || ! canPrefetch()) {
        return;
    }

    prefetchedUrls.set(url, 1);

    const image = new Image();
    image.decoding = 'async';
    image.loading = 'eager';
    image.src = url;
}

function rememberVideoPrefetch(video) {
    retainedVideoPrefetches.push(video);

    while(retainedVideoPrefetches.length > 8) {
        const staleVideo = retainedVideoPrefetches.shift();

        try {
            staleVideo.pause();
            staleVideo.removeAttribute('src');
            staleVideo.load();
        }
        catch(error) {}
    }
}

function prefetchVideoSource(url, preload = 'metadata') {
    const preloadMode = preload === 'auto' ? 'auto' : 'metadata';
    const preloadLevel = videoPrefetchLevels[preloadMode];

    if(! url || ! canPrefetch() || (prefetchedUrls.get(url) || 0) >= preloadLevel) {
        return;
    }

    prefetchedUrls.set(url, preloadLevel);

    const video = document.createElement('video');
    video.preload = preloadMode;
    video.muted = true;
    video.playsInline = true;
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');
    video.src = url;
    rememberVideoPrefetch(video);
    video.load();
}

function prefetchPostMedia(postData, state, networkProfile, options = {}) {
	const mediaItems = postData?.relations?.media || [];

	mediaItems.forEach((mediaItem) => {
		if(state.count >= state.limit) {
			return;
		}

		if(mediaItem.type === 'video') {
			prefetchImage(mediaItem.thumbnail_url);

			if(networkProfile.allowVideoPrefetch) {
				const playbackSource = buildAdaptiveVideoSource(mediaItem);
				const preloadMode = options.videoPreload || 'metadata';

				prefetchVideoSource(playbackSource.url, preloadMode);

				if(options.includeFallbackSource) {
					const fallbackSource = getDirectPlaybackFallback(mediaItem);

					if(fallbackSource?.url && fallbackSource.url !== playbackSource.url) {
						prefetchVideoSource(fallbackSource.url, preloadMode);
					}
				}
			}
		}
		else {
			prefetchImage(mediaItem.source_url);
		}

		state.count++;
	});

	if(postData?.relations?.quoted_post) {
		prefetchPostMedia(postData.relations.quoted_post, state, networkProfile, options);
	}
}

function prefetchTimelineMedia(posts = [], limit = 8, options = {}) {
    if(! canPrefetch() || ! Array.isArray(posts)) {
        return;
    }

	const networkProfile = getNetworkProfileSnapshot();

	if(networkProfile.offline) {
		return;
	}

	const effectiveLimit = isSlowNetworkProfile(networkProfile)
		? Math.max(1, Math.ceil(limit / 2))
		: limit;
	const queueLimit = isSlowNetworkProfile(networkProfile)
		? Math.max(effectiveLimit * 2, effectiveLimit)
		: Math.max(effectiveLimit * 3, effectiveLimit);

	queuedPosts = queuedPosts.concat(posts.slice(0, queueLimit));
	queuedLimit = Math.max(queuedLimit, effectiveLimit);

	const runPrefetch = () => {
		prefetchHandle = null;

		if(document.visibilityState === 'hidden') {
			queuedPosts = [];
			queuedLimit = 0;

			return;
		}

		const postsBatch = queuedPosts.splice(0);
		const limitBatch = queuedLimit || effectiveLimit;
		const activeNetworkProfile = getNetworkProfileSnapshot();

		queuedLimit = 0;

		const state = {
			count: 0,
			limit: limitBatch
		};

		postsBatch.some((postData) => {
			prefetchPostMedia(postData, state, activeNetworkProfile, options);

			return state.count >= state.limit;
		});
	};

    if(options.immediate) {
        window.setTimeout(runPrefetch, 0);
        return;
    }

	if(prefetchHandle) {
		return;
	}

    if('requestIdleCallback' in window) {
        prefetchHandle = window.requestIdleCallback(runPrefetch, { timeout: 2500 });
    }
    else {
        prefetchHandle = window.setTimeout(runPrefetch, 1200);
    }
}

function prefetchReelsPlaybackWindow(posts = [], activeIndex = 0) {
    if(! Array.isArray(posts) || ! posts.length) {
        return;
    }

    const networkProfile = getNetworkProfileSnapshot();

    if(networkProfile.offline) {
        return;
    }

    const safeActiveIndex = Math.max(0, Math.min(posts.length - 1, Number(activeIndex || 0)));
    const forwardRadius = networkProfile.allowVideoPrefetch ? 3 : 1;
    const backwardRadius = networkProfile.allowVideoPrefetch ? 1 : 0;
    const orderedPosts = [];

    for(let offset = 0; offset <= forwardRadius; offset++) {
        const postData = posts[safeActiveIndex + offset];

        if(postData) {
            orderedPosts.push(postData);
        }
    }

    for(let offset = 1; offset <= backwardRadius; offset++) {
        const postData = posts[safeActiveIndex - offset];

        if(postData) {
            orderedPosts.push(postData);
        }
    }

    prefetchTimelineMedia(orderedPosts, orderedPosts.length || 1, {
        immediate: true,
        videoPreload: networkProfile.allowVideoPrefetch ? 'auto' : 'metadata',
        includeFallbackSource: networkProfile.allowVideoPrefetch
    });
}

export { prefetchTimelineMedia, prefetchReelsPlaybackWindow };
