const degradedRecoveryStates = ['poor', 'reconnecting'];

const shouldWatchDegradedCallRecovery = ({
    status = 'idle',
    networkState = 'stable',
    hasLiveRemoteAudio = false,
    hasRemoteAudioPath = false,
    remoteMuted = false,
    audioPlaybackBlocked = false
} = {}) => {
    return status === 'connected'
        && hasLiveRemoteAudio !== true
        && hasRemoteAudioPath !== true
        && remoteMuted !== true
        && audioPlaybackBlocked !== true
        && degradedRecoveryStates.includes(networkState);
};

const shouldForceReconnectHangup = ({
    isActive = false,
    networkState = 'stable',
    hasLiveRemoteAudio = false,
    hasRemoteAudioPath = false,
    remoteMuted = false,
    audioPlaybackBlocked = false
} = {}) => {
    return isActive === true
        && networkState === 'reconnecting'
        && hasLiveRemoteAudio !== true
        && hasRemoteAudioPath !== true
        && remoteMuted !== true
        && audioPlaybackBlocked !== true;
};

export {
    shouldForceReconnectHangup,
    shouldWatchDegradedCallRecovery,
};
