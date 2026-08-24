import { readCache, writeCache } from '../cache/index.js';

const reelsSignalMemoryTtlMs = 1000 * 60 * 60 * 24 * 45;
const reelsMaxSignalEntries = 320;
const defaultMinimumFreshReels = 12;
const defaultViewerKey = 'guest';

const signalProfiles = Object.freeze({
	post_hide: {
		eventType: 'post_hide',
		suppressForMs: 1000 * 60 * 60 * 24 * 120,
		rerankWeight: 6,
		hardSuppress: true,
		immediateRerank: true,
		priority: 40
	},
	post_not_interested: {
		eventType: 'post_not_interested',
		suppressForMs: 1000 * 60 * 60 * 24 * 60,
		rerankWeight: 5,
		hardSuppress: true,
		immediateRerank: true,
		priority: 35
	},
	video_loop: {
		eventType: 'video_loop',
		suppressForMs: 1000 * 60 * 60 * 8,
		rerankWeight: 3,
		hardSuppress: false,
		immediateRerank: true,
		priority: 30
	},
	video_complete: {
		eventType: 'video_complete',
		suppressForMs: 1000 * 60 * 60 * 4,
		rerankWeight: 2.5,
		hardSuppress: false,
		immediateRerank: true,
		priority: 25
	},
	video_skip: {
		eventType: 'video_skip',
		suppressForMs: 1000 * 60 * 90,
		rerankWeight: 3.5,
		hardSuppress: false,
		immediateRerank: true,
		priority: 20
	},
	video_watch: {
		eventType: 'video_watch',
		suppressForMs: 1000 * 60 * 20,
		rerankWeight: 1.25,
		hardSuppress: false,
		immediateRerank: false,
		priority: 10
	}
});

const toFiniteNumber = function(value, fallback = 0) {
	const numericValue = Number(value);

	return Number.isFinite(numericValue) ? numericValue : fallback;
};

const clamp = function(value, min, max) {
	return Math.max(min, Math.min(max, value));
};

const nowMs = function() {
	return Date.now();
};

const normalizeViewerKey = function(viewerKey = defaultViewerKey) {
	const nextKey = String(viewerKey || defaultViewerKey).trim();

	return nextKey || defaultViewerKey;
};

const normalizePostId = function(postId) {
	const numericId = Math.trunc(toFiniteNumber(postId, 0));

	return numericId > 0 ? String(numericId) : '';
};

const reelsSignalMemoryKey = function(viewerKey = defaultViewerKey) {
	return `colibri.reels.signal-memory.v2.${normalizeViewerKey(viewerKey)}`;
};

const emptySignalMemory = function() {
	return {
		entries: {}
	};
};

const readSignalMemory = function(viewerKey = defaultViewerKey) {
	const cacheEntry = readCache(reelsSignalMemoryKey(viewerKey), emptySignalMemory(), reelsSignalMemoryTtlMs);

	if(! cacheEntry || typeof cacheEntry !== 'object') {
		return emptySignalMemory();
	}

	return {
		entries: typeof cacheEntry.entries === 'object' && cacheEntry.entries ? cacheEntry.entries : {}
	};
};

const writeSignalMemory = function(viewerKey = defaultViewerKey, memory = emptySignalMemory()) {
	writeCache(reelsSignalMemoryKey(viewerKey), memory);
};

