import test from 'node:test';
import assert from 'node:assert/strict';

import { shouldHydrateCachedProfileSnapshot } from '../../resources/js/spa/apps/mobile/views/profile/profile-cache.js';
import { clampCropOffset, getCropZoomRange, getRotatedImageDimensions } from '../../resources/js/spa/kernel/vue/components/media/image/image-crop-geometry.js';

test('background profile refresh preserves the loaded owner profile while the photo picker is open', () => {
	assert.equal(shouldHydrateCachedProfileSnapshot({ id: 42, meta: { is_owner: true } }, false), false);
	assert.equal(shouldHydrateCachedProfileSnapshot({}, false), true);
	assert.equal(shouldHydrateCachedProfileSnapshot({ id: 42 }, true), true);
});

test('crop geometry rotates the source dimensions and keeps the crop frame reachable', () => {
	assert.deepEqual(getRotatedImageDimensions(400, 200, 90), { width: 200, height: 400 });

	const zoomRange = getCropZoomRange(400, 200, 200, 200, 0);
	assert.equal(zoomRange.minZoom, 0.5);
	assert.equal(zoomRange.coverZoom, 1);

	const rotatedZoomRange = getCropZoomRange(400, 200, 200, 200, 45);
	assert.ok(rotatedZoomRange.coverZoom > 1);

	assert.deepEqual(clampCropOffset(120, -90, 400, 200, 200, 200, 0), {
		offsetX: 100,
		offsetY: 0,
	});

	// At 90 degrees, screen-space vertical movement maps to image-space horizontal
	// movement. Clamp after rotation so panning cannot reveal blank crop corners.
	assert.deepEqual(clampCropOffset(500, 0, 400, 200, 100, 100, 90), {
		offsetX: 50,
		offsetY: 0,
	});
});
