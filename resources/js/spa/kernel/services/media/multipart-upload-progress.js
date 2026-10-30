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

export async function uploadMultipartPartsAdaptive(parts, maxConcurrency, uploadPart, { initialConcurrency = 2, onConcurrencyChange = async () => {} } = {}) {
    const limit = Math.max(1, Math.min(4, Number(maxConcurrency) || 2));
    let concurrency = Math.max(1, Math.min(limit, Number(initialConcurrency) || 2));
    let next = 0;
    let active = 0;
    let failure;

    const persistConcurrency = async value => {
        try { await onConcurrencyChange(value); } catch { /* Keep transfer independent from advisory state. */ }
    };

    await new Promise((resolve, reject) => {
        let settled = false;
        const schedule = () => {
            if (settled) return;
            if (failure && active === 0) {
                settled = true;
                reject(failure);
                return;
            }
            if (!failure && next >= parts.length && active === 0) {
                settled = true;
                resolve();
                return;
            }

            while (!failure && active < concurrency && next < parts.length) {
                const part = parts[next++];
                active++;
                void Promise.resolve().then(() => uploadPart(part)).then(async () => {
                    if (!failure && concurrency < limit) {
                        concurrency++;
                        await persistConcurrency(concurrency);
                    }
                }).catch(async error => {
                    if (!failure) {
                        failure = error;
                        if (concurrency > 1) {
                            concurrency = Math.max(1, Math.floor(concurrency / 2));
                            try { error.nextConcurrency = concurrency; } catch { /* Platform errors may be non-extensible. */ }
                            await persistConcurrency(concurrency);
                        }
                    }
                }).finally(() => {
                    active--;
                    schedule();
                });
            }
        };

        schedule();
    });
}
