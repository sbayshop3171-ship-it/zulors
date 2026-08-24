const defaultFeedMeta = Object.freeze({
	rankVersion: 'candidate_ranking_v1',
	feedFamily: 'home',
	candidateSources: [],
	reRankAllowed: false,
	sessionWindowSize: 0,
	sessionId: '',
});

const normalizeCandidateSources = function(candidateSources = []) {
	return Array.isArray(candidateSources)
		? Array.from(new Set(candidateSources.filter(Boolean)))
		: [];
};

const extractFeedMeta = function(payload = {}, fallback = {}) {
	const feedMeta = payload?.meta?.feed || payload?.feed || payload || {};

	return {
		...defaultFeedMeta,
		...fallback,
		rankVersion: String(feedMeta.rank_version || fallback.rankVersion || defaultFeedMeta.rankVersion),
		feedFamily: String(feedMeta.feed_family || fallback.feedFamily || defaultFeedMeta.feedFamily),
		candidateSources: normalizeCandidateSources(feedMeta.candidate_sources || fallback.candidateSources || defaultFeedMeta.candidateSources),
		reRankAllowed: Boolean(feedMeta.re_rank_allowed ?? fallback.reRankAllowed ?? defaultFeedMeta.reRankAllowed),
		sessionWindowSize: Number(feedMeta.session_window_size ?? fallback.sessionWindowSize ?? defaultFeedMeta.sessionWindowSize) || 0,
		sessionId: String(feedMeta.session_id || fallback.sessionId || defaultFeedMeta.sessionId || ''),
	};
};

const mergeProtectedTail = function(currentPosts = [], incomingPosts = [], options = {}) {
	const protectedUntilIndex = Math.max(-1, Number(options.protectedUntilIndex ?? -1));
	const maxItems = Math.max(0, Number(options.maxItems || currentPosts.length || incomingPosts.length || 0));
	const pinnedPosts = currentPosts.slice(0, protectedUntilIndex + 1);
	const pinnedIds = new Set(pinnedPosts.map((postData) => postData.id));
	const mergedTail = [];
	const seenIds = new Set(pinnedIds);

	const appendUnique = function(postData) {
		if(! postData || seenIds.has(postData.id)) {
			return;
		}

		seenIds.add(postData.id);
		mergedTail.push(postData);
	};

	incomingPosts.forEach(appendUnique);
	currentPosts.slice(protectedUntilIndex + 1).forEach(appendUnique);

	return pinnedPosts.concat(mergedTail).slice(0, maxItems);
};

const buildViewportWindow = function(totalCount, activeIndex, before = 4, after = 6) {
	const safeTotalCount = Math.max(0, Number(totalCount || 0));

	if(! safeTotalCount) {
		return {
			start: 0,
			end: -1,
		};
	}

	const safeActiveIndex = Math.max(0, Math.min(safeTotalCount - 1, Number(activeIndex || 0)));

	return {
		start: Math.max(0, safeActiveIndex - Math.max(0, before)),
		end: Math.min(safeTotalCount - 1, safeActiveIndex + Math.max(0, after)),
	};
};

export {
	buildViewportWindow,
	defaultFeedMeta,
	extractFeedMeta,
	mergeProtectedTail
};
