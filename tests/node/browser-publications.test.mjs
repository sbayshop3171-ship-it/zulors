import test from 'node:test';
import assert from 'node:assert/strict';
import { PublicationManager } from '../../resources/js/spa/kernel/services/media/publications/manager.js';
import { NativePublicationBridge } from '../../resources/js/spa/kernel/services/media/publications/native-bridge.js';
import { directAssetImageUpload } from '../../resources/js/spa/kernel/services/media/r2-direct-asset-upload.js';
import { prepareMediaFile, prepareVideoFile } from '../../resources/js/spa/kernel/services/media/prepare-media-file.js';
import { transferItem, uploadPart } from '../../resources/js/spa/kernel/services/media/publications/transfer.js';
import { isPublicationMedia, publicationMediaMime, publicationMediaType, releasePublicationSelection, selectPublicationMedia } from '../../resources/js/spa/kernel/services/media/publications/selection.js';

class MemoryDatabase {
    rows = new Map(); files = new Map();
    async enqueue(row, files) { this.rows.set(row.key, structuredClone(row)); files.forEach(file => this.files.set(file.key, file.blob)); }
    async list(account) { return structuredClone([...this.rows.values()].filter(row => row.account === account)); }
    async get(key) { return structuredClone(this.rows.get(key)); }
    async file(key) { return this.files.get(key); }
    async update(key, fn) { const row = fn(await this.get(key)); if (row) this.rows.set(key, structuredClone(row)); return row; }
    async updateFileAndPublication(key, fileKey, blob, fn) { this.files.set(fileKey, blob); return this.update(key, fn); }
    async lease() { return true; }
    async release() {}
}
const bridge = () => ({ capabilities: async () => null, listeners: new Set(), connect: () => false });
const capability = { enabled: true, user_id: 1, kinds: ['post', 'story', 'chat'], privacy_options: ['all'], upload_concurrency: 4 };
const selection = () => ({ client_uid: crypto.randomUUID(), type: 'image', name: 'photo.png', mime: 'image/png', size: 4, file: new Blob(['test'], { type: 'image/png' }) });
function makeManager(request, options = {}) {
    const manager = new PublicationManager({ request, db: new MemoryDatabase(), bridge: bridge(), host: null, locks: null, ...options });
    manager.account = '1'; manager.capability = capability; manager.wake = () => {};
    return manager;
}
async function queued(manager) { return manager.enqueue({ kind: 'post', content: 'caption' }, [selection()]); }
function publication(row, status = 'uploading', itemStatus = 'pending') {
    return { id: 'publication-1', client_uid: row.client_uid, kind: 'post', status,
        items: row.descriptor.items.map(item => ({ ...item, id: item.client_uid, status: itemStatus })) };
}

test('enqueue waits for durable storage and never submits HTTP before it commits', async () => {
    let commit;
    const db = new MemoryDatabase();
    const save = db.enqueue.bind(db);
    db.enqueue = (...args) => new Promise(resolve => { commit = async () => { await save(...args); resolve(); }; });
    const manager = makeManager(() => { throw new Error('Selection must not upload'); }, { db });
    let resolved = false;
    const task = queued(manager).then(() => { resolved = true; });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(resolved, false); assert.equal(manager.rows.length, 0);
    await commit(); await task;
    assert.equal(manager.rows[0].status, 'queued');
    const file = await db.file(`${manager.rows[0].key}:${manager.rows[0].descriptor.items[0].client_uid}`);
    assert.equal(await file.text(), 'test');
});

test('quota failure rejects enqueue with no queued success', async () => {
    const manager = makeManager(async () => {});
    manager.db.enqueue = async () => { throw new DOMException('Full', 'QuotaExceededError'); };
    await assert.rejects(queued(manager), /still in the editor/);
    assert.equal(manager.rows.length, 0);
});

