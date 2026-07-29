import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.ColibriBRConnected = null;
window.Pusher = Pusher;
window.Echo = Echo;
Pusher.logToConsole = import.meta.env.PUSHER_DEBUG_CONSOLE;
const REVERB_CONNECTION_STATUS = import.meta.env.VITE_REVERB_CONNECTION_STATUS;

const setWSConnectionStatus = (isConnected) => {
    window.ColibriBRConnected = isConnected;

    window.dispatchEvent(new CustomEvent('colibri:ws-status', {
        detail: {
            connected: isConnected
        }
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

        setWSConnectionStatus(true);

        window.ColibriBRD.connector.pusher.connection.bind('connected', function() {
            console.log('📶 Websockets connection is established.');

            setWSConnectionStatus(true);
        });

        window.ColibriBRD.connector.pusher.connection.bind('state_change', function(state) {
            if(['failed', 'unavailable'].includes(state.current)) {
                setWSConnectionStatus(false);
            }
            else if(['connecting', 'connected'].includes(state.current)) {
                setWSConnectionStatus(true);
            }
        });
    }

    else {
        setWSConnectionStatus(false);
        console.info("📶 Websockets connection is disabled. Please configure your broadcaster server and enable Reverb connection in your app settings. (Zulors)");
    }
}

catch (error) {
    setWSConnectionStatus(false);
    console.log(error);
}
