import test from 'node:test';
import assert from 'node:assert/strict';

import {
    elementImageDimensions,
    elementVideoDimensions,
    shouldUseStoryBlurBackdrop,
    storyMediaDimensions,
    storyMediaObjectFitClass
} from '../../resources/js/spa/kernel/services/media/story-media-presentation.js';

test('native 9:16 story media fills the player without blur fallback', () => {
    const media = { metadata: { dimensions: { width: 1080, height: 1920 } } };

    assert.equal(shouldUseStoryBlurBackdrop(media), false);
    assert.equal(storyMediaObjectFitClass(media), 'object-cover');
});

test('landscape story media uses contain foreground with blurred backdrop', () => {
    const media = { metadata: { dimensions: { width: 1920, height: 1080 } } };

    assert.equal(shouldUseStoryBlurBackdrop(media), true);
    assert.equal(storyMediaObjectFitClass(media), 'object-contain');
});

test('aspect ratio metadata and loaded element dimensions are accepted', () => {
    assert.equal(storyMediaObjectFitClass({ metadata: { aspect_ratio: 0.5625 } }), 'object-cover');
    assert.deepEqual(elementVideoDimensions({ videoWidth: 640, videoHeight: 360 }), { width: 640, height: 360 });
    assert.deepEqual(elementImageDimensions({ naturalWidth: 720, naturalHeight: 1280 }), { width: 720, height: 1280 });
    assert.equal(storyMediaDimensions({}, { width: 720, height: 1280 }).aspect_ratio, 720 / 1280);
});
