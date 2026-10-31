const MAX_EDGE = 2048;
const MIN_OUTPUT_BYTES_SAVED = 1024;

const post = (id, payload) => self.postMessage({ id, ...payload });

async function prepareImage(id, file) {
    if(typeof createImageBitmap !== 'function' || typeof OffscreenCanvas !== 'function') {
        return { file, prepared: false, reason: 'image_encoder_unavailable' };
    }

    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
        const width = Math.max(1, Math.round(bitmap.width * scale));
        const height = Math.max(1, Math.round(bitmap.height * scale));
        const canvas = new OffscreenCanvas(width, height);
        const context = canvas.getContext('2d', { alpha: true });
        if(! context) return { file, prepared: false, reason: 'image_encoder_unavailable' };
        context.drawImage(bitmap, 0, 0, width, height);
        const blob = await canvas.convertToBlob({ type: 'image/webp', quality: 0.86 });
        if(blob.type !== 'image/webp' || blob.size + MIN_OUTPUT_BYTES_SAVED >= file.size) {
            return { file, prepared: false, reason: 'original_is_smaller', dimensions: { width, height } };
        }

        const baseName = String(file.name || 'image').replace(/\.[^.]+$/, '') || 'image';
        const preparedFile = new File([blob], `${baseName}.webp`, { type: 'image/webp', lastModified: file.lastModified || Date.now() });
        return {
            file: preparedFile,
            prepared: true,
            compression_ratio: Math.max(0, Math.min(100, Math.round((1 - preparedFile.size / file.size) * 100))),
            dimensions: { width, height },
            metadata_policy: 'orientation_applied_private_metadata_removed',
        };
    }
    finally {
        bitmap?.close?.();
    }
}

self.onmessage = async ({ data }) => {
    const { id, file } = data || {};
    try {
        const result = file?.type?.startsWith('image/')
            ? await prepareImage(id, file)
            : { file, prepared: false, reason: 'media_preparation_not_supported' };

        post(id, { done: true, ...result });
    }
    catch(error) {
        post(id, { done: true, file, prepared: false, reason: 'device_preparation_failed', error: error?.message || '' });
    }
};
