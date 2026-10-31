import { buildVideoPresentationMetadata } from '../video-metadata.js';
import { durationSecondsToObject } from '../../../helpers/media/audio/index.js';

const videoMimeByExtension = {
    mp4: 'video/mp4', m4v: 'video/x-m4v', mov: 'video/quicktime',
    webm: 'video/webm', '3gp': 'video/3gpp', '3g2': 'video/3gpp2',
    mkv: 'video/x-matroska', avi: 'video/x-msvideo', mpg: 'video/mpeg', mpeg: 'video/mpeg',
};
const mimeByExtension = {
    ...videoMimeByExtension,
    jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp',
    mp3: 'audio/mpeg', wav: 'audio/wav', m4a: 'audio/mp4', aac: 'audio/aac', ogg: 'audio/ogg',
    pdf: 'application/pdf', doc: 'application/msword',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ppt: 'application/vnd.ms-powerpoint',
    pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    xls: 'application/vnd.ms-excel',
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    txt: 'text/plain', zip: 'application/zip', rar: 'application/vnd.rar',
};
const audioMimes = new Set(['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/x-m4a', 'audio/x-wav']);
const documentMimes = new Set([
    'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/vnd.rar',
]);

export function publicationMediaMime(file) {
    const mime = String(file?.mime || file?.type || '').toLowerCase().split(';')[0].trim();
    const extension = String(file?.name || '').split('.').pop()?.toLowerCase();
    const containerMime = videoMimeByExtension[extension];

    // Some camera/provider pickers expose a codec MIME or generic binary MIME
    // instead of the container MIME. Prefer a recognized filename container.
    if (containerMime && (!mime || mime === 'application/octet-stream' || ['video/hevc', 'video/h265', 'video/h264', 'video/avc'].includes(mime))) {
        return containerMime;
    }

    if ((!mime || mime === 'application/octet-stream') && mimeByExtension[extension]) return mimeByExtension[extension];

    return mime;
}

export function publicationMediaType(file) {
    const mime = publicationMediaMime(file);

    if (['video/mp4', 'video/quicktime', 'video/webm', 'video/avi', 'video/x-msvideo', 'video/mpeg', 'video/x-m4v', 'video/3gpp', 'video/3gpp2', 'video/x-matroska'].includes(mime)) return 'video';
    if (['image/jpeg', 'image/png', 'image/webp'].includes(mime)) return 'image';
    if (audioMimes.has(mime)) return 'audio';
    if (documentMimes.has(mime)) return 'document';

    return null;
}

export function isPublicationMedia(file) { return Boolean(publicationMediaType(file)) && !/\.gif$/i.test(file?.name || ''); }

export async function selectPublicationMedia(file, account = null) {
    if (!isPublicationMedia(file)) throw new Error('Select a photo or video.');
    const mime = publicationMediaMime(file);
    const type = publicationMediaType(file);
    const native = Boolean(file.native_file_id);
    const url = native ? file.preview_url || '' : URL.createObjectURL(file);
    const metadata = {};
    const client_uid = crypto.randomUUID();
    const seconds = Math.max(0, Math.ceil(file.duration_seconds || 0));
    const presentationMetadata = type === 'video' ? buildVideoPresentationMetadata(
        file.width,
        file.height,
        file.duration_seconds
    ) : {};
    return {
        id: client_uid, client_uid, type, mime, size: file.size, account,
        name: file.name || ({ video: 'video.mp4', image: 'image.jpg', audio: 'audio.webm', document: 'document.pdf' }[type] || 'media.bin'),
        ...(native ? { native_file_id: file.native_file_id } : { file }),
        duration_seconds: file.duration_seconds || null,
        width: presentationMetadata.dimensions?.width, height: presentationMetadata.dimensions?.height,
        source_url: url, preview_url: url, thumbnail_url: '', is_local_preview: true,
        metadata: { ...metadata, ...presentationMetadata, duration: seconds ? durationSecondsToObject(seconds) : null },
        deleted: false,
    };
}

export function releasePublicationSelection(item) {
    if (item?.preview_url?.startsWith('blob:')) URL.revokeObjectURL(item.preview_url);
}
