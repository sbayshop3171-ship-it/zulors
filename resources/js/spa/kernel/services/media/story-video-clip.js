import { readVideoFileMetadata } from '@/kernel/services/media/video-metadata.js';

export const STORY_VIDEO_CLIP_SECONDS = 60;

export const isStoryVideoFile = (file) => {
    return Boolean(file?.type && file.type.startsWith('video/'));
};

export const getStoryVideoClipCandidate = async (file) => {
    if(! isStoryVideoFile(file)) {
        return null;
    }

    const metadata = await readVideoFileMetadata(file);
    const durationSeconds = Math.max(0, Number(metadata.duration_seconds || 0));

    if(durationSeconds <= STORY_VIDEO_CLIP_SECONDS) {
        return {
            requiresTrim: false,
            clipStartSeconds: 0,
            clipDurationSeconds: Math.max(1, Math.ceil(durationSeconds || STORY_VIDEO_CLIP_SECONDS)),
            durationSeconds: durationSeconds,
            metadata: metadata
        };
    }

    return {
        requiresTrim: true,
        file: file,
        objectUrl: URL.createObjectURL(file),
        durationSeconds: durationSeconds,
        maxStartSeconds: Math.max(0, Math.floor(durationSeconds - STORY_VIDEO_CLIP_SECONDS)),
        clipStartSeconds: 0,
        clipDurationSeconds: STORY_VIDEO_CLIP_SECONDS,
        metadata: metadata
    };
};

export const storyClipUploadOptions = (clipCandidate = null) => {
    if(! clipCandidate) {
        return {};
    }

    return {
        clip_start_seconds: Math.max(0, Math.floor(Number(clipCandidate.clipStartSeconds || 0))),
        clip_duration_seconds: Math.max(1, Math.min(
            STORY_VIDEO_CLIP_SECONDS,
            Math.ceil(Number(clipCandidate.clipDurationSeconds || STORY_VIDEO_CLIP_SECONDS))
        ))
    };
};

export const formatStoryClipTime = (seconds) => {
    const normalizedSeconds = Math.max(0, Math.floor(Number(seconds || 0)));
    const minutes = Math.floor(normalizedSeconds / 60);
    const remainingSeconds = normalizedSeconds % 60;

    return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
};
