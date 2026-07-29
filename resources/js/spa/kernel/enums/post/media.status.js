const MediaStatus = Object.freeze({
    PROCESSING: 'processing',
    PROCESSED: 'processed',
    UNPROCESSED: 'unprocessed',
    FAILED: 'failed'
});

const MediaStatusUtils = {
    isProcessing: (status) => {
        return status === MediaStatus.PROCESSING;
    }
};

export { MediaStatus, MediaStatusUtils };