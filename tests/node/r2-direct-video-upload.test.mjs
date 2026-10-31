import test from 'node:test';
import assert from 'node:assert/strict';
import { directVideoMetadata, directVideoUpload, uploadR2DirectVideo } from '../../resources/js/spa/kernel/services/media/r2-direct-video-upload.js';

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
            parts: [0, 1, 2].map(i => ({ part_number: i + 1, start: i * 3, end: Math.min(8, (i + 1) * 3), upload_url: `https://r2.example.test/part-${i + 1}` })),
        }, new Blob(['abcdefgh']), value => progress.push(value));
        assert.equal(peak, 2);
        assert.deepEqual([...bodies.values()], ['abc', 'def', 'gh']);
        assert.deepEqual(parts.map(part => part.part_number), [1, 2, 3]);
        assert.equal(parts[2].etag, '"https://r2.example.test/part-3"');
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

test('video upload begins without device encoding and sends the original container directly to R2', async () => {
    const file = new File(['original-video'], 'camera.mp4', { type: 'video/hevc' });
    const stages = [];
    const requests = [];
    const previous = globalThis.XMLHttpRequest;
    globalThis.XMLHttpRequest = class {
        upload = {};
        open(method, url) { assert.equal(method, 'PUT'); assert.equal(url, 'https://r2.example.test/original'); }
        setRequestHeader() {}
        getResponseHeader() { return 'original-etag'; }
        send(body) {
            body.text().then(text => {
                assert.equal(text, 'original-video');
                this.status = 200;
                this.upload.onprogress({ loaded: body.size });
                this.onload();
            });
        }
    };
    try {
        const metadata = await directVideoMetadata(file, { duration_seconds: 3600 });
        assert.equal(metadata.mime, 'video/mp4', 'codec MIME is normalized to its MP4 container');
        assert.equal(metadata.size, file.size, 'original byte size is preserved');
        const result = await directVideoUpload({
            file,
            options: { duration_seconds: 3600 },
            onStage: stage => stages.push(stage.stage),
            request: async (action, payload) => {
                requests.push([action, payload]);
                if (action === 'create') return { data: { data: {
                    direct_upload: true, uid: 'uploads/original.mp4', upload_type: 'raw',
                    upload_url: 'https://r2.example.test/original',
                } } };
                if (action === 'progress') return { data: { data: {} } };
                return { data: { data: { uploaded: true } } };
            },
        });
        assert.deepEqual(result, { uploaded: true });
        assert.equal(requests[0][1].size, file.size);
        assert.equal(requests[0][1].mime, 'video/mp4');
        assert.deepEqual(stages.slice(0, 2), ['uploading', 'uploading']);
    }
    finally { globalThis.XMLHttpRequest = previous; }
});
