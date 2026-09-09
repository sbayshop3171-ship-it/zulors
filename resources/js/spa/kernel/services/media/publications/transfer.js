export function uploadPart(part, body, { signal, onProgress = () => {}, xhrFactory = () => new XMLHttpRequest() } = {}) {
    return new Promise((resolve, reject) => {
        const xhr = xhrFactory();
        const abort = () => xhr.abort();
        const finish = (error, value) => {
            signal?.removeEventListener('abort', abort);
            if (error) reject(error); else resolve(value);
        };
        if (signal?.aborted) return reject(new DOMException('Paused', 'AbortError'));
        const url = new URL(part.upload_url);
        if (!['https:', 'http:'].includes(url.protocol)) return reject(new Error('Invalid upload URL.'));
        if (url.username || url.password || url.origin === globalThis.location?.origin) return reject(new Error('A separate storage upload URL is required.'));
        xhr.open(part.upload_method || 'PUT', url.href);
        xhr.withCredentials = false;
        xhr.timeout = 10 * 60 * 1000;
        for (const [key, value] of Object.entries(part.upload_headers || {})) {
            if (!/^(authorization|cookie|x-xsrf-token|x-csrf-token|x-requested-with|x-socket-id|host|content-length)$/i.test(key)) xhr.setRequestHeader(key, value);
        }
        xhr.upload.onprogress = event => onProgress(event.loaded);
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                onProgress(body.size);
                finish(null, xhr.getResponseHeader('ETag'));
            } else finish(Object.assign(new Error(`Upload failed (${xhr.status}).`), { status: xhr.status, retryAfter: xhr.getResponseHeader('Retry-After'), upload: true }));
        };
        xhr.onerror = xhr.ontimeout = () => finish(Object.assign(new Error('Upload connection interrupted.'), { status: 0 }));
        xhr.onabort = () => finish(new DOMException('Paused', 'AbortError'));
        signal?.addEventListener('abort', abort, { once: true });
        xhr.send(body);
    });
}

export async function transferItem({ upload, completed_parts = [], file, concurrency = 2, signal, put = uploadPart, onProgress = () => {}, beforePart = async () => {} }) {
    const multipart = upload.upload_type === 'multipart' || Array.isArray(upload.parts);
    const parts = multipart ? upload.parts : [{ ...upload, start: 0, end: file.size }];
    if (!parts?.length) throw new Error('No upload parts were provided.');
    const complete = new Map(completed_parts.map(part => [Number(part.part_number), part.etag]));
    const loaded = parts.map(part => complete.has(Number(part.part_number)) ? part.end - part.start : 0);
    let next = 0;
    let failure;
    const worker = async () => {
        while (next < parts.length && !failure) {
            const index = next++;
            const part = parts[index];
            if (complete.has(Number(part.part_number))) continue;
            try {
                if (signal?.aborted) throw new DOMException('Paused', 'AbortError');
                await beforePart();
                const etag = await put(part, file.slice(part.start, part.end, file.type), { signal, onProgress: bytes => {
                    loaded[index] = bytes;
                    onProgress(Math.min(100, Math.round(loaded.reduce((a, b) => a + b, 0) / file.size * 100)));
                } });
                if (multipart && !etag) throw new Error('Upload ETag is unavailable. Check storage CORS settings.');
                complete.set(Number(part.part_number), etag);
            } catch (error) { failure = error; }
        }
    };
    await Promise.all(Array.from({ length: Math.min(parts.length, Math.max(1, Math.min(2, concurrency))) }, worker));
    if (failure) throw failure;
    return multipart ? [...complete].map(([part_number, etag]) => ({ part_number, etag })).sort((a, b) => a.part_number - b.part_number) : [];
}
