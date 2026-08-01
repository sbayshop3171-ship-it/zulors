const MIN_VIDEO_RATIO = 4 / 5;
const MAX_VIDEO_RATIO = 16 / 9;

const positiveNumber = (value) => {
    const numberValue = Number(value || 0);

    return Number.isFinite(numberValue) && numberValue > 0 ? numberValue : 0;
};

export const normalizeVideoDimensions = (metadata = {}) => {
    const dimensions = metadata?.dimensions || {};
    const width = Math.round(positiveNumber(dimensions.width || metadata.width || metadata.video_width));
    const height = Math.round(positiveNumber(dimensions.height || metadata.height || metadata.video_height));

    if(width > 0 && height > 0) {
        return {
            width: width,
            height: height,
            aspect_ratio: width / height,
            is_portrait: width < height
        };
    }

    const aspectRatio = positiveNumber(metadata?.aspect_ratio);

    if(aspectRatio > 0) {
        return {
            width: 0,
            height: 0,
            aspect_ratio: aspectRatio,
            is_portrait: aspectRatio < 1
        };
    }

    return null;
};

export const normalizeVideoAspectRatio = (metadata = {}, fallbackIsPortrait = false) => {
    const dimensions = normalizeVideoDimensions(metadata);
    const ratio = dimensions?.aspect_ratio || (fallbackIsPortrait ? 9 / 16 : 16 / 9);

    return Math.max(MIN_VIDEO_RATIO, Math.min(MAX_VIDEO_RATIO, ratio));
};

export const videoFrameAspectStyle = (metadata = {}, fallbackIsPortrait = false) => {
    return {
        aspectRatio: String(normalizeVideoAspectRatio(metadata, fallbackIsPortrait))
    };
};

export const isVideoPortrait = (metadata = {}, fallback = false) => {
    const dimensions = normalizeVideoDimensions(metadata);

    if(dimensions) {
        return dimensions.is_portrait;
    }

    if(typeof metadata?.is_portrait === 'boolean') {
        return metadata.is_portrait;
    }

    return Boolean(fallback);
};

export const buildVideoPresentationMetadata = (width, height, durationSeconds = 0) => {
    const normalizedWidth = Math.round(positiveNumber(width));
    const normalizedHeight = Math.round(positiveNumber(height));
    const normalizedDuration = positiveNumber(durationSeconds);
    const metadata = {};

    if(normalizedWidth > 0 && normalizedHeight > 0) {
        metadata.dimensions = {
            width: normalizedWidth,
            height: normalizedHeight
        };
        metadata.aspect_ratio = normalizedWidth / normalizedHeight;
        metadata.is_portrait = normalizedWidth < normalizedHeight;
    }

    if(normalizedDuration > 0) {
        metadata.duration_seconds = Math.round(normalizedDuration);
    }

    return metadata;
};

export const applyVideoPresentationMetadata = (mediaItem, metadata = {}) => {
    if(! mediaItem || ! metadata) {
        return mediaItem;
    }

    mediaItem.metadata = {
        ...(mediaItem.metadata || {}),
        ...metadata
    };

    return mediaItem;
};

export const readVideoFileMetadata = (file) => {
    return new Promise((resolve) => {
        if(! file) {
            resolve({});

            return;
        }

        const video = document.createElement('video');
        const objectUrl = URL.createObjectURL(file);
        let settled = false;
        let timeoutId = null;

        const cleanup = () => {
            if(timeoutId) {
                clearTimeout(timeoutId);
            }

            URL.revokeObjectURL(objectUrl);
            video.removeAttribute('src');
            video.load();
        };

        const settle = (metadata = {}) => {
            if(settled) {
                return;
            }

            settled = true;
            cleanup();
            resolve(metadata);
        };

        video.preload = 'metadata';
        video.muted = true;
        video.playsInline = true;
        video.onloadedmetadata = () => {
            settle(buildVideoPresentationMetadata(
                video.videoWidth,
                video.videoHeight,
                video.duration
            ));
        };
        video.onerror = () => settle({});
        timeoutId = setTimeout(() => settle({}), 3000);
        video.src = objectUrl;
    });
};
