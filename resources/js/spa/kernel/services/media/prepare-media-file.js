const fallback = (file, reason = 'device_preparation_unavailable') => ({
    file,
    prepared: false,
    reason,
    compression_ratio: 0,
    cleanup: async () => {},
});

export function prepareMediaFile(file, { onStage = () => {}, signal } = {}) {
    if(!(file instanceof Blob) || !file.type?.match(/^(image|video)\//)) {
        return Promise.resolve(fallback(file, 'unsupported_media_type'));
    }
    // Video encoding can heat, slow or crash a phone. Keep video bytes intact
    // and start the resumable R2 transfer immediately.
    if(file.type.startsWith('video/')) {
        return Promise.resolve(fallback(file, 'video_uploads_use_original_r2'));
    }
    if(typeof Worker === 'undefined') {
        return Promise.resolve(fallback(file));
    }

    return new Promise(resolve => {
        let worker;
        let settled = false;
        const requestId = globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
        const finish = result => {
            if(settled) return;
            settled = true;
            signal?.removeEventListener('abort', abort);
            worker?.terminate();
            const preparedFile = result?.file instanceof Blob ? result.file : file;
            const cleanupName = result?.cleanup_name;
            const cleanupDirectory = result?.cleanup_directory;
            resolve({
                file: preparedFile,
                prepared: Boolean(result?.prepared && preparedFile !== file),
                reason: result?.reason || null,
                compression_ratio: Math.max(0, Math.min(100, Number(result?.compression_ratio) || 0)),
                dimensions: result?.dimensions || null,
                metadata_policy: result?.metadata_policy || null,
                cleanup: async () => {
                    if(! cleanupName || ! cleanupDirectory || typeof navigator === 'undefined' || typeof navigator.storage?.getDirectory !== 'function') return;
                    try {
                        const root = await navigator.storage.getDirectory();
                        const directory = await root.getDirectoryHandle(cleanupDirectory);
                        await directory.removeEntry(cleanupName);
                    }
                    catch {}
                },
            });
        };
        const abort = () => finish(fallback(file, 'preparation_cancelled'));

        try {
            worker = new Worker(new URL('./media-preparation.worker.js', import.meta.url), { type: 'module' });
            worker.onmessage = ({ data }) => {
                if(data?.id !== requestId) return;
                if(data.stage === 'preparing') {
                    onStage({ stage: 'preparing', progress: Math.max(0, Math.min(100, Number(data.progress) || 0)) });
                    return;
                }
                if(data.done) finish(data);
            };
            worker.onerror = () => finish(fallback(file, 'device_preparation_failed'));
            signal?.addEventListener('abort', abort, { once: true });
            if(signal?.aborted) return abort();
            onStage({ stage: 'preparing', progress: 0 });
            worker.postMessage({ id: requestId, file });
        }
        catch {
            finish(fallback(file));
        }
    });
}

export async function prepareImageFile(file, options = {}) {
    if(!file?.type?.startsWith('image/')) return fallback(file, 'unsupported_media_type');
    return prepareMediaFile(file, options);
}

export async function prepareVideoFile(file, options = {}) {
    if(!file?.type?.startsWith('video/')) return fallback(file, 'unsupported_media_type');
    options.onStage?.({ stage: 'uploading', progress: 100 });
    return fallback(file, 'video_uploads_use_original_r2');
}
