import { fitMediaPreviewAspectRatio } from './media-preview-sizing.js';

export const PUBLICATION_IMAGE_GALLERY_LIMIT = 5;

export function getPublicationImageGalleryLayout(mediaCount) {
    const count = Math.max(0, Math.floor(Number(mediaCount) || 0));
    const visibleCount = Math.min(count, PUBLICATION_IMAGE_GALLERY_LIMIT);
    const columns = visibleCount === 3 ? 3 : visibleCount >= 5 ? 6 : visibleCount === 1 ? 1 : 2;
    const aspectRatio = visibleCount === 2 ? 2 : visibleCount === 3 ? 1.5 : visibleCount === 4 ? 1 : visibleCount >= 5 ? 3 : 1;

    return {
        count,
        visibleCount,
        extraCount: Math.max(0, count - PUBLICATION_IMAGE_GALLERY_LIMIT),
        columns,
        rows: visibleCount === 2 ? 1 : visibleCount > 2 ? 2 : 1,
        aspectRatio,
        gridClasses: visibleCount === 1
            ? 'block'
            : columns === 3
                ? 'grid grid-cols-3 gap-px'
                : columns === 6
                    ? 'grid grid-cols-6 gap-px'
                    : 'grid grid-cols-2 gap-px',
    };
}

export function getPublicationImageTileClasses(mediaCount, index, singleTileClass = '') {
    const { visibleCount } = getPublicationImageGalleryLayout(mediaCount);

    if (visibleCount === 1) return singleTileClass;
    if (visibleCount === 3 && index === 0) return 'col-span-2 row-span-2 aspect-square';
    if (visibleCount >= 5) return index < 2 ? 'col-span-3 aspect-square' : 'col-span-2 aspect-square';

    return 'aspect-square';
}

export function getPublicationImageGalleryStageStyle(mediaCount, maxWidth, maxHeight) {
    const layout = getPublicationImageGalleryLayout(mediaCount);
    const size = fitMediaPreviewAspectRatio(layout.aspectRatio, maxWidth, maxHeight);

    if (!size.width || !size.height) {
        return { width: '100%', aspectRatio: `${layout.aspectRatio} / 1` };
    }

    return {
        display: 'grid',
        width: `${size.width}px`,
        height: `${size.height}px`,
        gridTemplateColumns: `repeat(${layout.columns}, minmax(0, 1fr))`,
        gridTemplateRows: `repeat(${layout.rows}, minmax(0, 1fr))`,
        gap: '1px',
    };
}

export function isPublicationImageGalleryMoreTile(mediaCount, index) {
    const { visibleCount, extraCount } = getPublicationImageGalleryLayout(mediaCount);
    return extraCount > 0 && index === visibleCount - 1;
}