const pruneSignalEntries = function(entries = {}, currentTimeMs = nowMs()) {
	const nextEntries = {};

	Object.entries(entries).forEach(([postId, entry]) => {
		if(! postId || ! entry || typeof entry !== 'object') {
			return;
		}

		const lastSignalAt = toFiniteNumber(entry.lastSignalAt, 0);
		const suppressUntil = toFiniteNumber(entry.suppressUntil, 0);

		if(suppressUntil <= currentTimeMs && (currentTimeMs - lastSignalAt) > reelsSignalMemoryTtlMs) {
			return;
		}

		nextEntries[postId] = {
			eventType: String(entry.eventType || 'video_watch'),
			lastSignalAt: lastSignalAt,
			suppressUntil: suppressUntil,
			hardSuppress: Boolean(entry.hardSuppress),
			totalWatchMs: Math.max(0, Math.round(toFiniteNumber(entry.totalWatchMs, 0))),
			completionRate: clamp(toFiniteNumber(entry.completionRate, 0), 0, 5),
			priority: toFiniteNumber(entry.priority, 0)
		};
	});

	const limitedEntries = Object.entries(nextEntries)
		.sort((left, right) => {
			return toFiniteNumber(right[1]?.lastSignalAt, 0) - toFiniteNumber(left[1]?.lastSignalAt, 0);
		})
		.slice(0, reelsMaxSignalEntries);

	return Object.fromEntries(limitedEntries);
};

const resolveCompletionRate = function(payload = {}) {
	const explicitCompletionRate = toFiniteNumber(payload.completionRate, NaN);

	if(Number.isFinite(explicitCompletionRate)) {
		return clamp(explicitCompletionRate, 0, 5);
	}

	const durationSeconds = toFiniteNumber(payload.durationSeconds, 0);
	const totalWatchMs = Math.max(
		0,
		Math.round(toFiniteNumber(payload.totalWatchMs, 0) || toFiniteNumber(payload.watchMs, 0))
	);

	if(durationSeconds <= 0 || totalWatchMs <= 0) {
		return 0;
	}

	return clamp(totalWatchMs / 1000 / durationSeconds, 0, 5);
};

const normalizeReelInteractionEventType = function(payload = {}) {
	const requestedEventType = String(payload.eventType || 'video_watch');
	const totalWatchMs = Math.max(
		0,
		Math.round(toFiniteNumber(payload.totalWatchMs, 0) || toFiniteNumber(payload.watchMs, 0))
	);
	const completionRate = resolveCompletionRate(payload);
	const loopCount = Math.max(0, Math.round(toFiniteNumber(payload.loopCount, 0)));

	if(requestedEventType === 'post_hide' || requestedEventType === 'post_not_interested') {
		return requestedEventType;
	}

	if(requestedEventType === 'video_loop' || loopCount > 0 || completionRate > 1.05) {
		return 'video_loop';
	}

	if(requestedEventType === 'video_complete' || completionRate >= 0.92) {
		return 'video_complete';
	}

	if(requestedEventType === 'video_skip') {
		return 'video_skip';
	}

	if(totalWatchMs >= 250 && (totalWatchMs < 1500 || completionRate < 0.35)) {
		return 'video_skip';
	}

	return 'video_watch';
};

const buildReelInteractionSignal = function(payload = {}) {
	const postId = normalizePostId(payload.postId);

	if(! postId) {
		return null;
	}

	const totalWatchMs = Math.max(
		0,
		Math.round(toFiniteNumber(payload.totalWatchMs, 0) || toFiniteNumber(payload.watchMs, 0))
	);
	const normalizedEventType = normalizeReelInteractionEventType({
		...payload,
		postId: postId,
		totalWatchMs: totalWatchMs
	});
	const profile = signalProfiles[normalizedEventType] || signalProfiles.video_watch;

	if(
		totalWatchMs < 250
		&& ! profile.hardSuppress
		&& normalizedEventType !== 'video_loop'
		&& normalizedEventType !== 'video_complete'
	) {
		return null;
	}

	const completionRate = resolveCompletionRate({
		...payload,
		totalWatchMs: totalWatchMs
	});

	return {
		postId: postId,
		eventType: profile.eventType,
		totalWatchMs: totalWatchMs,
		completionRate: completionRate,
		suppressForMs: profile.suppressForMs,
		rerankWeight: profile.rerankWeight,
		hardSuppress: profile.hardSuppress,
		immediateRerank: profile.immediateRerank,
		priority: profile.priority
	};
};

