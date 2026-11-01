import { buildAdaptiveVideoSource } from '@/kernel/services/media/adaptive-video/index.js';
import { getNetworkProfileSnapshot, isSlowNetworkProfile } from '@/kernel/services/network/index.js';

const prefetchedUrls = new Map();
let queuedPosts = [];
let queuedLimit = 0;
let prefetchHandle = null;

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

function prefetchPostMedia(postData, state) {
	const mediaItems = postData?.relations?.media || [];

	mediaItems.forEach((mediaItem) => {
		if(state.count >= state.limit) {
			return;
		}

		if(mediaItem.type === 'video') {
			prefetchImage(mediaItem.thumbnail_url);
		}
		else {
			prefetchImage(mediaItem.source_url);
		}

		state.count++;
	});

	if(postData?.relations?.quoted_post) {
		prefetchPostMedia(postData.relations.quoted_post, state);
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
		queuedLimit = 0;

		const state = {
			count: 0,
			limit: limitBatch
		};

		postsBatch.some((postData) => {
			prefetchPostMedia(postData, state);

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

    const safeActiveIndex = Math.max(0, Math.min(posts.length - 1, Number(activeIndex || 0)));
    const activePost = posts[safeActiveIndex];
    const nextPost = posts[safeActiveIndex + 1];

    const collectPostCacheUrls = (postData) => {
        const mediaItems = postData?.relations?.media || [];

        return mediaItems.filter((mediaItem) => mediaItem?.type === 'video').flatMap((mediaItem) => {
            if(mediaItem.thumbnail_url && ! networkProfile.offline) {
                prefetchImage(mediaItem.thumbnail_url);
            }

            const source = buildAdaptiveVideoSource(mediaItem);

            return [mediaItem.thumbnail_url, source.transport === 'hls' ? source.url : ''].filter(Boolean);
        });
    };

    const currentUrls = collectPostCacheUrls(activePost);
    const nextUrls = collectPostCacheUrls(nextPost);
    const worker = navigator.serviceWorker?.controller;

    if(worker) {
        worker.postMessage({
            type: 'ZULORS_REELS_CACHE_WINDOW',
            current: currentUrls,
            next: nextUrls
        });
    }
}

function warmHomeFeedMedia(posts = [], options = {}) {
    const networkProfile = getNetworkProfileSnapshot();

    if(networkProfile.offline) {
        return;
    }

    const limit = isSlowNetworkProfile(networkProfile)
        ? Number(options.slowLimit || 4)
        : Number(options.limit || 8);

    prefetchTimelineMedia(posts, limit, {
        immediate: true,
        videoPreload: 'metadata',
        includeFallbackSource: false,
        ...options
    });
}

export { prefetchTimelineMedia, prefetchReelsPlaybackWindow, warmHomeFeedMedia };
