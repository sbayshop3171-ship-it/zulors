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

const runtimeReverbConfig = () => {
    const runtime = window.__zulorsRealtime?.reverb || window.BackendEmbeds?.config?.reverb || {};
    const browserHost = window.location.hostname;
    const browserScheme = window.location.protocol === 'https:' ? 'https' : 'http';
    const isLoopbackHost = (host) => ['127.0.0.1', 'localhost', '0.0.0.0', '::1'].includes(String(host || '').trim().toLowerCase());
    const normalizeScheme = (scheme) => ['http', 'https'].includes(String(scheme || '').trim())
        ? String(scheme).trim()
        : browserScheme;
    const scheme = normalizeScheme(runtime.scheme || import.meta.env.VITE_REVERB_SCHEME || browserScheme);
    let host = runtime.host || import.meta.env.VITE_REVERB_HOST || browserHost;
    let port = Number(runtime.port || import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80));

    if(isLoopbackHost(host) && ! isLoopbackHost(browserHost)) {
        host = browserHost;
        port = scheme === 'https' ? 443 : 80;
    }

    const enabled = typeof runtime.enabled === 'boolean'
        ? runtime.enabled
        : import.meta.env.VITE_REVERB_CONNECTION_STATUS === 'on';

    return {
        enabled: enabled,
        key: runtime.app_key || runtime.key || import.meta.env.VITE_REVERB_APP_KEY,
        host: host,
        port: Number.isFinite(port) && port > 0 ? port : (scheme === 'https' ? 443 : 80),
        scheme: scheme
    };
};

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
    const reverbConfig = runtimeReverbConfig();

    if (reverbConfig.enabled && reverbConfig.key && reverbConfig.host) {
        window.ColibriBRD = new Echo({
            namespace: 'null',
            broadcaster: 'reverb',
            key: reverbConfig.key,
            wsHost: reverbConfig.host,
            wsPort: reverbConfig.port,
            wssPort: reverbConfig.port,
            forceTLS: reverbConfig.scheme === 'https',
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
