import { readVideoFileMetadata } from '../video-metadata.js';
import { durationSecondsToObject } from '../../../helpers/media/audio/index.js';

export function isPublicationMedia(file) {
    const mime = file?.mime || file?.type || '';
    return (mime.startsWith('video/') || ['image/jpeg', 'image/png', 'image/webp'].includes(mime)) && !/\.gif$/i.test(file?.name || '');
}

export async function selectPublicationMedia(file, account = null) {
    if (!isPublicationMedia(file)) throw new Error('Select a photo or video.');
    const mime = file.mime || file.type;
    const type = mime.startsWith('video/') ? 'video' : 'image';
    const native = Boolean(file.native_file_id);
    const url = native ? file.preview_url || '' : URL.createObjectURL(file);
    let metadata = {};
    try {
        if (type === 'video' && !native) metadata = await readVideoFileMetadata(file);
    } catch { /* Metadata is optional; the server validates the original media. */ }
    const client_uid = crypto.randomUUID();
    const seconds = Math.max(0, Math.ceil(file.duration_seconds || metadata.duration_seconds || 0));
    return {
        id: client_uid, client_uid, type, mime, size: file.size, account,
        name: file.name || (type === 'video' ? 'video.mp4' : 'image.jpg'),
        ...(native ? { native_file_id: file.native_file_id } : { file }),
        duration_seconds: file.duration_seconds || metadata.duration_seconds,
        width: file.width || metadata.dimensions?.width, height: file.height || metadata.dimensions?.height,
        source_url: url, preview_url: url, thumbnail_url: '', is_local_preview: true,
        metadata: { ...metadata, duration: seconds ? durationSecondsToObject(seconds) : null },
        deleted: false,
    };
}

export function releasePublicationSelection(item) {
    if (item?.preview_url?.startsWith('blob:')) URL.revokeObjectURL(item.preview_url);
}
