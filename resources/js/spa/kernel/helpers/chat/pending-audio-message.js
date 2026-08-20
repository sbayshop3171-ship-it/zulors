import { resolveMediaDuration } from '@/kernel/helpers/media/audio/index.js';

function buildUserName(userData = {}) {
    if(userData?.name) {
        return userData.name;
    }

    const fullName = [userData?.first_name, userData?.last_name].filter(Boolean).join(' ').trim();

    return fullName || userData?.username || 'You';
}

function buildMessageDate() {
    const now = new Date();

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

export function buildPendingAudioLocalState({
    stage = 'uploading',
    uploadProgress = 0,
    clientUid = '',
} = {}) {
    return {
        pending: true,
        stage: stage,
        upload_progress: Math.max(0, Math.min(100, Math.round(Number(uploadProgress) || 0))),
        client_uid: clientUid,
    };
}

export function isPendingAudioMessage(messageData = {}) {
    const mediaItem = messageData?.relations?.media;

    return messageData?.type === 'audio'
        && mediaItem
        && ! Array.isArray(mediaItem)
        && (mediaItem?.status === 'processing' || ! mediaItem?.source_url);
}

export function withLocalAudioState(messageData = {}, localAudioState = null) {
    const nextMeta = {
        ...(messageData?.meta || {}),
    };

    if(localAudioState) {
        nextMeta.local_audio = localAudioState;
    }
    else {
        delete nextMeta.local_audio;
    }

    return {
        ...messageData,
        meta: nextMeta,
    };
}

export function mergeIncomingAudioMessage(existingMessage = null, incomingMessage = {}) {
    if(! existingMessage?.meta?.local_audio || incomingMessage?.type !== 'audio') {
        return incomingMessage;
    }

    if(! isPendingAudioMessage(incomingMessage)) {
        return withLocalAudioState(incomingMessage, null);
    }

    return withLocalAudioState(incomingMessage, existingMessage.meta.local_audio);
}

export function createPendingAudioMessage({
    localId,
    chatId,
    userData = {},
    durationSeconds = 1,
    extension = 'webm',
    fileName = '',
    blobSize = 0,
    parentMessage = null,
    clientUid = '',
} = {}) {
    const safeDuration = Math.max(1, Math.ceil(Number(durationSeconds) || 1));
    const safeFileName = fileName || `voice-note-${Date.now()}.${extension}`;
    const mediaItem = {
        id: `${localId}-media`,
        mediaable_id: localId,
        source_url: null,
        preview_url: null,
        extension: extension,
        type: 'audio',
        size: Math.max(0, Number(blobSize) || 0),
        status: 'processing',
        thumbnail_url: null,
        thumbnail_size: '',
        lqip_base64: null,
        metadata: {
            duration: resolveMediaDuration(null, safeDuration),
            duration_seconds: safeDuration,
            file_name: safeFileName,
            original_name: safeFileName,
        },
    };

    return {
        id: localId,
        user_id: userData?.id,
        chat_uuid: chatId,
        content: '',
        type: 'audio',
        has_parent: Boolean(parentMessage?.id),
        date: buildMessageDate(),
        relations: {
            user: {
                id: userData?.id,
                name: buildUserName(userData),
                username: userData?.username || '',
                avatar_url: userData?.avatar_url || userData?.avatar || null,
                verified: Boolean(userData?.verified),
            },
            participant: {
                color: '#4f46e5',
            },
            parent: parentMessage || null,
            media: mediaItem,
            reactions: [],
            link_snapshot: null,
        },
        meta: {
            is_deleted: false,
            is_translatable: false,
            permissions: {
                can_edit: false,
                can_delete: false,
            },
            local_audio: buildPendingAudioLocalState({
                stage: 'uploading',
                uploadProgress: 0,
                clientUid: clientUid,
            }),
        },
    };
}
