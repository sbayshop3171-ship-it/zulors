const PostType = Object.freeze({
    TEXT: 'text',
    IMAGE: 'image',
    VIDEO: 'video',
    AUDIO: 'audio',
    RECORDING: 'recording',
    DOCUMENT: 'document',
    POLL: 'poll',
    GIF: 'gif'
});

const PostTypeUtils = {
    isMedia: (type) => {
        return type === PostType.IMAGE ||
        type === PostType.AUDIO ||
        type === PostType.VIDEO ||
        type === PostType.GIF ||
        type === PostType.DOCUMENT;
    },
    isText: (type) => {
        return !type || type === PostType.TEXT;
    },
    isVideo: (type) => {
        return type === PostType.VIDEO;
    },
    isAudio: (type) => {
        return type === PostType.AUDIO;
    },
    isImage: (type) => {
        return type === PostType.IMAGE;
    },
    isRecording: (type) => {
        return type === PostType.RECORDING;
    },
    isGif: (type) => {
        return type === PostType.GIF;
    },
    isPoll: (type) => {
        return type === PostType.POLL;
    },
    isTextified: (type) => {
        return type === PostType.TEXT;
    },
    isPicture: (type) => {
        return type === PostType.IMAGE || type === PostType.GIF;
    },
    isDocument: (type) => {
        return type === PostType.DOCUMENT;
    }
};

export { PostType, PostTypeUtils };