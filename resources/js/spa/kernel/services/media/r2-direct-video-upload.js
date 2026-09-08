import { readVideoFileMetadata } from './video-metadata.js';

export async function directVideoMetadata(file, options = {}) {
    options = Object.fromEntries(Object.entries(options).filter(([, value]) => value !== undefined));
    const metadata = await readVideoFileMetadata(file);
    return {
        name: file.name || options.name || 'video.mp4',
        size: file.size,
        mime: file.type || 'video/mp4',
        extension: options.extension || file.name?.split('.').pop() || 'mp4',
        width: metadata.dimensions?.width,
        height: metadata.dimensions?.height,
        duration_seconds: Math.ceil(metadata.duration_seconds || options.duration || 0),
        ...options,
    };
}

function putPart(part, body, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(part.upload_method || 'PUT', part.upload_url);
        xhr.timeout = 10 * 60 * 1000;
        Object.entries(part.upload_headers || {}).forEach(([key, value]) => {
            if(! ['host', 'content-length'].includes(key.toLowerCase())) xhr.setRequestHeader(key, value);
        });
        xhr.upload.onprogress = (event) => onProgress(event.loaded);
        xhr.onload = () => {
            if(xhr.status >= 200 && xhr.status < 300) {
                onProgress(body.size);
                resolve(xhr.getResponseHeader('ETag'));
            }
            else reject(new Error(`Video upload failed (${xhr.status}).`));
        };
        xhr.onerror = () => reject(new Error('Video upload connection failed.'));
        xhr.ontimeout = () => reject(new Error('Video upload timed out.'));
        xhr.onabort = () => reject(new Error('Video upload cancelled.'));
        xhr.send(body);
    });
}

export async function uploadR2DirectVideo(upload, file, onProgress = () => {}) {
    const parts = upload.upload_type === 'multipart' ? upload.parts : [{ ...upload, start: 0, end: file.size }];
    if(! parts?.length) throw new Error('No video upload parts were provided.');
    const loaded = new Array(parts.length).fill(0);
    const completed = new Array(parts.length);
    let next = 0;
    let failure = null;
    const worker = async () => {
        while(next < parts.length && ! failure) {
            const index = next++;
            const part = parts[index];
            const body = file.slice(part.start, part.end, file.type);
            for(let attempt = 0; attempt < 3; attempt++) {
                try {
                    const etag = await putPart(part, body, (bytes) => {
                        loaded[index] = bytes;
                        onProgress(Math.min(100, Math.round(loaded.reduce((a, b) => a + b, 0) / file.size * 100)));
                    });
                    completed[index] = { part_number: part.part_number, etag };
                    break;
                }
                catch(error) {
                    if(attempt === 2) { failure = error; return; }
                    await new Promise(resolve => setTimeout(resolve, 500 * (2 ** attempt)));
                }
            }
        }
    };
    await Promise.all(Array.from({ length: Math.min(parts.length, Math.max(1, Math.min(4, Number(upload.upload_concurrency) || 3))) }, worker));
    if(failure) throw failure;
    return upload.upload_type === 'multipart' ? completed : [];
}

// Progress is advisory. Completion is sent only after all R2 requests settle.
export async function directVideoUpload({ file, options, request, onProgress, onCreated }) {
    const response = await request('create', await directVideoMetadata(file, options));
    const upload = response.data.data;
    if(! upload.direct_upload) return null;
    const identity = { media_id: upload.media_id || upload.id || upload.media?.id, uid: upload.uid };
    onCreated?.(upload);
    let pendingProgress = Promise.resolve();
    let lastProgress = -10;
    try {
        const parts = await uploadR2DirectVideo(upload, file, progress => {
            onProgress?.(progress);
            if(progress >= lastProgress + 10) {
                lastProgress = progress;
                pendingProgress = pendingProgress.then(() => request('progress', {
                    ...identity, upload_state: 'uploading', upload_progress: progress,
                })).catch(() => {});
            }
        });
        await pendingProgress;
        const result = await request('complete', { ...identity, upload_id: upload.upload_id, parts });
        return result.data.data;
    }
    catch(error) {
        await pendingProgress;
        await request('progress', { ...identity, upload_state: 'failed', upload_progress: 0 }).catch(() => {});
        throw error;
    }
}
