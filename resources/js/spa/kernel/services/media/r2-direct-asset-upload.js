import { prepareImageFile } from './prepare-media-file.js';
import { transferItem } from './publications/transfer.js';

const mimeByExtension = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };

function putObject(upload, file, onProgress) {
    return new Promise((resolve, reject) => {
        const url = new URL(upload.upload_url);
        if (url.protocol !== 'https:' || url.username || url.password || url.origin === globalThis.location?.origin) {
            return reject(new Error('A separate R2 upload URL was not returned.'));
        }
        const xhr = new XMLHttpRequest();
        xhr.open(upload.upload_method || 'PUT', url.href);
        xhr.withCredentials = false;
        xhr.timeout = 10 * 60 * 1000;
        Object.entries(upload.upload_headers || {}).forEach(([key, value]) => {
            if (!/^(authorization|cookie|x-xsrf-token|x-csrf-token|x-requested-with|host|content-length)$/i.test(key)) xhr.setRequestHeader(key, value);
        });
        xhr.upload.onprogress = event => onProgress(event.lengthComputable ? Math.round(event.loaded / event.total * 100) : 0);
        xhr.onload = () => xhr.status >= 200 && xhr.status < 300
            ? resolve()
            : reject(Object.assign(new Error(`R2 image upload failed (${xhr.status}).`), { status: xhr.status }));
        xhr.onerror = xhr.ontimeout = () => reject(Object.assign(new Error('R2 image upload connection was interrupted.'), { status: 0 }));
        xhr.onabort = () => reject(new DOMException('Upload cancelled.', 'AbortError'));
        xhr.send(file);
    });
}

const unwrap = response => response?.data?.data ?? response?.data ?? response;
const pause = attempt => new Promise(resolve => setTimeout(resolve, 500 * (2 ** attempt)));

export async function directAssetImageUpload({ file, operation, chatId, request, onProgress = () => {}, onStage = () => {} }) {
    const extension = String(file?.name || '').split('.').pop()?.toLowerCase();
    const sourceMime = file?.type || mimeByExtension[extension] || '';
    const source = sourceMime && file?.type !== sourceMime ? new File([file], file.name || `image.${extension || 'jpg'}`, { type: sourceMime, lastModified: file.lastModified || Date.now() }) : file;
    const preparation = await prepareImageFile(source, {
        onStage: stage => onStage({ stage: 'preparing', progress: stage.progress || 0 }),
    });
    const preparedFile = preparation.file || source;
    const preparedExtension = String(preparedFile.name || '').split('.').pop()?.toLowerCase();
    const mime = preparedFile.type || mimeByExtension[preparedExtension];
    if (!mime || !mimeByExtension[preparedExtension]) {
        await preparation.cleanup?.();
        throw new Error('Choose a JPEG, PNG or WebP image.');
    }

    try {
        onStage({ stage: preparation.prepared ? 'prepared' : 'original', progress: 100, prepared: preparation.prepared });
        const clientUid = crypto.randomUUID();
        let upload;
        let createError;
        for (let attempt = 0; attempt < 3; attempt++) {
            try {
                upload = unwrap(await request('create', {
                    operation,
                    client_uid: clientUid,
                    ...(chatId ? { chat_id: chatId } : {}),
                    name: preparedFile.name || file.name || `image.${preparedExtension}`,
                    size: preparedFile.size,
                    mime,
                    extension: preparedExtension,
                }));
                createError = null;
                break;
            } catch (error) {
                createError = error;
                if (attempt < 2) await pause(attempt);
            }
        }
        if (createError) throw createError;
        if (upload?.completed) return { ...upload.result, prepared: preparation.prepared, reason: preparation.reason || null };
        if (!upload?.uid) throw new Error('Direct R2 image upload is unavailable.');

        onStage({ stage: 'uploading', progress: 0 });
        let parts = [];
        if (upload.upload_type === 'multipart') {
            let transferError;
            for (let attempt = 0; attempt < 3; attempt++) {
                try {
                    parts = await transferItem({
                        upload,
                        file: preparedFile,
                        concurrency: upload.upload_concurrency || 2,
                        initialConcurrency: 2,
                        onProgress,
                    });
                    transferError = null;
                    break;
                } catch (error) {
                    transferError = error;
                    if (attempt < 2) await pause(attempt);
                }
            }
            if (transferError) throw transferError;
        }
        else {
            if (!upload.upload_url) throw new Error('Direct R2 image upload is unavailable.');
            let lastError;
            for (let attempt = 0; attempt < 3; attempt++) {
                try {
                    await putObject(upload, preparedFile, onProgress);
                    lastError = null;
                    break;
                } catch (error) {
                    lastError = error;
                    if (attempt < 2) await pause(attempt);
                }
            }
            if (lastError) throw lastError;
        }

        let completeError;
        for (let attempt = 0; attempt < 3; attempt++) {
            try {
                const result = unwrap(await request('complete', { operation, uid: upload.uid, parts }));
                onStage({ stage: 'complete', progress: 100 });
                return { ...result, prepared: preparation.prepared, reason: preparation.reason || null };
            } catch (error) {
                completeError = error;
                if (attempt < 2) await new Promise(resolve => setTimeout(resolve, 500 * (2 ** attempt)));
            }
        }
        throw completeError;
    } finally {
        await preparation.cleanup?.();
    }
}