test('feature disabled, unsupported privacy and GIF cannot enter the new path', async () => {
    const manager = makeManager(async () => {});
    manager.capability = { ...capability, enabled: false };
    await assert.rejects(queued(manager), /unavailable/);
    manager.capability = capability;
    await assert.rejects(manager.enqueue({ kind: 'post', privacy: 'followers' }, [selection()]), /audience/);
    for (const type of ['image/gif', 'image/heic']) assert.equal(isPublicationMedia({ type }), false);
    assert.equal(isPublicationMedia({ type: 'application/pdf', name: 'report.pdf' }), true);
    assert.equal(isPublicationMedia({ type: 'audio/mp3', name: 'voice.mp3' }), true);
    assert.equal(isPublicationMedia({ type: 'image/png' }), true);
});

test('camera videos with generic or codec MIME use their known container type', () => {
    const cameraMp4 = { name: 'camera.mp4', type: 'video/hevc' };
    const cameraMov = { name: 'camera.MOV', type: 'application/octet-stream' };

    assert.equal(isPublicationMedia(cameraMp4), true);
    assert.equal(publicationMediaType(cameraMp4), 'video');
    assert.equal(publicationMediaMime(cameraMp4), 'video/mp4');
    assert.equal(isPublicationMedia(cameraMov), true);
    assert.equal(publicationMediaMime(cameraMov), 'video/quicktime');
});

test('video preparation returns the untouched original immediately without invoking a device encoder', async () => {
    const previousWorker = globalThis.Worker;
    let workerCalls = 0;
    globalThis.Worker = class { constructor() { workerCalls++; throw new Error('Video worker must not start'); } };
    const original = new File(['video-bytes'], 'one-hour.mp4', { type: 'video/mp4' });
    try {
        const prepared = await prepareVideoFile(original);
        const generic = await prepareMediaFile(original);
        assert.equal(prepared.file, original);
        assert.equal(generic.file, original);
        assert.equal(prepared.prepared, false);
        assert.equal(prepared.reason, 'video_uploads_use_original_r2');
        assert.equal(workerCalls, 0);
    }
    finally {
        if (previousWorker === undefined) delete globalThis.Worker;
        else globalThis.Worker = previousWorker;
    }
});

test('video publication can queue when the device cannot provide duration metadata', async () => {
    const manager = makeManager(() => { throw new Error('Upload should wait for the worker, not run during enqueue'); });
    const video = {
        client_uid: crypto.randomUUID(), type: 'video', name: 'camera.mp4', mime: 'video/mp4', size: 4,
        duration_seconds: null, file: new Blob(['test'], { type: 'video/mp4' }),
    };

    const row = await manager.enqueue({ kind: 'post', content: 'caption' }, [video]);

    assert.equal(row.status, 'queued');
    assert.equal(Object.hasOwn(row.descriptor.items[0], 'duration_seconds'), false);
});

test('video selection is queued without waiting for client-side metadata probing', async () => {
    const previousDocument = globalThis.document;
    let metadataProbeCalls = 0;
    globalThis.document = { createElement() { metadataProbeCalls++; throw new Error('metadata probe should happen after queue acknowledgement'); } };
    try {
        const selected = await selectPublicationMedia(new File(['video'], 'camera.mp4', { type: 'video/mp4' }));
        assert.equal(selected.type, 'video');
        assert.equal(selected.duration_seconds, null);
        assert.equal(selected.size, 5);
        assert.equal(metadataProbeCalls, 0);
        releasePublicationSelection(selected);
    }
    finally {
        if (previousDocument === undefined) delete globalThis.document;
        else globalThis.document = previousDocument;
    }
});

test('multipart resumes confirmed parts, starts at two, ramps to four and respects byte ranges', async () => {
    let simultaneous = 0; let maximum = 0; const sent = [];
    const parts = Array.from({ length: 8 }, (_, i) => ({ part_number: i + 1, start: i * 2, end: i * 2 + 2 }));
    const result = await transferItem({ file: new Blob(['abcdefghijklmnop']), upload: { upload_type: 'multipart', parts },
        completed_parts: [{ part_number: 1, etag: 'saved' }], concurrency: 4,
        put: async (part, body) => { simultaneous++; maximum = Math.max(maximum, simultaneous); sent.push([part.part_number, await body.text()]); await new Promise(resolve => setImmediate(resolve)); simultaneous--; return `etag-${part.part_number}`; },
    });
    assert.equal(maximum, 4); assert.equal(sent.length, 7);
    assert.deepEqual(sent.find(([part]) => part === 2), [2, 'cd']);
    assert.deepEqual(result[0], { part_number: 1, etag: 'saved' }); assert.equal(result.length, 8);
});

