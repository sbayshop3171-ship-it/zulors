export function createMultipartUploadProgress(fileSize, onProgress) {
    const loadedParts = new Map();
    let progress = 0;

    return (partNumber, loaded, partSize) => {
        // Acknowledging one part must not discard bytes sent by other workers.
        const bytes = Math.max(0, Math.min(Number(partSize) || 0, Number(loaded) || 0));
        loadedParts.set(partNumber, Math.max(loadedParts.get(partNumber) || 0, bytes));
        const total = Array.from(loadedParts.values()).reduce((sum, value) => sum + value, 0);
        progress = Math.max(progress, fileSize > 0 ? Math.min(100, Math.round(total / fileSize * 100)) : 0);
        onProgress(progress);
    };
}
