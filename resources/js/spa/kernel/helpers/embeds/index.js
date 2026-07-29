window.embedder = function(path, defaultValue = undefined) {
    // Make sure window.BackendEmbeds is defined and is an object
    if (typeof window.BackendEmbeds !== 'object' || window.BackendEmbeds === null) {
        return defaultValue;
    }

    const parts = path.split('.');
    let current = window.BackendEmbeds;

    for(const part of parts) {
        if (current && Object.prototype.hasOwnProperty.call(current, part)) {
            current = current[part];
        } else {
            return defaultValue;
        }
    }

    // If value is explicitly undefined, use defaultValue
    return typeof current !== 'undefined' ? current : defaultValue;
}

window.assetUrl = function(path = '') {
    return embedder('links.assets_url') + path;
};

window.config = function(path, defaultValue = undefined) {
    return embedder(`config.${path}`, defaultValue);
}

window.base_url = function(path = '', defaultValue = undefined) {
    return embedder(`links.base_url`) + path;
}

window.freezeScroll = function() {
    window.ACTIVE_MODALS = window.ACTIVE_MODALS || 0;

    window.ACTIVE_MODALS++;

    document.body.style.overflow = 'hidden';
}

window.unfreezeScroll = function() {
    window.ACTIVE_MODALS--;

    if (window.ACTIVE_MODALS < 1) {
        document.body.style.overflow = 'auto';
    }
}