test('browser outbox durably saves completed part ETags and uses them for the active generation', async () => {
    let row;
    const manager = makeManager(async (method, path) => {
        if (method === 'POST' && path === '') {
            return publication(row, 'uploading', 'pending');
        }
        if (method === 'GET') return publication(row, 'uploading', 'pending');
        if (path.endsWith('/resume')) return {
            status: 'uploading', generation: 5,
            upload: { upload_type: 'multipart', upload_concurrency: 4, parts: [{ part_number: 1, start: 0, end: 4 }] },
            completed_parts: [],
        };
        if (path.endsWith('/complete')) return publication(row, 'published', 'uploaded');
        throw new Error(`Unexpected request ${method} ${path}`);
    }, {
        transfer: async ({ onPartComplete, onConcurrencyChange }) => {
            await onPartComplete({ part_number: 1, etag: 'etag-generation-five' });
            await onConcurrencyChange(3);
            const saved = await manager.db.get(row.key);
            assert.deepEqual(saved.completed_parts[`${row.descriptor.items[0].client_uid}:5`], [{ part_number: 1, etag: 'etag-generation-five' }]);
            return [{ part_number: 1, etag: 'etag-generation-five' }];
        },
    });
    row = await queued(manager);
    row.descriptor.items[0].type = 'video';
    row.descriptor.items[0].mime = 'video/mp4';
    row.descriptor.items[0].name = 'camera.mp4';
    row.descriptor.items[0].size = 4;
    await manager.db.update(row.key, current => ({ ...current, descriptor: row.descriptor }));

    await manager.process(row, manager.epoch);

    const saved = await manager.db.get(row.key);
    assert.equal(saved.status, 'published');
    assert.deepEqual(saved.completed_parts, {});
    assert.equal(saved.upload_concurrency, 2);
});

test('network failure halves concurrency and exposes the next retry setting', async () => {
    const parts = Array.from({ length: 6 }, (_, i) => ({ part_number: i + 1, start: i, end: i + 1 }));
    const changes = [];
    let calls = 0;
    await assert.rejects(transferItem({ file: new Blob(['abcdef']), upload: { upload_type: 'multipart', parts }, concurrency: 4,
        onConcurrencyChange: value => changes.push(value),
        put: async () => {
            if (++calls === 1) throw Object.assign(new Error('offline'), { status: 0 });
            await new Promise(resolve => setImmediate(resolve));
            return 'etag';
        },
    }), error => error.nextConcurrency === 1);
    assert.ok(changes.includes(1));
    assert.equal(calls, 2);
});

test('R2 requests carry no cookies or API authentication headers', async () => {
    const headers = {}; const xhr = { upload: {}, open() {}, setRequestHeader: (k, v) => { headers[k] = v; },
        getResponseHeader: () => 'etag', send() { this.status = 200; this.onload(); } };
    await uploadPart({ upload_url: 'https://bucket.example/media', upload_headers: {
        Authorization: 'secret', Cookie: 'secret', 'X-XSRF-TOKEN': 'secret', 'X-CSRF-TOKEN': 'secret', 'Content-Type': 'image/png',
    } }, new Blob(['x']), { xhrFactory: () => xhr });
    assert.equal(xhr.withCredentials, false); assert.deepEqual(headers, { 'Content-Type': 'image/png' });
});

test('raw upload sends the full file and completes with no multipart entries', async () => {
    const result = await transferItem({ upload: { upload_type: 'raw', upload_url: 'https://bucket.example/raw' }, file: new Blob(['data']),
        put: async (part, body) => { assert.equal(await body.text(), 'data'); assert.equal(part.end, 4); return null; } });
    assert.deepEqual(result, []);
});

