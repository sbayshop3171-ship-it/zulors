import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

export function sendFeedFeedbackEvent({
	eventType = '',
	postId = null,
	sessionId = '',
	feedType = 'reels',
	source = 'reels',
	position = 0,
	refreshReason = 'feedback'
} = {}) {
	if(! eventType || ! postId) {
		return Promise.resolve();
	}

	return colibriAPI().userTimeline().with({
		events: [{
			event_type: eventType,
			post_id: Number(postId),
			session_id: sessionId || null,
			feed_type: feedType,
			source: source,
			position: Number(position || 0),
			refresh_reason: refreshReason
		}]
	}).sendTo('telemetry/events');
}
