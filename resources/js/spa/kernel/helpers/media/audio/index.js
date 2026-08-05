function padDurationUnit(value) {
    return String(Math.max(0, Math.floor(value || 0))).padStart(2, '0');
}

export function durationSecondsToObject(seconds = 0) {
    const safeSeconds = Math.max(0, Math.ceil(Number(seconds) || 0));

    return {
        hours: padDurationUnit(Math.floor(safeSeconds / 3600)),
        minutes: padDurationUnit(Math.floor((safeSeconds % 3600) / 60)),
        seconds: padDurationUnit(safeSeconds % 60),
    };
}

export function durationObjectToSeconds(duration = null) {
    if (typeof duration === 'number') {
        return Math.max(0, Math.ceil(duration));
    }

    if (!duration || typeof duration !== 'object') {
        return 0;
    }

    const hours = Number(duration.hours || 0);
    const minutes = Number(duration.minutes || 0);
    const seconds = Number(duration.seconds || 0);

    return Math.max(0, Math.ceil((hours * 3600) + (minutes * 60) + seconds));
}

export function resolveMediaDuration(duration = null, fallbackSeconds = 0) {
    if (duration && typeof duration === 'object') {
        return {
            hours: padDurationUnit(duration.hours || 0),
            minutes: padDurationUnit(duration.minutes || 0),
            seconds: padDurationUnit(duration.seconds || 0),
        };
    }

    return durationSecondsToObject(fallbackSeconds);
}

export function inferAudioExtension(mimeType = '', fallbackExtension = 'webm') {
    const normalizedMimeType = String(mimeType).toLowerCase();

    if (normalizedMimeType.includes('audio/mp4') || normalizedMimeType.includes('audio/x-m4a')) {
        return 'm4a';
    }

    if (normalizedMimeType.includes('audio/mpeg') || normalizedMimeType.includes('audio/mp3')) {
        return 'mp3';
    }

    if (normalizedMimeType.includes('audio/ogg')) {
        return 'ogg';
    }

    if (normalizedMimeType.includes('audio/wav') || normalizedMimeType.includes('audio/x-wav')) {
        return 'wav';
    }

    if (normalizedMimeType.includes('audio/aac')) {
        return 'aac';
    }

    if (normalizedMimeType.includes('audio/webm')) {
        return 'webm';
    }

    return fallbackExtension;
}

export async function probeAudioDuration(fileOrBlob, fallbackSeconds = 1) {
    const safeFallback = Math.max(1, Math.ceil(Number(fallbackSeconds) || 1));

    if (!fileOrBlob) {
        return safeFallback;
    }

    return await new Promise((resolve) => {
        const audio = document.createElement('audio');
        const objectUrl = URL.createObjectURL(fileOrBlob);
        let resolved = false;

        const finish = (duration = safeFallback) => {
            if (resolved) {
                return;
            }

            resolved = true;
            audio.removeAttribute('src');
            audio.load();
            URL.revokeObjectURL(objectUrl);
            resolve(Math.max(1, Math.ceil(Number(duration) || safeFallback)));
        };

        const resolveFromAudio = () => {
            if (Number.isFinite(audio.duration) && audio.duration > 0) {
                finish(audio.duration);
            }
        };

        audio.preload = 'metadata';
        audio.addEventListener('loadedmetadata', resolveFromAudio, { once: true });
        audio.addEventListener('durationchange', resolveFromAudio);
        audio.addEventListener('canplay', resolveFromAudio, { once: true });
        audio.addEventListener('error', () => finish(safeFallback), { once: true });
        audio.src = objectUrl;

        window.setTimeout(() => finish(safeFallback), 2500);
    });
}

export function buildStableWaveformBars(mediaItem = {}, barCount = 80) {
    const seedSource = [
        mediaItem?.id ?? 0,
        mediaItem?.size ?? 0,
        mediaItem?.extension ?? 'audio',
        durationObjectToSeconds(mediaItem?.metadata?.duration ?? null) || mediaItem?.metadata?.duration_seconds || 0,
    ].join(':');

    let seed = 0;

    for (let index = 0; index < seedSource.length; index++) {
        seed = ((seed * 31) + seedSource.charCodeAt(index)) >>> 0;
    }

    return Array.from({ length: barCount }, (_, index) => {
        seed = ((seed * 1664525) + 1013904223) >>> 0;

        const randomValue = seed / 4294967295;
        const centerDistance = Math.abs(index - ((barCount - 1) / 2)) / ((barCount - 1) / 2 || 1);
        const centerBoost = (1 - centerDistance) * 0.22;
        const rhythm = (Math.sin((index + 1) * 0.55) + 1) * 0.08;
        const height = 0.2 + (randomValue * 0.45) + centerBoost + rhythm;

        return Math.min(1, Math.max(0.16, height));
    });
}