test('profile image upload retries create with the same identity and completes signed R2 multipart parts', async () => {
    const previousXhr = globalThis.XMLHttpRequest;
    let sent = 0;
    globalThis.XMLHttpRequest = class {
        upload = {};
        open(method, url) { this.method = method; this.url = url; }
        setRequestHeader() {}
        getResponseHeader(name) { return name.toLowerCase() === 'etag' ? `etag-${sent}` : null; }
        send(body) {
            sent++;
            this.status = 200;
            queueMicrotask(() => {
                this.upload.onprogress?.({ lengthComputable: true, loaded: body.size, total: body.size });
                this.onload();
            });
        }
    };
    const clientUids = [];
    let createAttempts = 0;
    let completion;
    try {
        const result = await directAssetImageUpload({
            file: new File(['abcdef'], 'photo.jpg', { type: 'image/jpeg' }),
            operation: 'profile_avatar',
            request: async (action, payload) => {
                if (action === 'create') {
                    clientUids.push(payload.client_uid);
                    if (++createAttempts === 1) throw new Error('lost create acknowledgement');
                    return { data: { data: {
                        uid: 'tmp/direct/media/profile_avatar/object.jpg', upload_type: 'multipart', upload_concurrency: 2,
                        parts: [
                            { part_number: 1, start: 0, end: 3, upload_url: 'https://r2.example.test/part/1' },
                            { part_number: 2, start: 3, end: 6, upload_url: 'https://r2.example.test/part/2' },
                        ],
                    } } };
                }
                completion = payload;
                return { data: { data: { avatar_url: '/media/avatar.jpg' } } };
            },
        });
        assert.equal(clientUids.length, 2);
        assert.equal(clientUids[0], clientUids[1]);
        assert.equal(sent, 2);
        assert.deepEqual(completion.parts.map(part => part.part_number), [1, 2]);
        assert.deepEqual(result, { avatar_url: '/media/avatar.jpg', prepared: false, reason: 'device_preparation_unavailable' });
    }
    finally {
        if (previousXhr === undefined) delete globalThis.XMLHttpRequest;
        else globalThis.XMLHttpRequest = previousXhr;
    }
});

test('uploaded object with lost completion acknowledgement is completed without another PUT', async () => {
    const calls = [];
    const manager = makeManager(async (method, path, data) => {
        calls.push([method, path, data]);
        if (path.endsWith('/resume')) return { status: 'uploaded', generation: 3, upload: null, completed_parts: [] };
        return publication(row, path.endsWith('/complete') ? 'published' : 'uploading', 'uploaded');
    }, { transfer: async () => { throw new Error('Must not upload'); } });
    const row = await queued(manager);
    await manager.process(row, manager.epoch);
    assert.deepEqual(calls.find(call => call[1].endsWith('/complete'))[2], { generation: 3, parts: [] });
    assert.equal((await manager.db.get(row.key)).status, 'published');
});

test('processed items are never resumed and terminal publications stop polling', async () => {
    let requests = 0;
    const manager = makeManager(async (method, path) => {
        requests++;
        if (path === '/capabilities') return capability;
        assert.ok(!path.includes('/items/'));
        return publication(row, 'published', 'processed');
    });
    const row = await queued(manager);
    await manager.tick();
    const count = requests;
    assert.equal(manager.rows[0].status, 'published');
    assert.equal(manager.timer, undefined);
    assert.ok(count > 0);
});

test('429 respects Retry-After; 401 pauses; 410 schedules retry', async () => {
    for (const [status, expected] of [[429, 'waiting'], [401, 'auth_required'], [410, 'waiting']]) {
        const manager = makeManager(async () => { throw { status, message: 'Try later', retryAfter: '60' }; });
        const row = await queued(manager);
        await manager.process(row, manager.epoch);
        const saved = await manager.db.get(row.key);
        assert.equal(saved.status, expected);
        if (status === 429) assert.ok(saved.retry_at >= Date.now() + 59000);
        if (status === 410) assert.equal(saved.retry_requested, true);
    }
});

