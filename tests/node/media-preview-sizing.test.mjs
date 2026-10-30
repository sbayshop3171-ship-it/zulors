import test from 'node:test';
import assert from 'node:assert/strict';
import { fitMediaPreviewAspectRatio, fitMediaPreviewSize } from '../../resources/js/spa/kernel/services/media/media-preview-sizing.js';
import { getPublicationImageGalleryLayout, getPublicationImageGalleryStageStyle, getPublicationImageTileClasses, isPublicationImageGalleryMoreTile } from '../../resources/js/spa/kernel/services/media/publication-image-gallery.js';

test('media preview sizing preserves portrait and landscape aspect ratios within viewport bounds', () => {
    assert.deepEqual(fitMediaPreviewSize(3840, 2400, 430, 600), { width: 430, height: 269 });
    assert.deepEqual(fitMediaPreviewSize(1080, 1920, 430, 600), { width: 338, height: 600 });
});

test('small media is not enlarged and invalid dimensions return an empty frame', () => {
    assert.deepEqual(fitMediaPreviewSize(320, 180, 800, 600), { width: 320, height: 180 });
    assert.deepEqual(fitMediaPreviewSize(0, 180, 800, 600), { width: 0, height: 0 });
});

test('unknown video dimensions use the available viewport with a fallback ratio', () => {
    assert.deepEqual(fitMediaPreviewAspectRatio(16 / 9, 390, 494), { width: 390, height: 219 });
    assert.deepEqual(fitMediaPreviewAspectRatio(9 / 16, 390, 494), { width: 278, height: 494 });
});

test('composer and published post use the same compact image gallery layouts for 1 through 10 images', () => {
    const expected = [
        [1, 1, 1, 1], [2, 2, 1, 2], [3, 3, 2, 1.5], [4, 2, 2, 1],
        [5, 6, 2, 3], [6, 6, 2, 3], [7, 6, 2, 3], [8, 6, 2, 3], [9, 6, 2, 3], [10, 6, 2, 3],
    ];

    for (const [count, columns, rows, aspectRatio] of expected) {
        const layout = getPublicationImageGalleryLayout(count);
        assert.equal(layout.columns, columns, `${count} images use the feed gallery columns`);
        assert.equal(layout.rows, rows, `${count} images avoid an empty trailing row`);
        assert.equal(layout.aspectRatio, aspectRatio, `${count} images keep the feed gallery ratio`);
        assert.equal(layout.visibleCount, Math.min(count, 5));
        assert.equal(layout.extraCount, Math.max(0, count - 5));

        if (count > 1) {
            const stage = getPublicationImageGalleryStageStyle(count, 390, 494);
            assert.equal(Math.round(Number.parseFloat(stage.width) / Number.parseFloat(stage.height) * 100) / 100, aspectRatio);
            assert.ok(Number.parseFloat(stage.height) <= 494);
            assert.ok(Number.parseFloat(stage.width) <= 390);
        }
    }

    assert.equal(getPublicationImageTileClasses(3, 0), 'col-span-2 row-span-2 aspect-square');
    assert.equal(getPublicationImageTileClasses(5, 0), 'col-span-3 aspect-square');
    assert.equal(getPublicationImageTileClasses(5, 2), 'col-span-2 aspect-square');
    assert.equal(isPublicationImageGalleryMoreTile(10, 4), true);
    assert.equal(isPublicationImageGalleryMoreTile(10, 3), false);
    assert.equal(isPublicationImageGalleryMoreTile(5, 4), false);
});
