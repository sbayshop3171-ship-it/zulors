const STORY_ASPECT_RATIO = 9 / 16;
const STORY_ASPECT_TOLERANCE = 0.045;

const positiveNumber = (value) => {
    const numberValue = Number(value || 0);

    return Number.isFinite(numberValue) && numberValue > 0 ? numberValue : 0;
};

export const storyMediaDimensions = (mediaItem = {}, fallbackDimensions = {}) => {
    const metadata = mediaItem?.metadata || {};
    const dimensions = metadata.dimensions || mediaItem?.dimensions || {};
    const width = Math.round(positiveNumber(
        dimensions.width || mediaItem?.width || metadata.width || metadata.video_width || fallbackDimensions.width
    ));
    const height = Math.round(positiveNumber(
        dimensions.height || mediaItem?.height || metadata.height || metadata.video_height || fallbackDimensions.height
    ));

    if(width > 0 && height > 0) {
        return {
            width: width,
            height: height,
            aspect_ratio: width / height
        };
    }

    const aspectRatio = positiveNumber(metadata.aspect_ratio || mediaItem?.aspect_ratio || fallbackDimensions.aspect_ratio);

    if(aspectRatio > 0) {
        return {
            width: 0,
            height: 0,
            aspect_ratio: aspectRatio
        };
    }

    return null;
};

export const isNativeStoryAspectRatio = (mediaItem = {}, fallbackDimensions = {}) => {
    const dimensions = storyMediaDimensions(mediaItem, fallbackDimensions);

    return Boolean(dimensions && Math.abs(dimensions.aspect_ratio - STORY_ASPECT_RATIO) <= STORY_ASPECT_TOLERANCE);
};

export const shouldUseStoryBlurBackdrop = (mediaItem = {}, fallbackDimensions = {}) => {
    return ! isNativeStoryAspectRatio(mediaItem, fallbackDimensions);
};

export const storyMediaObjectFitClass = (mediaItem = {}, fallbackDimensions = {}) => {
    return isNativeStoryAspectRatio(mediaItem, fallbackDimensions) ? 'object-cover' : 'object-contain';
};

export const elementVideoDimensions = (element) => {
    return {
        width: element?.videoWidth || 0,
        height: element?.videoHeight || 0
    };
};

export const elementImageDimensions = (element) => {
    return {
        width: element?.naturalWidth || 0,
        height: element?.naturalHeight || 0
    };
};
