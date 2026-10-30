const positiveNumber = (value) => {
    const number = Number(value || 0);

    return Number.isFinite(number) && number > 0 ? number : 0;
};

export function getImageDimensions(mediaItem = {}, loadedDimensions = {}) {
    const candidates = [
        loadedDimensions,
        mediaItem?.metadata?.dimensions,
        mediaItem?.dimensions,
        mediaItem?.metadata?.original_dimensions,
        mediaItem?.original_dimensions,
        mediaItem,
    ];

    for(const candidate of candidates) {
        const width = positiveNumber(candidate?.width);
        const height = positiveNumber(candidate?.height);

        if(width && height) {
            return { width, height };
        }
    }

    return null;
}

/** Return the source image ratio, falling back only when no dimensions are available yet. */
export function getImageAspectRatio(mediaItem = {}, loadedDimensions = {}, fallback = 4 / 3) {
    const dimensions = getImageDimensions(mediaItem, loadedDimensions);

    if(dimensions) {
        return dimensions.width / dimensions.height;
    }

    const metadataRatio = positiveNumber(mediaItem?.metadata?.aspect_ratio || mediaItem?.aspect_ratio);

    return metadataRatio || fallback;
}
