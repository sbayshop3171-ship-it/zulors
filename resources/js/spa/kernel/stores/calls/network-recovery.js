const degradedRecoveryStates = ['poor', 'reconnecting'];

const shouldWatchDegradedCallRecovery = ({
    status = 'idle',
    networkState = 'stable',
    hasLiveRemoteAudio = false
} = {}) => {
    return status === 'connected'
        && hasLiveRemoteAudio !== true
        && degradedRecoveryStates.includes(networkState);
};

const shouldForceReconnectHangup = ({
    isActive = false,
    networkState = 'stable',
    hasLiveRemoteAudio = false
} = {}) => {
    return isActive === true
        && networkState === 'reconnecting'
        && hasLiveRemoteAudio !== true;
};

export {
    shouldForceReconnectHangup,
    shouldWatchDegradedCallRecovery,
};
