import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const workerSource = await readFile(new URL('../../public/pwa/service-worker.js', import.meta.url), 'utf8');

function createWorkerHarness() {
    const origin = 'https://app.example.test';
    const listeners = new Map();
    const responses = new Map();
    const requests = [];
    const stored = new Map();

    class WorkerRequest extends Request {
        constructor(input, init) {
            super(input instanceof Request ? input : new URL(input, origin).href, init);
        }
    }

    const cache = {
        async match(request) {
            const response = stored.get(request instanceof Request ? request.url : new URL(request, origin).href);
            return response?.clone() || undefined;
        },
        async put(request, response) {
            const key = request instanceof Request ? request.url : new URL(request, origin).href;
            stored.set(key, response.clone());
        },
        async delete(request) {
            const key = request instanceof Request ? request.url : new URL(request, origin).href;
            return stored.delete(key);
        },
    };
    const caches = {
        async open() { return cache; },
        async keys() { return ['zulors-media-v1']; },
        async delete() { return true; },
    };
    const self = {
        location: { href: `${origin}/pwa/service-worker.js?v=test`, origin },
        addEventListener(type, listener) { listeners.set(type, listener); },
        skipWaiting() {},
        clients: { claim: async () => {} },
    };
    const fetch = async (request) => {
        requests.push(request);
        if (request.headers?.has('range')) {
            return new Response('range bytes', { status: 206, headers: { 'content-length': '11' } });
        }
        const url = request instanceof Request ? request.url : new URL(request, origin).href;
        const responseFactory = responses.get(url);
        return responseFactory ? responseFactory() : new Response('x', { headers: { 'content-length': '1' } });
    };

    vm.runInNewContext(workerSource, { self, caches, fetch, Request: WorkerRequest, Response, URL, setTimeout, clearTimeout });

    async function dispatchFetch(request) {
        let responsePromise;
        listeners.get('fetch')({ request, respondWith(value) { responsePromise = value; } });
        const response = await responsePromise;
        await new Promise((resolve) => setImmediate(resolve));
        return response;
    }

    async function updateWindow(current = [], next = []) {
        let completion = Promise.resolve();
        listeners.get('message')({
            data: { type: 'ZULORS_REELS_CACHE_WINDOW', current, next },
            waitUntil(value) { completion = value; },
        });
        await completion;
    }

    async function mediaMeta() {
        const response = await cache.match(new WorkerRequest('/__zulors_media_cache_meta__'));
        return response ? response.json() : { entries: {} };
    }

    return { origin, cache, requests, responses, updateWindow, dispatchFetch, mediaMeta, Request: WorkerRequest };
}

test('the current reel keeps its poster ahead of older low-priority media at the LRU entry limit', async () => {
    const worker = createWorkerHarness();
    const currentUrl = `${worker.origin}/media/current.jpg`;
    await worker.updateWindow([currentUrl], []);
    await worker.dispatchFetch(new worker.Request(currentUrl));

    for (let index = 0; index < 160; index++) {
        await worker.dispatchFetch(new worker.Request(`${worker.origin}/media/older-${index}.jpg`));
    }

    const meta = await worker.mediaMeta();
    assert.equal(Object.keys(meta.entries).length, 160);
    assert.equal(meta.entries[currentUrl]?.priority, 3);
    assert.equal(meta.entries[`${worker.origin}/media/older-0.jpg`], undefined);
    assert.ok(meta.entries[currentUrl]);
});

test('a Range request bypasses cached full media and keeps its partial response semantics', async () => {
    const worker = createWorkerHarness();
    const url = `${worker.origin}/media/video.mp4`;
    await worker.dispatchFetch(new worker.Request(url));

    const partial = await worker.dispatchFetch(new worker.Request(url, { headers: { Range: 'bytes=0-10' } }));

    assert.equal(partial.status, 206);
    assert.equal(await partial.text(), 'range bytes');
    const full = await worker.cache.match(new worker.Request(url));
    assert.equal(full.status, 200);
});

test('a prioritized HLS master passes priority to its variant playlist and first segment', async () => {
    const worker = createWorkerHarness();
    const masterUrl = `${worker.origin}/media/master.m3u8`;
    const variantUrl = `${worker.origin}/media/360/index.m3u8`;
    const segmentUrl = `${worker.origin}/media/360/segment_00000.ts`;
    worker.responses.set(masterUrl, () => new Response('#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=900000,RESOLUTION=640x360\n360/index.m3u8\n', {
        headers: { 'content-type': 'application/vnd.apple.mpegurl' },
    }));
    worker.responses.set(variantUrl, () => new Response('#EXTM3U\n#EXTINF:4.0,\nsegment_00000.ts\n#EXT-X-ENDLIST\n', {
        headers: { 'content-type': 'application/vnd.apple.mpegurl' },
    }));
    await worker.updateWindow([masterUrl], []);
    await worker.dispatchFetch(new worker.Request(masterUrl));
    await worker.dispatchFetch(new worker.Request(variantUrl));
    await worker.dispatchFetch(new worker.Request(segmentUrl));

    const meta = await worker.mediaMeta();
    assert.equal(meta.entries[masterUrl]?.priority, 3);
    assert.equal(meta.entries[variantUrl]?.priority, 3);
    assert.equal(meta.entries[segmentUrl]?.priority, 3);
});
