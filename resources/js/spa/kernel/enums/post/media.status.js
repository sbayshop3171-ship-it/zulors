const MediaStatus = Object.freeze({
    PROCESSING: 'processing',
    PROCESSED: 'processed',
    UNPROCESSED: 'unprocessed',
    FAILED: 'failed'
});

const MediaStatusUtils = {
    isProcessing: (status) => {
        return status === MediaStatus.PROCESSING;
    },
    isProcessed: (status) => {
        return status === MediaStatus.PROCESSED;
    },
    isFailed: (status) => {
        return status === MediaStatus.FAILED;
    }
};

export { MediaStatus, MediaStatusUtils };
