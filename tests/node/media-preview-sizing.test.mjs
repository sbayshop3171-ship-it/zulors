import test from 'node:test';
import assert from 'node:assert/strict';
import { fitMediaPreviewAspectRatio, fitMediaPreviewSize } from '../../resources/js/spa/kernel/services/media/media-preview-sizing.js';

test('media preview sizing preserves portrait and landscape aspect ratios within viewport bounds', () => {
    assert.deepEqual(fitMediaPreviewSize(3840, 2400, 430, 600), { width: 430, height: 269 });
    assert.deepEqual(fitMediaPreviewSize(1080, 1920, 430, 600), { width: 338, height: 600 });
});

test('small media is not enlarged and invalid dimensions return an empty frame', () => {
    assert.deepEqual(fitMediaPreviewSize(320, 180, 800, 600), { width: 320, height: 180 });
    assert.deepEqual(fitMediaPreviewSize(0, 180, 800, 600), { width: 0, height: 0 });
});

test('unknown media dimensions use the available viewport with a fallback ratio', () => {
    assert.deepEqual(fitMediaPreviewAspectRatio(16 / 9, 390, 494), { width: 390, height: 219 });
    assert.deepEqual(fitMediaPreviewAspectRatio(9 / 16, 390, 494), { width: 278, height: 494 });
});
