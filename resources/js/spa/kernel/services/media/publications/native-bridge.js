export class NativePublicationBridge {
    constructor(host = globalThis.window) {
        this.host = host;
        this.pending = new Map();
        this.listeners = new Set();
    }

    connect() {
        const bridge = this.host?.ZulorsUploadBridge;
        if (!bridge?.postMessage) return false;
        if (this.bridge === bridge) return true;
        this.bridge = bridge;
        bridge.onmessage = event => {
            if (event.origin && event.origin !== this.host.location?.origin) return;
            this.receive(event.data);
        };
        return true;
    }

    receive(raw) {
        let message;
        try { message = typeof raw === 'string' ? JSON.parse(raw) : raw; } catch { return; }
        if (!message || typeof message !== 'object') return;
        if (message.event === 'uploadsChanged' && Array.isArray(message.items)) {
            this.listeners.forEach(listener => listener(message.items));
        }
        const pending = this.pending.get(message.id);
        if (!pending) return;
        this.pending.delete(message.id);
        clearTimeout(pending.timer);
        if (message.error) pending.reject(new Error(message.error.message || String(message.error)));
        else pending.resolve(message.result);
    }

    request(method, payload = {}, timeout = 15000) {
        if (!this.connect()) return Promise.reject(new Error('Native uploads are unavailable.'));
        return new Promise((resolve, reject) => {
            const id = crypto.randomUUID();
            const timer = setTimeout(() => {
                this.pending.delete(id);
                reject(new Error('The app did not respond. Your media remains in the editor.'));
            }, timeout);
            this.pending.set(id, { resolve, reject, timer });
            try { this.bridge.postMessage(JSON.stringify({ id, method, payload })); }
            catch (error) { clearTimeout(timer); this.pending.delete(id); reject(error); }
        });
    }

    async capabilities() {
        if (!this.connect()) return null;
        try { return await this.request('capabilities', {}, 2000); } catch { return null; }
    }

    async pick(type) {
        const result = await this.request('pick', { type, kinds: type === 'media' ? ['image', 'video'] : [type], mime_types: type === 'video' ? ['video/*'] : type === 'image' ? ['image/jpeg', 'image/png', 'image/webp'] : ['image/jpeg', 'image/png', 'image/webp', 'video/*'], multiple: false }, 180000);
        const items = Array.isArray(result) ? result : result?.items || (result?.native_file_id ? [result] : []);
        return items.filter(item => item.native_file_id && item.size > 0);
    }
}
