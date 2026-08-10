import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
import {
    CONNECTION_STATUS_EVENT,
    CONNECTION_STATES,
    createConnectionSnapshot,
    normalizeConnectionState
} from './brd/connection.js';

window.ColibriBRConnected = null;
window.ColibriBRState = createConnectionSnapshot();
window.Pusher = Pusher;
window.Echo = Echo;
Pusher.logToConsole = import.meta.env.PUSHER_DEBUG_CONSOLE;
const REVERB_CONNECTION_STATUS = import.meta.env.VITE_REVERB_CONNECTION_STATUS;

const setWSConnectionStatus = (nextState = {}) => {
    const previousState = window.ColibriBRState ?? createConnectionSnapshot();
    const current = normalizeConnectionState(nextState.current ?? nextState.state ?? previousState.current);
    const connectionSnapshot = {
        ...previousState,
        ...nextState,
        connected: (nextState.connected ?? current === CONNECTION_STATES.CONNECTED),
        current: current,
        previous: nextState.previous ?? previousState.current ?? null,
        updated_at: nextState.updated_at ?? Date.now(),
        reconnects: nextState.reconnects ?? previousState.reconnects ?? 0
    };

    window.ColibriBRState = connectionSnapshot;
    window.ColibriBRConnected = connectionSnapshot.connected;

    window.dispatchEvent(new CustomEvent(CONNECTION_STATUS_EVENT, {
        detail: connectionSnapshot
    }));
};

try {
    if (REVERB_CONNECTION_STATUS == 'on') {
        window.ColibriBRD = new Echo({
            namespace: 'null',
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            cluster: false
        });

        setWSConnectionStatus({
            connected: false,
            current: CONNECTION_STATES.CONNECTING,
            previous: CONNECTION_STATES.INITIALIZING
        });

        window.ColibriBRD.connector.pusher.connection.bind('connected', function() {
            console.log('📶 Websockets connection is established.');

            setWSConnectionStatus({
                connected: true,
                current: CONNECTION_STATES.CONNECTED
            });
        });

        window.ColibriBRD.connector.pusher.connection.bind('state_change', function(state) {
            const currentState = normalizeConnectionState(state.current);
            const isConnected = currentState === CONNECTION_STATES.CONNECTED;
            const reconnects = (
                previousState => {
                    if(previousState.current !== CONNECTION_STATES.CONNECTED && currentState === CONNECTION_STATES.CONNECTING) {
                        return previousState.reconnects + 1;
                    }

                    return previousState.reconnects;
                }
            )(window.ColibriBRState ?? createConnectionSnapshot());

            setWSConnectionStatus({
                connected: isConnected,
                current: currentState,
                previous: normalizeConnectionState(state.previous),
                reconnects: reconnects
            });
        });
    }

    else {
        setWSConnectionStatus({
            connected: false,
            current: CONNECTION_STATES.DISABLED,
            previous: CONNECTION_STATES.INITIALIZING
        });
        console.info("📶 Websockets connection is disabled. Please configure your broadcaster server and enable Reverb connection in your app settings. (Zulors)");
    }
}

catch (error) {
    setWSConnectionStatus({
        connected: false,
        current: CONNECTION_STATES.FAILED,
        previous: window.ColibriBRState?.current ?? CONNECTION_STATES.INITIALIZING
    });
    console.log(error);
}
