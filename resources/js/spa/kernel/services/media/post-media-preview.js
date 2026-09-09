// Keep device-only URLs out of the draft and public timeline stores.
export function mergePostMediaPreviews(serverMedia = [], localPreviews = []) {
    const matched = new Set();
    const media = serverMedia.map(item => {
        const preview = localPreviews.find(local => local.media_id != null && String(local.media_id) === String(item.id));
        if(! preview) return item;

        matched.add(preview);
        return {
            ...item,
            preview_url: preview.preview_url,
            preview_key: preview.id,
            metadata: { ...preview.metadata, ...item.metadata }
        };
    });

    return media.concat(localPreviews.filter(preview => ! matched.has(preview)));
}
