export default {
    install(app) {
        app.config.globalProperties.$asset = (path = '') => {
            return embedder('links.assets_url') + path;
        };

        app.config.globalProperties.$embedder = function(path, defaultValue = undefined) {
            return embedder(path, defaultValue);
        }
    }
};