const recordReelInteractionSignal = function(payload = {}, options = {}) {
	const signal = buildReelInteractionSignal(payload);

	if(! signal) {
		return null;
	}

	const currentTimeMs = nowMs();
	const viewerKey = normalizeViewerKey(options.viewerKey);
	const memory = readSignalMemory(viewerKey);
	const nextEntries = pruneSignalEntries(memory.entries, currentTimeMs);
	const previousEntry = nextEntries[signal.postId] || {};

	nextEntries[signal.postId] = {
		eventType: signal.eventType,
		lastSignalAt: currentTimeMs,
		suppressUntil: Math.max(
			toFiniteNumber(previousEntry.suppressUntil, 0),
			currentTimeMs + Math.max(0, signal.suppressForMs)
		),
		hardSuppress: Boolean(previousEntry.hardSuppress) || signal.hardSuppress,
		totalWatchMs: Math.max(toFiniteNumber(previousEntry.totalWatchMs, 0), signal.totalWatchMs),
		completionRate: Math.max(toFiniteNumber(previousEntry.completionRate, 0), signal.completionRate),
		priority: Math.max(toFiniteNumber(previousEntry.priority, 0), signal.priority)
	};

	writeSignalMemory(viewerKey, {
		entries: pruneSignalEntries(nextEntries, currentTimeMs)
	});

	return signal;
};

const reorderReelsBySignalEntries = function(posts = [], entries = {}, options = {}) {
	const currentTimeMs = toFiniteNumber(options.currentTimeMs, nowMs());
	const minimumFresh = Math.max(0, Math.round(toFiniteNumber(options.minimumFresh, defaultMinimumFreshReels)));
	const uniquePosts = [];
	const seenIds = new Set();

	posts.forEach((postData) => {
		const postId = normalizePostId(postData?.id);

		if(! postId || seenIds.has(postId)) {
			return;
		}

		seenIds.add(postId);
		uniquePosts.push(postData);
	});

	const freshPosts = [];
	const softSuppressedPosts = [];
	const hardSuppressedPosts = [];

	uniquePosts.forEach((postData) => {
		const postId = normalizePostId(postData?.id);
		const entry = entries[postId];

		if(! entry || toFiniteNumber(entry.suppressUntil, 0) <= currentTimeMs) {
			freshPosts.push(postData);

			return;
		}

		const targetBucket = entry.hardSuppress ? hardSuppressedPosts : softSuppressedPosts;

		targetBucket.push({
			postData: postData,
			remainingMs: Math.max(0, toFiniteNumber(entry.suppressUntil, currentTimeMs) - currentTimeMs),
			priority: toFiniteNumber(entry.priority, 0),
			lastSignalAt: toFiniteNumber(entry.lastSignalAt, 0)
		});
	});

	const sortSuppressed = function(left, right) {
		if(left.remainingMs !== right.remainingMs) {
			return left.remainingMs - right.remainingMs;
		}

		if(left.priority !== right.priority) {
			return right.priority - left.priority;
		}

		return right.lastSignalAt - left.lastSignalAt;
	};

	softSuppressedPosts.sort(sortSuppressed);
	hardSuppressedPosts.sort(sortSuppressed);

	const prioritizedSoftPosts = softSuppressedPosts.map((entry) => {
		return entry.postData;
	});
	const prioritizedHardPosts = hardSuppressedPosts.map((entry) => {
		return entry.postData;
	});

	if(freshPosts.length >= minimumFresh) {
		return freshPosts.concat(prioritizedSoftPosts).slice(0, uniquePosts.length);
	}

	return freshPosts
		.concat(prioritizedSoftPosts)
		.concat(prioritizedHardPosts)
		.slice(0, uniquePosts.length);
};

const prioritizeReelsByRecentSignals = function(posts = [], options = {}) {
	const viewerKey = normalizeViewerKey(options.viewerKey);
	const memory = readSignalMemory(viewerKey);

	return reorderReelsBySignalEntries(posts, pruneSignalEntries(memory.entries, nowMs()), options);
};

export {
	buildReelInteractionSignal,
	normalizeReelInteractionEventType,
	prioritizeReelsByRecentSignals,
	recordReelInteractionSignal,
	reorderReelsBySignalEntries
};
