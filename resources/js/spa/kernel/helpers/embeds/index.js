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

    if (window.ACTIVE_MODALS === 0 && document.body) {
        window.__zulorsScrollLockState = {
            overflow: document.body.style.getPropertyValue('overflow'),
            overflowPriority: document.body.style.getPropertyPriority('overflow'),
            overflowX: document.body.style.getPropertyValue('overflow-x'),
            overflowXPriority: document.body.style.getPropertyPriority('overflow-x'),
            overflowY: document.body.style.getPropertyValue('overflow-y'),
            overflowYPriority: document.body.style.getPropertyPriority('overflow-y')
        };
    }

    window.ACTIVE_MODALS++;

    if (document.body) {
        document.body.style.setProperty('overflow', 'hidden', 'important');
    }
}

window.unfreezeScroll = function() {
    window.ACTIVE_MODALS = Math.max((window.ACTIVE_MODALS || 0) - 1, 0);

    if (window.ACTIVE_MODALS < 1 && document.body) {
        const previousState = window.__zulorsScrollLockState || {};
        const restoreProperty = (propertyName, value, priority = '') => {
            if (value) {
                document.body.style.setProperty(propertyName, value, priority);
            }
            else {
                document.body.style.removeProperty(propertyName);
            }
        };

        restoreProperty('overflow', previousState.overflow, previousState.overflowPriority);
        restoreProperty('overflow-x', previousState.overflowX, previousState.overflowXPriority);
        restoreProperty('overflow-y', previousState.overflowY, previousState.overflowYPriority);

        window.__zulorsScrollLockState = null;
    }
}