test('account change aborts transfer and cannot complete as another user', async () => {
    let started;
    const wait = new Promise(resolve => { started = resolve; });
    let completed = false;
    const manager = makeManager(async (method, path) => {
        if (path.endsWith('/resume')) return { generation: 1, upload: { upload_type: 'raw' } };
        if (path.endsWith('/complete')) completed = true;
        return publication(row);
    }, { transfer: ({ signal }) => new Promise((resolve, reject) => {
        started(); signal.addEventListener('abort', () => reject(new DOMException('Paused', 'AbortError')));
    }) });
    const row = await queued(manager);
    const pending = manager.process(row, manager.epoch);
    await wait; await manager.setAccount(null); await pending;
    assert.equal(completed, false); assert.equal(manager.rows.length, 0);
    assert.notEqual((await manager.db.get(row.key)).status, 'published');
});

test('cancelling an unacknowledged create recovers its idempotent identity then deletes it', async () => {
    const calls = [];
    const manager = makeManager(async (method, path, data) => {
        calls.push([method, path, data]);
        return publication(row, method === 'DELETE' ? 'cancelled' : 'uploading');
    });
    const row = await queued(manager);
    await manager.db.update(row.key, current => ({ ...current, create_attempted: true }));
    await manager.cancel(row);
    await manager.process(await manager.db.get(row.key), manager.epoch);
    assert.equal(calls[0][0], 'POST'); assert.equal(calls[0][2].client_uid, row.client_uid);
    assert.equal(calls[1][0], 'DELETE'); assert.equal((await manager.db.get(row.key)).status, 'cancelled');
});

test('native bridge gracefully rejects older APKs and sends only opaque file handles', async () => {
    const absent = new NativePublicationBridge({});
    assert.equal(await absent.capabilities(), null);
    const messages = [];
    const host = { ZulorsUploadBridge: { postMessage(raw) {
        const request = JSON.parse(raw); messages.push(request);
        queueMicrotask(() => this.onmessage({ data: JSON.stringify({ id: request.id, result: { enabled: true } }) }));
    } } };
    const native = new NativePublicationBridge(host);
    assert.equal((await native.capabilities()).enabled, true);
    await native.request('enqueue', { items: [{ native_file_id: 'opaque-id' }] });
    assert.deepEqual(messages[1].payload.items, [{ native_file_id: 'opaque-id' }]);
    let events = 0; native.listeners.add(() => events++);
    native.receive(JSON.stringify({ event: 'uploadsChanged', items: [] })); assert.equal(events, 1);
});

test('flag rollback continues admitted uploads but never admits waiting local entries', async () => {
    const calls = [];
    const manager = makeManager(async (method, path) => {
        calls.push([method, path]);
        if (path === '/capabilities') return { ...capability, enabled: false };
        if (path === '') return [publication(admitted, 'processing', 'processing')];
        return publication(admitted, 'published', 'processed');
    });
    const admitted = await queued(manager);
    const local = await queued(manager);
    await manager.savePublication(admitted, publication(admitted));
    await manager.tick();
    assert.equal((await manager.db.get(admitted.key)).status, 'published');
    assert.equal((await manager.db.get(local.key)).status, 'feature_disabled');
    assert.equal(calls.some(([method]) => method === 'POST'), false);
    assert.equal(manager.timer, undefined);
    await manager.reconcile();
    assert.ok(calls.some(([method, path]) => method === 'GET' && path === ''));
});

test('an account change cannot publish a selection made by the previous user', async () => {
    const manager = makeManager(async () => {});
    await assert.rejects(manager.enqueue({ kind: 'post' }, [{ ...selection(), account: 'another-user' }]), /Account changed/);
    assert.equal(manager.rows.length, 0);
});

