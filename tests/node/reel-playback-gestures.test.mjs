import test from 'node:test';
import assert from 'node:assert/strict';

import { createReelPlaybackGestureController } from '../../resources/js/spa/kernel/services/media/reel-playback-gestures.js';

test('single tap invokes playback immediately without waiting for the double-tap window', () => {
	let now = 100;
	const events = [];
	const controller = createReelPlaybackGestureController({
		now: () => now,
		getPlaybackSnapshot: () => ({ isPlaying: false }),
		onSingleTap: () => events.push('play'),
		onDoubleTap: () => events.push('reaction'),
		restorePlayback: () => events.push('restore')
	});

	assert.equal(controller.handleTap(), 'single');
	assert.deepEqual(events, ['play']);
	assert.equal(now, 100);

	now += 221;
	assert.equal(controller.handleTap(), 'single');
	assert.deepEqual(events, ['play', 'play']);
});

test('double tap restores the playback snapshot and emits a separate reaction', () => {
	let now = 100;
	const events = [];
	const snapshot = { isPlaying: true, manualPaused: false, playRequested: true };
	const controller = createReelPlaybackGestureController({
		now: () => now,
		getPlaybackSnapshot: () => snapshot,
		onSingleTap: () => events.push('toggle'),
		onDoubleTap: () => events.push('reaction'),
		restorePlayback: (previousState) => events.push(['restore', previousState])
	});

	controller.handleTap();
	now += 100;
	assert.equal(controller.handleTap(), 'double');
	assert.deepEqual(events, ['toggle', ['restore', snapshot], 'reaction']);
});
