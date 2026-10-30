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

const shouldReduceConcurrency = error => {
    const status = Number(error?.status || 0);
    return status === 0 || status === 408 || status === 429 || status >= 500;
};

export async function transferItem({
    upload, completed_parts = [], file, concurrency = 4, initialConcurrency = 2,
    signal, put = uploadPart, onProgress = () => {}, beforePart = async () => {},
    onPartComplete = async () => {}, onConcurrencyChange = async () => {},
}) {
    const multipart = upload.upload_type === 'multipart' || Array.isArray(upload.parts);
    const parts = multipart ? upload.parts : [{ ...upload, start: 0, end: file.size }];
    if (!parts?.length) throw new Error('No upload parts were provided.');
    const complete = new Map(completed_parts.map(part => [Number(part.part_number), part.etag]));
    const loaded = parts.map(part => complete.has(Number(part.part_number)) ? part.end - part.start : 0);
    const pending = parts.map((part, index) => ({ part, index }))
        .filter(({ part }) => !complete.has(Number(part.part_number)));
    const maxConcurrency = Math.max(1, Math.min(4, Number(concurrency) || 2));
    let activeConcurrency = Math.max(1, Math.min(maxConcurrency, Number(initialConcurrency) || 2));
    let next = 0;
    let failure;

    const persistConcurrency = async value => {
        try { await onConcurrencyChange(value); } catch { /* Local progress persistence must not stop a transfer. */ }
    };

    await new Promise((resolve, reject) => {
        let active = 0;
        let settled = false;
        const schedule = () => {
            if (settled) return;
            if (failure && active === 0) {
                settled = true;
                reject(failure);
                return;
            }
            if (!failure && next >= pending.length && active === 0) {
                settled = true;
                resolve();
                return;
            }

            while (!failure && active < activeConcurrency && next < pending.length) {
                const { part, index } = pending[next++];
                active++;
                void (async () => {
                    try {
                        if (signal?.aborted) throw new DOMException('Paused', 'AbortError');
                        await beforePart();
                        const etag = await put(part, file.slice(part.start, part.end, file.type), { signal, onProgress: bytes => {
                            loaded[index] = bytes;
                            onProgress(Math.min(100, Math.round(loaded.reduce((a, b) => a + b, 0) / file.size * 100)));
                        } });
                        if (multipart && !etag) throw new Error('Upload ETag is unavailable. Check storage CORS settings.');
                        if (multipart) {
                            complete.set(Number(part.part_number), etag);
                            try { await onPartComplete({ part_number: Number(part.part_number), etag }); } catch { /* R2 is authoritative during resume. */ }
                        }

                        if (!failure && activeConcurrency < maxConcurrency) {
                            activeConcurrency++;
                            await persistConcurrency(activeConcurrency);
                        }
                    } catch (error) {
                        if (!failure) {
                            failure = error;
                            if (shouldReduceConcurrency(error) && activeConcurrency > 1) {
                                activeConcurrency = Math.max(1, Math.floor(activeConcurrency / 2));
                                try { error.nextConcurrency = activeConcurrency; } catch { /* Platform errors may be non-extensible. */ }
                                await persistConcurrency(activeConcurrency);
                            }
                        }
                    } finally {
                        active--;
                        schedule();
                    }
                })();
            }
        };

        schedule();
    });

    return multipart ? [...complete].map(([part_number, etag]) => ({ part_number, etag })).sort((a, b) => a.part_number - b.part_number) : [];
}
