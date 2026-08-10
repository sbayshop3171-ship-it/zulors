import test from 'node:test';
import assert from 'node:assert/strict';

import BRD from '../../resources/js/spa/kernel/websockets/brd/index.js';
import {
    createEventDeduper,
    buildAckPayload,
    buildUploadProgressPayload
} from '../../resources/js/spa/kernel/websockets/brd/reliability.js';
import {
    buildTypingPayload,
    createIncomingTypingController,
    createOutgoingTypingController
} from '../../resources/js/spa/kernel/websockets/brd/typing.js';
import {
    CONNECTION_STATES,
    createConnectionSnapshot
} from '../../resources/js/spa/kernel/websockets/brd/connection.js';

test('BRD resolves events across casing and raw event names', () => {
    assert.equal(BRD.getEvent('CHAT_MESSAGE_RECEIVED'), '.chat.message.received');
    assert.equal(BRD.getEvent('chat-message-received'), '.chat.message.received');
    assert.equal(BRD.getEvent('.chat.message.received'), '.chat.message.received');
    assert.equal(BRD.getEventMeta('CHAT_MESSAGE_TYPING').whisper, true);
});

test('BRD formats channels and exposes grouped metadata', () => {
    assert.equal(BRD.getChannel('AUTH_USER', [42]), 'App.Models.User.42');
    assert.equal(BRD.getChannel('timeline', []), 'timeline.public');
    assert.equal(BRD.getChannelMeta('chat').scope, 'private');
    assert.ok(BRD.listEventKeys('chat').includes('CHAT_MESSAGE_READ'));
    assert.ok(BRD.listChannelKeys('presence').includes('CHAT_PRESENCE'));
});

test('reliability helpers create bounded payloads and dedupe repeated events', () => {
    const deduper = createEventDeduper({ ttlMs: 1000 });
    const ackPayload = buildAckPayload({ entity_id: 15, status: 'read' });
    const uploadPayload = buildUploadProgressPayload({ upload_id: 'up_1', progress: 120 });

    assert.equal(ackPayload.data.entity_id, 15);
    assert.equal(uploadPayload.data.progress, 100);
    assert.equal(deduper.seen(ackPayload), false);
    assert.equal(deduper.seen(ackPayload), true);
});

test('incoming typing controller auto-expires transient typing state', async () => {
    const events = [];
    const controller = createIncomingTypingController((state) => {
        events.push(state);
    }, { ttlMs: 25 });

    controller.receive(buildTypingPayload({
        user: { name: 'Rasel' },
        is_typing: true,
        ttl_ms: 25
    }));

    assert.equal(events.at(-1).is_typing, true);

    await new Promise((resolve) => {
        setTimeout(resolve, 50);
    });

    assert.equal(events.at(-1).is_typing, false);
});

test('outgoing typing controller emits start once and stop after idle', async () => {
    const payloads = [];
    const controller = createOutgoingTypingController((payload) => {
        payloads.push(payload);
    }, { idleMs: 20, ttlMs: 40 });

    controller.bump({ name: 'Admin User' });
    controller.bump({ name: 'Admin User' });

    await new Promise((resolve) => {
        setTimeout(resolve, 50);
    });

    assert.equal(payloads[0].data.is_typing, true);
    assert.equal(payloads.at(-1).data.is_typing, false);
    assert.equal(payloads.filter((payload) => payload.data.is_typing).length, 1);
});

test('connection snapshot provides a stable default state', () => {
    const snapshot = createConnectionSnapshot();

    assert.equal(snapshot.connected, false);
    assert.equal(snapshot.current, CONNECTION_STATES.INITIALIZING);
    assert.deepEqual(BRD.getConnectionSnapshot().current, CONNECTION_STATES.INITIALIZING);
});
