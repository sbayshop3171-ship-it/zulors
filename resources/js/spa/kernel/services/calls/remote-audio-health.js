const defaultFreshnessWindowMs = 12000;
const defaultZeroProgressTolerance = 2;
const minimumAudibleLevel = 0.01;

const toNumber = (value, fallback = 0) => {
    const number = Number(value);

    return Number.isFinite(number) ? number : fallback;
};

const toPositiveNumber = (value, fallback = 0) => {
    const number = toNumber(value, fallback);

    return number > 0 ? number : fallback;
};

const toTimestampMs = (value, fallback = 0) => {
    if(typeof value === 'number' && Number.isFinite(value) && value > 0) {
        return value;
    }

    if(typeof value === 'string' && value.trim() !== '') {
        const parsed = Date.parse(value);

        return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
    }

    return fallback;
};

const createRemoteAudioHealthState = (overrides = {}) => {
    return {
        source: overrides.source || 'unknown',
        live: overrides.live === true,
        trackPresent: overrides.trackPresent === true,
        playbackActive: overrides.playbackActive === true,
        bytesReceived: toPositiveNumber(overrides.bytesReceived, 0),
        packetsReceived: toPositiveNumber(overrides.packetsReceived, 0),
        receivedBitrate: toPositiveNumber(overrides.receivedBitrate, 0),
        audioLevel: Math.max(0, Math.min(1, toNumber(overrides.audioLevel, 0))),
        zeroProgressWindows: Math.max(0, Math.floor(toNumber(overrides.zeroProgressWindows, 0))),
        lastProgressAtMs: toTimestampMs(overrides.lastProgressAtMs, 0),
        lastPlaybackAtMs: toTimestampMs(overrides.lastPlaybackAtMs, 0),
        lastDecodeAtMs: toTimestampMs(overrides.lastDecodeAtMs, 0),
        lastActiveAtMs: toTimestampMs(overrides.lastActiveAtMs, 0),
        reason: overrides.reason || 'idle',
    };
};

const evaluateWebRemoteAudioHealth = (previousState = {}, sample = {}, options = {}) => {
    const previous = createRemoteAudioHealthState({
        source: 'web',
        ...previousState,
    });
    const nowMs = toTimestampMs(options.nowMs, Date.now());
    const freshnessWindowMs = Math.max(1000, toPositiveNumber(options.freshnessWindowMs, defaultFreshnessWindowMs));
    const zeroProgressTolerance = Math.max(1, Math.floor(toPositiveNumber(options.zeroProgressTolerance, defaultZeroProgressTolerance)));
    const trackPresent = sample.trackPresent !== undefined
        ? sample.trackPresent === true
        : previous.trackPresent;

    if(sample.forceOffline === true || ! trackPresent) {
        return createRemoteAudioHealthState({
            ...previous,
            source: 'web',
            live: false,
            trackPresent: trackPresent,
            playbackActive: false,
            zeroProgressWindows: sample.forceOffline === true ? previous.zeroProgressWindows + 1 : previous.zeroProgressWindows,
            reason: sample.reason || (! trackPresent ? 'track_missing' : 'forced_offline'),
        });
    }

    const bytesReceived = toPositiveNumber(
        sample.bytesReceived !== undefined
            ? Math.max(0, toNumber(sample.bytesReceived, previous.bytesReceived))
            : previous.bytesReceived,
        previous.bytesReceived
    );
    const packetsReceived = toPositiveNumber(
        sample.packetsReceived !== undefined
            ? Math.max(0, toNumber(sample.packetsReceived, previous.packetsReceived))
            : previous.packetsReceived,
        previous.packetsReceived
    );
    const audioLevel = Math.max(0, Math.min(1, toNumber(
        sample.audioLevel !== undefined ? sample.audioLevel : previous.audioLevel,
        previous.audioLevel
    )));
    const playbackActive = sample.playbackActive === true || audioLevel >= minimumAudibleLevel;
    const progressed = bytesReceived > previous.bytesReceived || packetsReceived > previous.packetsReceived;
    const lastProgressAtMs = progressed ? nowMs : previous.lastProgressAtMs;
    const lastPlaybackAtMs = playbackActive ? nowMs : previous.lastPlaybackAtMs;
    const lastActiveAtMs = Math.max(previous.lastActiveAtMs, lastProgressAtMs, lastPlaybackAtMs);
    const zeroProgressWindows = progressed || playbackActive
        ? 0
        : previous.zeroProgressWindows + 1;
    const isFresh = lastActiveAtMs > 0 && (nowMs - lastActiveAtMs) <= freshnessWindowMs;
    const live = isFresh && zeroProgressWindows < zeroProgressTolerance;

    return createRemoteAudioHealthState({
        ...previous,
        source: 'web',
        live: live,
        trackPresent: true,
        playbackActive: playbackActive,
        bytesReceived: bytesReceived,
        packetsReceived: packetsReceived,
        audioLevel: audioLevel,
        zeroProgressWindows: zeroProgressWindows,
        lastProgressAtMs: lastProgressAtMs,
        lastPlaybackAtMs: lastPlaybackAtMs,
        lastActiveAtMs: lastActiveAtMs,
        reason: sample.reason || (live ? 'media_flow_live' : (zeroProgressWindows >= zeroProgressTolerance ? 'zero_progress' : 'stale_media_flow')),
    });
};

