const PostStatus = Object.freeze({
    ACTIVE: 'active',
    DRAFT: 'draft',
    PUBLISHED: 'published',
    DELETED: 'deleted',
    PROCESSING_VIDEO: 'processing_video'
});

const PostStatusUtils = {
    isActive: (status) => {
        return status === PostStatus.ACTIVE;
    }
};

export { PostStatus, PostStatusUtils };