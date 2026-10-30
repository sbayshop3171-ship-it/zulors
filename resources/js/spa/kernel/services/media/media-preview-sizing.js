/** Fit a media frame inside its available box without changing its aspect ratio. */
export function fitMediaPreviewSize(width, height, maxWidth, maxHeight) {
    const sourceWidth = Number(width);
    const sourceHeight = Number(height);
    const availableWidth = Number(maxWidth);
    const availableHeight = Number(maxHeight);

    if (![sourceWidth, sourceHeight, availableWidth, availableHeight].every(value => Number.isFinite(value) && value > 0)) {
        return { width: 0, height: 0 };
    }

    const scale = Math.min(1, availableWidth / sourceWidth, availableHeight / sourceHeight);
    return {
        width: Math.max(1, Math.round(sourceWidth * scale)),
        height: Math.max(1, Math.round(sourceHeight * scale)),
    };
}

/** Size an unknown-dimension preview to its available box using a safe fallback ratio. */
export function fitMediaPreviewAspectRatio(aspectRatio, maxWidth, maxHeight) {
    const ratio = Number(aspectRatio);
    const availableWidth = Number(maxWidth);
    const availableHeight = Number(maxHeight);

    if (![ratio, availableWidth, availableHeight].every(value => Number.isFinite(value) && value > 0)) {
        return { width: 0, height: 0 };
    }

    const height = Math.min(availableHeight, availableWidth / ratio);
    return {
        width: Math.max(1, Math.round(height * ratio)),
        height: Math.max(1, Math.round(height)),
    };
}
