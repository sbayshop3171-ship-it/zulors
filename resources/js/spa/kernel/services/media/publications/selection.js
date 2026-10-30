import { buildVideoPresentationMetadata, readVideoFileMetadata } from '../video-metadata.js';
import { durationSecondsToObject } from '../../../helpers/media/audio/index.js';

const videoMimeByExtension = {
    mp4: 'video/mp4', m4v: 'video/x-m4v', mov: 'video/quicktime',
    webm: 'video/webm', '3gp': 'video/3gpp', '3g2': 'video/3gpp2',
    mkv: 'video/x-matroska', avi: 'video/x-msvideo', mpg: 'video/mpeg', mpeg: 'video/mpeg',
};

export function publicationMediaMime(file) {
    const mime = String(file?.mime || file?.type || '').toLowerCase().split(';')[0].trim();
    const extension = String(file?.name || '').split('.').pop()?.toLowerCase();
    const containerMime = videoMimeByExtension[extension];

    // Some camera/provider pickers expose a codec MIME or generic binary MIME
    // instead of the container MIME. Prefer a recognized filename container.
    if (containerMime && (!mime || mime === 'application/octet-stream' || ['video/hevc', 'video/h265', 'video/h264', 'video/avc'].includes(mime))) {
        return containerMime;
    }

    return mime;
}

export function publicationMediaType(file) {
    const mime = publicationMediaMime(file);

    if (mime.startsWith('video/')) return 'video';
    if (['image/jpeg', 'image/png', 'image/webp'].includes(mime)) return 'image';

    return null;
}

export function isPublicationMedia(file) {
    return Boolean(publicationMediaType(file)) && !/\.gif$/i.test(file?.name || '');
}

export async function selectPublicationMedia(file, account = null) {
    if (!isPublicationMedia(file)) throw new Error('Select a photo or video.');
    const mime = publicationMediaMime(file);
    const type = publicationMediaType(file);
    const native = Boolean(file.native_file_id);
    const url = native ? file.preview_url || '' : URL.createObjectURL(file);
    let metadata = {};
    try {
        if (type === 'video' && !native) metadata = await readVideoFileMetadata(file);
    } catch { /* Metadata is optional; the server validates the original media. */ }
    const client_uid = crypto.randomUUID();
    const seconds = Math.max(0, Math.ceil(file.duration_seconds || metadata.duration_seconds || 0));
    const presentationMetadata = type === 'video' ? buildVideoPresentationMetadata(
        file.width || metadata.dimensions?.width,
        file.height || metadata.dimensions?.height,
        file.duration_seconds || metadata.duration_seconds
    ) : {};
    return {
        id: client_uid, client_uid, type, mime, size: file.size, account,
        name: file.name || (type === 'video' ? 'video.mp4' : 'image.jpg'),
        ...(native ? { native_file_id: file.native_file_id } : { file }),
        duration_seconds: file.duration_seconds || metadata.duration_seconds || null,
        width: presentationMetadata.dimensions?.width, height: presentationMetadata.dimensions?.height,
        source_url: url, preview_url: url, thumbnail_url: '', is_local_preview: true,
        metadata: { ...metadata, ...presentationMetadata, duration: seconds ? durationSecondsToObject(seconds) : null },
        deleted: false,
    };
}

export function releasePublicationSelection(item) {
    if (item?.preview_url?.startsWith('blob:')) URL.revokeObjectURL(item.preview_url);
}
