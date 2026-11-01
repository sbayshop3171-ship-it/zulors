const defaultDoubleTapWindowMs = 220;

function createReelPlaybackGestureController(options = {}) {
	const now = options.now || (() => Date.now());
	const windowMs = Math.max(0, Number(options.doubleTapWindowMs || defaultDoubleTapWindowMs));
	let previousTap = null;

	return {
		handleTap() {
			const tappedAt = now();

			if(previousTap && tappedAt >= previousTap.at && (tappedAt - previousTap.at) <= windowMs) {
				const playbackSnapshot = previousTap.playbackSnapshot;
				previousTap = null;

				options.restorePlayback?.(playbackSnapshot);
				options.onDoubleTap?.();

				return 'double';
			}

			previousTap = {
				at: tappedAt,
				playbackSnapshot: options.getPlaybackSnapshot?.()
			};

			options.onSingleTap?.();

			return 'single';
		},
		reset() {
			previousTap = null;
		}
	};
}

export {
	defaultDoubleTapWindowMs,
	createReelPlaybackGestureController
};
