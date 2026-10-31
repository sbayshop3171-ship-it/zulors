import { uploadMultipartPartsAdaptive } from './multipart-upload-progress.js';

const videoMimeByExtension = {
    mp4: 'video/mp4', m4v: 'video/x-m4v', mov: 'video/quicktime',
    webm: 'video/webm', avi: 'video/x-msvideo', mpg: 'video/mpeg', mpeg: 'video/mpeg',
    '3gp': 'video/3gpp', '3g2': 'video/3gpp2', mkv: 'video/x-matroska',
};
const acceptedVideoMimes = new Set(Object.values(videoMimeByExtension));

export async function directVideoMetadata(file, options = {}) {
    options = Object.fromEntries(Object.entries(options).filter(([, value]) => value !== undefined));
    const extension = String(options.extension || file.name?.split('.').pop() || 'mp4').toLowerCase();
    const sourceMime = String(file.type || '').toLowerCase();
    const providedMime = String(options.mime || '').toLowerCase();
    const mime = acceptedVideoMimes.has(providedMime)
        ? providedMime
        : acceptedVideoMimes.has(sourceMime)
            ? sourceMime
            : (videoMimeByExtension[extension] || sourceMime || 'video/mp4');
    return {
        ...options,
        name: file.name || options.name || 'video.mp4',
        size: file.size,
        mime,
        extension,
        width: options.width || options.dimensions?.width,
        height: options.height || options.dimensions?.height,
        duration_seconds: Math.ceil(Number(options.duration_seconds || options.duration || 0)),
    };
}

function putPart(part, body, onProgress) {
    return new Promise((resolve, reject) => {
        const url = new URL(part.upload_url);
        if(url.protocol !== 'https:' || url.username || url.password || url.origin === globalThis.location?.origin) {
            return reject(new Error('R2 upload URLs must use HTTPS and a separate storage origin.'));
        }
        const xhr = new XMLHttpRequest();
        xhr.open(part.upload_method || 'PUT', url.href);
        xhr.withCredentials = false;
        xhr.timeout = 10 * 60 * 1000;
        Object.entries(part.upload_headers || {}).forEach(([key, value]) => {
            if(! /^(authorization|cookie|x-xsrf-token|x-csrf-token|x-requested-with|x-socket-id|host|content-length)$/i.test(key)) xhr.setRequestHeader(key, value);
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
    const completed = new Map();
    await uploadMultipartPartsAdaptive(parts, Number(upload.upload_concurrency) || 4, async part => {
        const index = parts.indexOf(part);
        const body = file.slice(part.start, part.end, file.type);
        let lastError;
        for(let attempt = 0; attempt < 3; attempt++) {
            try {
                const etag = await putPart(part, body, bytes => {
                    loaded[index] = bytes;
                    onProgress(Math.min(100, Math.round(loaded.reduce((a, b) => a + b, 0) / file.size * 100)));
                });
                completed.set(Number(part.part_number), { part_number: part.part_number, etag });
                return;
            }
            catch(error) {
                lastError = error;
                if(attempt < 2) await new Promise(resolve => setTimeout(resolve, 500 * (2 ** attempt)));
            }
        }
        throw lastError;
    });
    return upload.upload_type === 'multipart' ? [...completed.values()].sort((a, b) => a.part_number - b.part_number) : [];
}

// Progress is advisory. Completion is sent only after all R2 requests settle.
export async function directVideoUpload({ file, options, request, onProgress, onCreated, onStage }) {
    const uploadFile = file;
    let identity = null;
    let pendingProgress = Promise.resolve();
    let lastProgress = -10;

    try {
        onStage?.({ stage: 'uploading', progress: 0, label: 'uploading_original_to_r2' });
        const response = await request('create', await directVideoMetadata(uploadFile, options));
        const upload = response.data.data;
        if(! upload.direct_upload) return upload.already_received ? upload.result : null;
        identity = { media_id: upload.media_id || upload.id || upload.media?.id, uid: upload.uid };
        onCreated?.(upload);
        onStage?.({ stage: 'uploading', progress: 0, label: 'uploading_to_r2' });
        const parts = await uploadR2DirectVideo(upload, uploadFile, progress => {
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
        onStage?.({ stage: 'complete', progress: 100, label: 'upload_complete' });
        return result.data.data;
    }
    catch(error) {
        await pendingProgress;
        if(identity) await request('progress', { ...identity, upload_state: 'failed', upload_progress: 0 }).catch(() => {});
        throw error;
    }
}
