const degradedRecoveryStates = ['poor', 'reconnecting'];

const shouldWatchDegradedCallRecovery = ({
    status = 'idle',
    networkState = 'stable',
    hasLiveRemoteAudio = false,
    remoteMuted = false,
    audioPlaybackBlocked = false
} = {}) => {
    return status === 'connected'
        && hasLiveRemoteAudio !== true
        && remoteMuted !== true
        && audioPlaybackBlocked !== true
        && degradedRecoveryStates.includes(networkState);
};

const shouldForceReconnectHangup = ({
    isActive = false,
    networkState = 'stable',
    hasLiveRemoteAudio = false,
    remoteMuted = false,
    audioPlaybackBlocked = false
} = {}) => {
    return isActive === true
        && networkState === 'reconnecting'
        && hasLiveRemoteAudio !== true
        && remoteMuted !== true
        && audioPlaybackBlocked !== true;
};

export {
    shouldForceReconnectHangup,
    shouldWatchDegradedCallRecovery,
};
