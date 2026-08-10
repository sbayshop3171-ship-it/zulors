const playbackCandidates = new Map();
let activeCandidateId = null;
let preferredCandidateId = null;
let manualPauseHoldId = null;
let visibilityListenerBound = false;

function hasWindow() {
    return typeof window !== 'undefined';
}

function hasDocument() {
    return typeof document !== 'undefined';
}

function isCandidateEligible(candidate) {
    return Boolean(candidate?.isReady && candidate?.isVisible && ! candidate?.manualPaused);
}

function normalizeRatio(value) {
    const safeValue = Number(value || 0);

    if(! Number.isFinite(safeValue)) {
        return 0;
    }

    return Math.max(0, Math.min(1, safeValue));
}

function computeCenterBias(candidate) {
    if(! hasWindow() || ! candidate?.rect) {
        return 0;
    }

    const viewportHeight = window.innerHeight || document.documentElement?.clientHeight || 0;

    if(! viewportHeight) {
        return 0;
    }

    const viewportCenter = viewportHeight / 2;
    const rectCenter = candidate.rect.top + (candidate.rect.height / 2);
    const distance = Math.abs(rectCenter - viewportCenter);

    return Math.max(0, 1 - (distance / viewportHeight));
}

function computeCandidateScore(candidate) {
    if(! isCandidateEligible(candidate)) {
        return -1;
    }

    return (normalizeRatio(candidate.ratio) * 1000) + (computeCenterBias(candidate) * 100);
}

function deactivateCandidate(candidate) {
    try {
        candidate?.deactivate?.();
    }
    catch (error) {
        //
    }
}

function activateCandidate(candidate) {
    try {
        candidate?.activate?.();
    }
    catch (error) {
        //
    }
}

function pauseOtherCandidates(activeId = null) {
    playbackCandidates.forEach((candidate, candidateId) => {
        if(candidateId !== activeId) {
            deactivateCandidate(candidate);
        }
    });
}

function releaseManualPauseHoldIfHidden(candidateId) {
    if(manualPauseHoldId !== candidateId) {
        return;
    }

    const candidate = playbackCandidates.get(candidateId);

    if(! candidate?.isVisible) {
        manualPauseHoldId = null;

        if(candidate) {
            candidate.manualPaused = false;
        }
    }
}

function syncActivePlayback() {
    if(hasDocument() && document.visibilityState === 'hidden') {
        pauseAllVideoPlayback();
        return;
    }

    if(manualPauseHoldId) {
        const heldCandidate = playbackCandidates.get(manualPauseHoldId);

        if(heldCandidate?.isVisible) {
            pauseOtherCandidates(null);

            if(activeCandidateId && activeCandidateId !== manualPauseHoldId) {
                deactivateCandidate(playbackCandidates.get(activeCandidateId));
            }

            activeCandidateId = null;
            preferredCandidateId = null;

            return;
        }

        manualPauseHoldId = null;
    }

    let nextActiveCandidate = null;
    let bestScore = -1;

    if(preferredCandidateId) {
        const preferredCandidate = playbackCandidates.get(preferredCandidateId);

        if(isCandidateEligible(preferredCandidate)) {
            nextActiveCandidate = preferredCandidate;
        }
    }

    if(! nextActiveCandidate) {
        playbackCandidates.forEach((candidate) => {
            const candidateScore = computeCandidateScore(candidate);

            if(candidateScore > bestScore) {
                bestScore = candidateScore;
                nextActiveCandidate = candidate;
            }
        });
    }

    preferredCandidateId = null;

    if(! nextActiveCandidate) {
        if(activeCandidateId) {
            deactivateCandidate(playbackCandidates.get(activeCandidateId));
        }

        activeCandidateId = null;
        pauseOtherCandidates(null);

        return;
    }

    if(activeCandidateId && activeCandidateId !== nextActiveCandidate.id) {
        deactivateCandidate(playbackCandidates.get(activeCandidateId));
    }

    activeCandidateId = nextActiveCandidate.id;
    pauseOtherCandidates(activeCandidateId);
    activateCandidate(nextActiveCandidate);
}

function bindVisibilityListener() {
    if(visibilityListenerBound || ! hasDocument()) {
        return;
    }

    document.addEventListener('visibilitychange', () => {
        if(document.visibilityState === 'hidden') {
            pauseAllVideoPlayback();
        }
        else {
            syncActivePlayback();
        }
    });

    visibilityListenerBound = true;
}

function registerVideoPlaybackCandidate({ id, activate, deactivate }) {
    if(! id) {
        return () => {};
    }

    bindVisibilityListener();

    playbackCandidates.set(id, {
        id,
        activate,
        deactivate,
        isReady: false,
        isVisible: false,
        ratio: 0,
        rect: null,
        manualPaused: false,
    });

    return () => {
        unregisterVideoPlaybackCandidate(id);
    };
}

function updateVideoPlaybackCandidate(id, payload = {}) {
    const candidate = playbackCandidates.get(id);

    if(! candidate) {
        return;
    }

    Object.assign(candidate, payload);

    if(payload.isVisible === false) {
        releaseManualPauseHoldIfHidden(id);
    }

    syncActivePlayback();
}

function requestVideoPlayback(id) {
    const candidate = playbackCandidates.get(id);

    if(! candidate) {
        return;
    }

    candidate.manualPaused = false;

    if(manualPauseHoldId === id) {
        manualPauseHoldId = null;
    }

    preferredCandidateId = id;
    syncActivePlayback();
}

function setVideoPlaybackManualPause(id, isPaused = true) {
    const candidate = playbackCandidates.get(id);

    if(! candidate) {
        return;
    }

    candidate.manualPaused = Boolean(isPaused);

    if(candidate.manualPaused) {
        manualPauseHoldId = candidate.isVisible ? id : null;

        if(activeCandidateId === id) {
            activeCandidateId = null;
        }

        deactivateCandidate(candidate);
    }
    else if(manualPauseHoldId === id) {
        manualPauseHoldId = null;
    }

    syncActivePlayback();
}

function unregisterVideoPlaybackCandidate(id) {
    if(! playbackCandidates.has(id)) {
        return;
    }

    if(activeCandidateId === id) {
        activeCandidateId = null;
    }

    if(preferredCandidateId === id) {
        preferredCandidateId = null;
    }

    if(manualPauseHoldId === id) {
        manualPauseHoldId = null;
    }

    playbackCandidates.delete(id);
    syncActivePlayback();
}

function pauseAllVideoPlayback() {
    playbackCandidates.forEach((candidate) => {
        deactivateCandidate(candidate);
    });

    activeCandidateId = null;
}

export {
    pauseAllVideoPlayback,
    registerVideoPlaybackCandidate,
    requestVideoPlayback,
    setVideoPlaybackManualPause,
    unregisterVideoPlaybackCandidate,
    updateVideoPlaybackCandidate,
};
