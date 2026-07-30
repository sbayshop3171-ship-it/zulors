const prefetchedUrls = new Set();

function canPrefetch() {
    return typeof window !== 'undefined' && typeof document !== 'undefined';
}

function prefetchImage(url) {
    if(! url || prefetchedUrls.has(url) || ! canPrefetch()) {
        return;
    }

    prefetchedUrls.add(url);

    const image = new Image();
    image.decoding = 'async';
    image.loading = 'eager';
    image.src = url;
}

function prefetchVideoMetadata(url) {
    if(! url || prefetchedUrls.has(url) || ! canPrefetch()) {
        return;
    }

    prefetchedUrls.add(url);

    const video = document.createElement('video');
    video.preload = 'metadata';
    video.muted = true;
    video.src = url;
    video.load();
}

function prefetchPostMedia(postData, state) {
    const mediaItems = postData?.relations?.media || [];

    mediaItems.forEach((mediaItem) => {
        if(state.count >= state.limit) {
            return;
        }

        if(mediaItem.type === 'video') {
            prefetchImage(mediaItem.thumbnail_url);
            prefetchVideoMetadata(mediaItem.preview_url || mediaItem.source_url);
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

function prefetchTimelineMedia(posts = [], limit = 8) {
    if(! canPrefetch() || ! Array.isArray(posts)) {
        return;
    }

    const state = {
        count: 0,
        limit: limit
    };

    posts.some((postData) => {
        prefetchPostMedia(postData, state);

        return state.count >= state.limit;
    });
}

export { prefetchTimelineMedia };