const evaluateNativeRemoteAudioHealth = (previousState = {}, sample = {}, options = {}) => {
    const previous = createRemoteAudioHealthState({
        source: 'native',
        ...previousState,
    });
    const nowMs = toTimestampMs(options.nowMs, Date.now());
    const freshnessWindowMs = Math.max(1000, toPositiveNumber(options.freshnessWindowMs, defaultFreshnessWindowMs));
    const zeroProgressTolerance = Math.max(1, Math.floor(toPositiveNumber(options.zeroProgressTolerance, defaultZeroProgressTolerance)));
    const trackPresent = sample.trackPresent !== undefined
        ? sample.trackPresent === true
        : (previous.trackPresent || sample.remoteAudioPlaying === true || sample.remoteAudioLive === true);

    if(sample.forceOffline === true || ! trackPresent) {
        return createRemoteAudioHealthState({
            ...previous,
            source: 'native',
            live: false,
            trackPresent: trackPresent,
            playbackActive: false,
            reason: sample.reason || (! trackPresent ? 'track_missing' : 'forced_offline'),
        });
    }

    const receivedBitrate = sample.receivedBitrate !== undefined
        ? Math.max(0, toNumber(sample.receivedBitrate, previous.receivedBitrate))
        : previous.receivedBitrate;
    const hintedLastActiveAtMs = Math.max(
        toTimestampMs(sample.lastActiveAtMs, 0),
        toTimestampMs(sample.lastRemoteAudioActiveAtMs, 0),
        toTimestampMs(sample.lastRemoteAudioActiveAt, 0)
    );
    const playbackActive = sample.playbackActive === true || sample.remoteAudioPlaying === true;
    const decodeActive = sample.decodeActive === true || sample.remoteAudioLive === true || playbackActive;
    const progressed = receivedBitrate > 0 || hintedLastActiveAtMs > previous.lastActiveAtMs;
    const lastProgressAtMs = progressed
        ? Math.max(nowMs, hintedLastActiveAtMs)
        : previous.lastProgressAtMs;
    const lastPlaybackAtMs = playbackActive
        ? Math.max(nowMs, hintedLastActiveAtMs)
        : previous.lastPlaybackAtMs;
    const lastDecodeAtMs = decodeActive
        ? Math.max(nowMs, hintedLastActiveAtMs)
        : previous.lastDecodeAtMs;
    const lastActiveAtMs = Math.max(previous.lastActiveAtMs, hintedLastActiveAtMs, lastProgressAtMs, lastPlaybackAtMs, lastDecodeAtMs);
    const zeroProgressWindows = progressed || playbackActive || decodeActive
        ? 0
        : previous.zeroProgressWindows + 1;
    const isFresh = lastActiveAtMs > 0 && (nowMs - lastActiveAtMs) <= freshnessWindowMs;
    const live = isFresh
        && zeroProgressWindows < zeroProgressTolerance
        && (sample.remoteAudioLive === true || decodeActive || playbackActive || receivedBitrate > 0);

    return createRemoteAudioHealthState({
        ...previous,
        source: 'native',
        live: live,
        trackPresent: trackPresent,
        playbackActive: playbackActive,
        receivedBitrate: receivedBitrate,
        zeroProgressWindows: zeroProgressWindows,
        lastProgressAtMs: lastProgressAtMs,
        lastPlaybackAtMs: lastPlaybackAtMs,
        lastDecodeAtMs: lastDecodeAtMs,
        lastActiveAtMs: lastActiveAtMs,
        reason: sample.reason || (live ? 'native_media_flow_live' : (zeroProgressWindows >= zeroProgressTolerance ? 'zero_progress' : 'stale_media_flow')),
    });
};

export {
    createRemoteAudioHealthState,
    evaluateNativeRemoteAudioHealth,
    evaluateWebRemoteAudioHealth,
};
