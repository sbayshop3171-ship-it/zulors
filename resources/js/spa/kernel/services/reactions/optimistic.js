function reactionImageUrl(unifiedId) {
    if(typeof embedder === 'function') {
        return `${embedder('links.assets.emoji').replace(/\/$/, '')}/${unifiedId}.png`;
    }

    return '';
}

function applyOptimisticReaction(postData, unifiedId) {
    const previousReactions = (postData.relations?.reactions || []).map((reactionItem) => {
        return { ...reactionItem };
    });
    const selectedReaction = previousReactions.find((reactionItem) => {
        return reactionItem.unified_id === unifiedId;
    });
    const isRemovingSelectedReaction = Boolean(selectedReaction?.has_reacted);

    if(! postData.relations) {
        postData.relations = {};
    }

    const reactions = previousReactions.map((reactionItem) => {
        const nextItem = { ...reactionItem };

        if(nextItem.has_reacted) {
            nextItem.total = Math.max(0, Number(nextItem.total || 0) - 1);
            nextItem.has_reacted = false;
        }

        return nextItem;
    }).filter((reactionItem) => {
        return Number(reactionItem.total || 0) > 0;
    });

    if(isRemovingSelectedReaction) {
        postData.relations.reactions = reactions;

        return () => {
            postData.relations.reactions = previousReactions;
        };
    }

    const existingReaction = reactions.find((reactionItem) => {
        return reactionItem.unified_id === unifiedId;
    });

    if(existingReaction) {
        existingReaction.total = Number(existingReaction.total || 0) + 1;
        existingReaction.has_reacted = true;
    }
    else {
        reactions.push({
            unified_id: unifiedId,
            image_url: reactionImageUrl(unifiedId),
            total: 1,
            has_reacted: true
        });
    }

    postData.relations.reactions = reactions;

    return () => {
        postData.relations.reactions = previousReactions;
    };
}

export { applyOptimisticReaction };