test('server-only records reconcile without taking over another device upload', async () => {
    const calls = [];
    const manager = makeManager(async (method, path) => { calls.push([method, path]); return publication(row); });
    const row = await queued(manager);
    const admitted = await manager.savePublication(row, publication(row));
    await manager.db.update(row.key, current => ({ ...current, remote_only: true }));
    await manager.process({ ...admitted, remote_only: true }, manager.epoch);
    assert.deepEqual(calls, [['GET', '/publication-1']]);
});

test('stale capability responses cannot restore a previous account', async () => {
    let answer;
    const manager = makeManager(() => new Promise(resolve => { answer = resolve; }));
    const pending = manager.capabilities(true);
    await manager.setAccount(null);
    answer(capability);
    assert.equal(await pending, null);
    assert.equal(manager.account, null); assert.equal(manager.capability, null);
});

test('lease loss prevents any API request', async () => {
    let requests = 0;
    const manager = makeManager(async () => { requests++; });
    const row = await queued(manager);
    manager.db.lease = async () => false;
    await manager.process(row, manager.epoch);
    assert.equal(requests, 0);
});

test('focus reconciliation verifies the cookie account before reading publications', async () => {
    const calls = [];
    const manager = makeManager(async (method, path) => { calls.push(path); return { ...capability, user_id: 2 }; });
    await manager.reconcile();
    assert.deepEqual(calls, ['/capabilities']);
    assert.equal(manager.capability, null);
});

test('stale server reads cannot resurrect a cancelled or published local entry', async () => {
    const manager = makeManager(async () => {});
    const row = await queued(manager);
    for (const status of ['cancelled', 'published']) {
        const merged = manager.merge({ ...row, status }, publication(row, 'uploading'));
        assert.equal(merged.status, status);
    }
});

test('a processing publication resumes its remaining attachments after interruption', async () => {
    const calls = [];
    const transferred = [];
    const manager = makeManager(async (method, path, data) => {
        calls.push([method, path, data]);
        if (path.endsWith('/resume')) return { status: 'uploading', generation: 4, upload: { upload_type: 'raw' } };
        return { ...server, items: server.items.map((item, index) => path.endsWith('/complete') && index === 1 ? { ...item, status: 'processing' } : item) };
    }, { transfer: async ({ file }) => { transferred.push(await file.text()); return []; } });
    const row = await manager.enqueue({ kind: 'post' }, [selection(), selection()]);
    const server = publication(row, 'processing', 'pending');
    server.items[0].status = 'processed';
    await manager.savePublication(row, server);
    await manager.process(await manager.db.get(row.key), manager.epoch);
    assert.equal(transferred.length, 1);
    assert.equal(calls.filter(call => call[1].endsWith('/resume')).length, 1);
    assert.ok(calls.find(call => call[1].endsWith('/resume'))[1].includes(server.items[1].id));
    assert.equal(calls.filter(call => call[1].endsWith('/complete')).length, 1);
});

test('server expiry stays visible, retains originals, and stops worker polling', async () => {
    const manager = makeManager(async () => {});
    const row = await queued(manager);
    const saved = await manager.savePublication(row, { ...publication(row, 'cancelled'), error: 'Upload expired. Start a new publication.' });
    assert.equal(saved.status, 'expired');
    assert.equal(saved.discard_requested, undefined);
    assert.equal(await (await manager.db.file(`${row.key}:${row.descriptor.items[0].client_uid}`)).text(), 'test');
    await manager.cancel(saved);
    assert.equal((await manager.db.get(row.key)).discard_requested, true);
    assert.equal((await manager.db.get(row.key)).status, 'cancelled');
});

