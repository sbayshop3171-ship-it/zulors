const CONNECTION_STATUS_EVENT = 'colibri:ws-status';

const CONNECTION_STATES = Object.freeze({
    INITIALIZING: 'initializing',
    CONNECTING: 'connecting',
    CONNECTED: 'connected',
    DISCONNECTED: 'disconnected',
    UNAVAILABLE: 'unavailable',
    FAILED: 'failed',
    DISABLED: 'disabled',
});

const createConnectionSnapshot = (overrides = {}) => {
    return {
        connected: false,
        current: CONNECTION_STATES.INITIALIZING,
        previous: null,
        reconnects: 0,
        updated_at: Date.now(),
        transport: null,
        ...overrides
    };
};

const normalizeConnectionState = (state) => {
    switch (state) {
        case CONNECTION_STATES.CONNECTED:
        case 'connected':
            return CONNECTION_STATES.CONNECTED;
        case CONNECTION_STATES.CONNECTING:
        case 'connecting':
            return CONNECTION_STATES.CONNECTING;
        case CONNECTION_STATES.DISCONNECTED:
        case 'disconnected':
            return CONNECTION_STATES.DISCONNECTED;
        case CONNECTION_STATES.UNAVAILABLE:
        case 'unavailable':
            return CONNECTION_STATES.UNAVAILABLE;
        case CONNECTION_STATES.FAILED:
        case 'failed':
            return CONNECTION_STATES.FAILED;
        case CONNECTION_STATES.DISABLED:
        case 'disabled':
            return CONNECTION_STATES.DISABLED;
        default:
            return CONNECTION_STATES.INITIALIZING;
    }
};

const isConnectionIssueState = (state) => {
    const normalizedState = normalizeConnectionState(state);

    return [
        CONNECTION_STATES.DISCONNECTED,
        CONNECTION_STATES.UNAVAILABLE,
        CONNECTION_STATES.FAILED,
        CONNECTION_STATES.DISABLED
    ].includes(normalizedState);
};

export {
    CONNECTION_STATUS_EVENT,
    CONNECTION_STATES,
    createConnectionSnapshot,
    normalizeConnectionState,
    isConnectionIssueState,
};
