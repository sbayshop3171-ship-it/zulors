import test from 'node:test';
import assert from 'node:assert/strict';
import { uploadR2DirectVideo } from '../../resources/js/spa/kernel/services/media/r2-direct-video-upload.js';

test('multipart uploader slices every byte, bounds concurrency, and returns ordered ETags', async () => {
    const previous = globalThis.XMLHttpRequest;
    let active = 0;
    let peak = 0;
    const bodies = new Map();
    class XHR {
        upload = {};
        open(method, url) { this.url = url; assert.equal(method, 'PUT'); }
        setRequestHeader() {}
        getResponseHeader() { return `"${this.url}"`; }
        send(body) {
            active++;
            peak = Math.max(peak, active);
            body.text().then(text => {
                bodies.set(this.url, text);
                this.upload.onprogress({ loaded: body.size });
                active--;
                this.status = 200;
                this.onload();
            });
        }
    }
    globalThis.XMLHttpRequest = XHR;
    try {
        const progress = [];
        const parts = await uploadR2DirectVideo({
            upload_type: 'multipart', upload_concurrency: 2,
            parts: [0, 1, 2].map(i => ({ part_number: i + 1, start: i * 3, end: Math.min(8, (i + 1) * 3), upload_url: `part-${i + 1}` })),
        }, new Blob(['abcdefgh']), value => progress.push(value));
        assert.equal(peak, 2);
        assert.deepEqual([...bodies.values()], ['abc', 'def', 'gh']);
        assert.deepEqual(parts.map(part => part.part_number), [1, 2, 3]);
        assert.equal(parts[2].etag, '"part-3"');
        assert.equal(progress.at(-1), 100);
    }
    finally { globalThis.XMLHttpRequest = previous; }
});

test('raw upload retries a connection failure without using the application server', async () => {
    const previous = globalThis.XMLHttpRequest;
    let attempts = 0;
    globalThis.XMLHttpRequest = class {
        upload = {};
        open(method, url) { assert.equal(url, 'https://r2.example.test/upload'); }
        setRequestHeader() {}
        getResponseHeader() { return null; }
        send() {
            queueMicrotask(() => {
                if(++attempts === 1) this.onerror();
                else { this.status = 200; this.onload(); }
            });
        }
    };
    try {
        assert.deepEqual(await uploadR2DirectVideo({ upload_type: 'raw', upload_url: 'https://r2.example.test/upload' }, new Blob(['video'])), []);
        assert.equal(attempts, 2);
    }
    finally { globalThis.XMLHttpRequest = previous; }
});

test('an empty multipart session is rejected', async () => {
    await assert.rejects(uploadR2DirectVideo({ upload_type: 'multipart', parts: [] }, new Blob(['video'])), /No video upload parts/);
});