test('native publications deduplicate by client UID or server ID and own all actions', async () => {
    for (const match of ['client', 'server', 'nested']) {
        const apiCalls = [];
        const nativeCalls = [];
        let nativeRows = [];
        const nativeBridge = { capabilities: async () => ({ enabled: true }), listeners: new Set(), connect: () => true,
            request: async (method, payload) => { nativeCalls.push([method, payload]); return method === 'list' ? { items: nativeRows } : {}; } };
        const manager = makeManager(async (method, path) => {
            apiCalls.push([method, path]);
            if (path === '/capabilities') return capability;
            if (path === '') return [server];
            throw new Error('The browser must not run native publication requests');
        }, { bridge: nativeBridge, transfer: async () => { throw new Error('The browser must not transfer native files'); } });
        const row = await queued(manager);
        const server = publication(row);
        const stale = await manager.savePublication(row, server);
        const native = { id: match === 'client' ? undefined : server.id, native_id: 'android-job', user_id: '1',
            client_uid: match === 'server' ? 'native-client' : match === 'nested' ? undefined : row.client_uid,
            ...(match === 'nested' ? { descriptor: row.descriptor } : { kind: 'post', content: 'native caption' }), status: 'uploading', progress: 67 };
        nativeRows = [native, { ...native }, { ...native, user_id: '2', native_id: 'other-account', progress: 9 }];
        manager.nativeCapability = { enabled: true }; manager.nativeAccount = '1';
        await manager.reconcile();
        assert.equal(manager.rows.length, 1, match);
        assert.equal(manager.rows[0].native, true); assert.equal(manager.rows[0].progress, 67);
        assert.equal((await manager.db.get(row.key)).native_owner.native_id, 'android-job');
        await manager.tick();
        await manager.process(await manager.db.get(row.key), manager.epoch);
        await manager.retry(stale); await manager.cancel(stale);
        assert.deepEqual(nativeCalls.filter(([method]) => ['retry', 'cancel'].includes(method)).map(([method, payload]) => [method, payload.native_id]), [['retry', 'android-job'], ['cancel', 'android-job']]);
        assert.ok(apiCalls.every(([method, path]) => method === 'GET' && ['', '/capabilities'].includes(path)));
        assert.equal((await manager.db.get(row.key)).cancel_requested, undefined);
        manager.nativeRows = [];
        nativeBridge.request = async () => { throw new Error('Bridge unavailable'); };
        await manager.refresh();
        assert.equal(manager.rows.length, 1); assert.equal(manager.rows[0].native, true);
        await assert.rejects(manager.cancel(stale), /Bridge unavailable/);
        assert.equal((await manager.db.get(row.key)).cancel_requested, undefined);
    }
});

test('canary permits only all privacy for browser and native admission', async () => {
    for (const kind of ['post', 'story', 'chat']) for (const native of [false, true]) {
        const manager = makeManager(async () => {});
        manager.capability = { ...capability, privacy_options: ['all', 'followers', 'selected_users'] };
        const item = { ...selection(), ...(native ? { native_file_id: 'opaque-handle' } : {}) };
        for (const privacy of ['followers', 'selected_users', 'private']) await assert.rejects(manager.enqueue({ kind, privacy }, [item]), /Everyone/);
        await assert.rejects(manager.enqueue({ kind, privacy: 'all', selected_user_ids: [2] }, [item]), /Everyone/);
        assert.equal(manager.rows.length, 0);
    }
});

test('cancel racing publication reconciles DELETE 409 to published and stops retrying', async () => {
    const calls = [];
    const manager = makeManager(async (method, path) => {
        calls.push([method, path]);
        if (path === '/capabilities') return capability;
        if (method === 'DELETE') throw { response: { status: 409 }, message: 'Already published' };
        return { ...publication(row, 'published', 'processed'), result: { id: 42, type: 'post', url: '/posts/42' } };
    });
    const row = await queued(manager);
    const admitted = await manager.savePublication(row, publication(row));
    await manager.cancel(admitted);
    await manager.tick();
    assert.deepEqual(calls.filter(([, path]) => path !== '/capabilities'), [['DELETE', '/publication-1'], ['GET', '/publication-1']]);
    const saved = await manager.db.get(row.key);
    assert.equal(saved.status, 'published'); assert.equal(saved.cancel_requested, false);
    assert.equal(saved.publication.result.id, 42); assert.equal(manager.timer, undefined);
    await manager.tick();
    assert.equal(calls.filter(([method]) => method === 'DELETE').length, 1);
});
