import test from 'node:test';
import assert from 'node:assert/strict';
import { getImageAspectRatio, getImageDimensions } from '../../resources/js/spa/kernel/services/media/image-presentation.js';

test('image presentation uses natural metadata dimensions for portrait, landscape, and square media', () => {
    assert.equal(getImageAspectRatio({ metadata: { dimensions: { width: 1080, height: 2400 } } }), 0.45);
    assert.equal(getImageAspectRatio({ metadata: { dimensions: { width: 3840, height: 2160 } } }), 16 / 9);
    assert.equal(getImageAspectRatio({ dimensions: { width: 1200, height: 1200 } }), 1);
});

test('loaded image dimensions override stale metadata and original dimensions are a fallback', () => {
    const mediaItem = {
        metadata: {
            dimensions: { width: 400, height: 300 },
            original_dimensions: { width: 900, height: 1600 },
        },
    };

    assert.deepEqual(getImageDimensions(mediaItem), { width: 400, height: 300 });
    assert.deepEqual(getImageDimensions(mediaItem, { width: 1080, height: 1920 }), { width: 1080, height: 1920 });
    assert.equal(getImageAspectRatio(mediaItem, { width: 1080, height: 1920 }), 9 / 16);
    assert.equal(getImageAspectRatio({ metadata: { original_dimensions: { width: 900, height: 1600 } } }), 900 / 1600);
});

test('unknown or invalid dimensions use a safe ratio until the image loads', () => {
    assert.equal(getImageAspectRatio({ width: 0, height: -2 }), 4 / 3);
    assert.equal(getImageAspectRatio({ metadata: { aspect_ratio: 0.5 } }), 0.5);
    assert.equal(getImageAspectRatio({ metadata: { aspect_ratio: Infinity } }), 4 / 3);
});
