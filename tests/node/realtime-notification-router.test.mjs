import test from 'node:test';
import assert from 'node:assert/strict';

import { routeRealtimeNotification } from '../../resources/js/spa/kernel/services/realtime/notification-router.js';

test('routeRealtimeNotification handles incoming call notifications and chat notifications', () => {
    const callStore = {
        handleNotification: (payload) => {
            callStore.received = payload;
        },
        received: null,
    };

    const inboxStore = {
        handleIncomingMessageNotification: (payload, userId, activeChatId) => {
            inboxStore.lastArgs = { payload, userId, activeChatId };
            return true;
        },
        lastArgs: null,
    };

    const toastStore = {
        add: (message, duration) => {
            toastStore.lastToast = { message, duration };
        },
        lastToast: null,
    };

    const sounds = {
        isNotificationsSoundEnabled: () => true,
        backgroundChatMessageReceived: () => {
            sounds.played = true;
        },
        played: false,
    };

    routeRealtimeNotification({
        type: 'call.notification',
        data: { call: { call_uuid: 'abc' } },
    }, {
        callStore,
        inboxStore,
        toastStore,
        sounds,
        authUserId: 5,
        activeChatId: null,
    });

    assert.deepEqual(callStore.received, { call: { call_uuid: 'abc' } });

    routeRealtimeNotification({
        type: 'chat.notification',
        data: { relations: { user: { name: 'Alice' } }, content: 'Hi' },
    }, {
        callStore,
        inboxStore,
        toastStore,
        sounds,
        authUserId: 5,
        activeChatId: null,
    });

    assert.deepEqual(inboxStore.lastArgs.payload, { relations: { user: { name: 'Alice' } }, content: 'Hi' });
    assert.equal(sounds.played, true);
    assert.equal(toastStore.lastToast.message, 'Alice: Hi');
});
