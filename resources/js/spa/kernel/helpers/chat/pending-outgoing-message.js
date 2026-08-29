const DEFAULT_OUTGOING_PARTICIPANT_COLOR = '#4f46e5';

const HTML_ESCAPE_MAP = {
	'&': '&amp;',
	'<': '&lt;',
	'>': '&gt;',
	'"': '&quot;',
	"'": '&#39;'
};

function escapeHtml(value = '') {
	return String(value ?? '').replace(/[&<>"']/g, (character) => {
		return HTML_ESCAPE_MAP[character] || character;
	});
}

function buildUserName(userData = {}) {
	if(userData?.name) {
		return userData.name;
	}

	const fullName = [userData?.first_name, userData?.last_name].filter(Boolean).join(' ').trim();

	return fullName || userData?.username || 'You';
}

function buildMessageDate(timestamp = Date.now()) {
	const now = new Date(timestamp);

	return {
		iso: now.toISOString(),
		time_ago: 'now',
		generic: now.toISOString().slice(0, 10),
		date: now.toLocaleDateString(undefined, {
			month: 'short',
			day: 'numeric',
			year: 'numeric',
		}),
	};
}

function buildUserPreview(userData = {}, isAuthUser = true) {
	return {
		id: userData?.id ?? null,
		name: buildUserName(userData),
		avatar_url: userData?.avatar_url || userData?.avatar || null,
		is_auth_user: Boolean(isAuthUser || userData?.is_auth_user),
		username: userData?.username || '',
		caption: userData?.caption || userData?.bio || '',
		verified: Boolean(userData?.verified),
	};
}

function normalizeStoredContent(content = '') {
	return String(content ?? '').trim();
}

function buildReplySnapshot(messageData = null) {
	if(! messageData) {
		return null;
	}

	if(messageData.user && ! messageData.relations?.user && Object.prototype.hasOwnProperty.call(messageData, 'participant')) {
		return {
			content: messageData.content ?? '',
			type: messageData.type || 'text',
			relations: {
				media: messageData.relations?.media || null,
				link_snapshot: messageData.relations?.link_snapshot || null,
			},
			user: {
				id: messageData.user.id ?? null,
				name: messageData.user.name || buildUserName(messageData.user),
				username: messageData.user.username || '',
				avatar_url: messageData.user.avatar_url || null,
				verified: Boolean(messageData.user.verified),
			},
			participant: {
				color: messageData.participant?.color || DEFAULT_OUTGOING_PARTICIPANT_COLOR,
			},
		};
	}

	const messageUser = messageData.relations?.user || messageData.user || {};
	const mediaData = messageData.relations?.media || messageData.media || null;
	const linkSnapshot = messageData.relations?.link_snapshot || messageData.link_snapshot || null;

	return {
		content: messageData.content ?? '',
		type: messageData.type || 'text',
		relations: {
			media: mediaData && ! Array.isArray(mediaData) && Object.keys(mediaData).length ? mediaData : null,
			link_snapshot: linkSnapshot || null,
		},
		user: {
			id: messageUser.id ?? null,
			name: messageUser.name || buildUserName(messageUser),
			username: messageUser.username || '',
			avatar_url: messageUser.avatar_url || null,
			verified: Boolean(messageUser.verified),
		},
		participant: {
			color: messageData.relations?.participant?.color || messageData.participant?.color || DEFAULT_OUTGOING_PARTICIPANT_COLOR,
		},
	};
}

function buildOutgoingMessageSignature({
	content = '',
	messageType = 'text',
	parentMessage = null,
	parentId = null,
	clientUid = '',
} = {}) {
	if(clientUid) {
		return `client:${clientUid}`;
	}

	const replySnapshot = buildReplySnapshot(parentMessage);
	const replySignature = replySnapshot
		? [
			replySnapshot.type || 'text',
			replySnapshot.user?.id || '',
			normalizeStoredContent(replySnapshot.content || ''),
			replySnapshot.relations?.link_snapshot?.url || '',
			replySnapshot.relations?.media?.id || replySnapshot.relations?.media?.source_url || '',
		].join(':')
		: (parentId ? `parent:${parentId}` : 'parent:none');

	return [
		messageType || 'text',
		replySignature,
		normalizeStoredContent(content),
	].join('|');
}

function buildOutgoingMessageMatchKey(messageData = {}) {
	const clientUid = messageData?.meta?.client_uid
		|| messageData?.meta?.local_outgoing?.client_uid
		|| messageData?.client_uid
		|| '';

	if(clientUid) {
		return `client:${clientUid}`;
	}

	return buildOutgoingMessageSignature({
		content: messageData?.content ?? '',
		messageType: messageData?.message_type || messageData?.type || 'text',
		parentMessage: messageData?.relations?.parent || messageData?.parent_message || null,
		parentId: messageData?.parent_id || messageData?.parent?.id || null,
	});
}

function findPendingOutgoingMessageIndex(messages = [], messageData = {}) {
	const matchKey = buildOutgoingMessageMatchKey(messageData);

	return messages.findIndex((messageItem) => {
		if(! messageItem?.meta?.local_outgoing?.pending) {
			return false;
		}

		const localKey = messageItem?.meta?.client_uid
			|| messageItem?.meta?.local_outgoing?.client_uid
			|| buildOutgoingMessageSignature({
				content: messageItem?.content ?? '',
				messageType: messageItem?.type || 'text',
				parentMessage: messageItem?.relations?.parent || null,
				parentId: messageItem?.parent_id || messageItem?.relations?.parent?.id || null,
			});

		return localKey === matchKey;
	});
}

function createOptimisticOutgoingMessage({
	chatId,
	userData = {},
	content = '',
	messageType = 'text',
	parentMessage = null,
	parentId = null,
	clientUid = '',
} = {}) {
	const safeClientUid = clientUid || `msg-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
	const replySnapshot = buildReplySnapshot(parentMessage);
	const safeContent = escapeHtml(content);
	const messageSignature = buildOutgoingMessageSignature({
		content: safeContent,
		messageType: messageType,
		parentMessage: replySnapshot,
		parentId: parentId,
		clientUid: safeClientUid,
	});

	return {
		id: `local-${safeClientUid}`,
		user_id: userData?.id,
		chat_uuid: chatId,
		content: safeContent,
		type: messageType || 'text',
		has_parent: Boolean(replySnapshot),
		date: buildMessageDate(),
		relations: {
			user: buildUserPreview(userData, true),
			participant: {
				color: DEFAULT_OUTGOING_PARTICIPANT_COLOR,
			},
			parent: replySnapshot,
			media: [],
			reactions: [],
			link_snapshot: null,
		},
		meta: {
			client_uid: safeClientUid,
			is_deleted: false,
			is_translatable: false,
			permissions: {
				can_edit: false,
				can_delete: false,
			},
			local_outgoing: {
				pending: true,
				client_uid: safeClientUid,
				signature: messageSignature,
				created_at: new Date().toISOString(),
			},
		},
	};
}

export {
	escapeHtml,
	createOptimisticOutgoingMessage,
	buildOutgoingMessageMatchKey,
	buildOutgoingMessageSignature,
	buildReplySnapshot,
	findPendingOutgoingMessageIndex,
};
