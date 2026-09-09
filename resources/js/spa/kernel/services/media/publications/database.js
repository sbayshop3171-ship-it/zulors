export class PublicationDatabase {
    constructor(indexedDB = globalThis.indexedDB, name = 'zulors-media-publications-v1') {
        this.indexedDB = indexedDB;
        this.name = name;
    }

    open() {
        if (!this.pending) this.pending = new Promise((resolve, reject) => {
            if (!this.indexedDB) return reject(new Error('Local media storage is unavailable. Keep this editor open.'));
            const request = this.indexedDB.open(this.name, 1);
            request.onupgradeneeded = () => {
                for (const name of ['publications', 'files', 'leases']) request.result.createObjectStore(name, { keyPath: 'key' });
            };
            request.onerror = () => reject(request.error);
            request.onblocked = () => reject(new Error('Local media storage is blocked by another tab.'));
            request.onsuccess = () => {
                request.result.onversionchange = () => { request.result.close(); this.pending = null; };
                resolve(request.result);
            };
        }).catch(error => { this.pending = null; throw error; });
        return this.pending;
    }

    async transaction(stores, action, mode = 'readwrite') {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(stores, mode);
            let result;
            tx.oncomplete = () => resolve(result);
            tx.onerror = tx.onabort = () => reject(tx.error || new Error('Media could not be saved. Keep this editor open.'));
            try { action(tx, value => { result = value; }); }
            catch (error) { tx.abort(); reject(error); }
        });
    }

    async enqueue(record, files) {
        await this.transaction(['publications', 'files'], tx => {
            tx.objectStore('publications').add(record);
            files.forEach(file => tx.objectStore('files').add(file));
        });
    }

    async list(account) {
        return this.transaction(['publications'], (tx, done) => {
            tx.objectStore('publications').getAll().onsuccess = event => done(event.target.result.filter(row => row.account === account));
        }, 'readonly');
    }

    async file(key) {
        return this.transaction(['files'], (tx, done) => {
            tx.objectStore('files').get(key).onsuccess = event => done(event.target.result?.blob);
        }, 'readonly');
    }

    async get(key) {
        return this.transaction(['publications'], (tx, done) => {
            tx.objectStore('publications').get(key).onsuccess = event => done(event.target.result);
        }, 'readonly');
    }

    async update(key, change) {
        return this.transaction(['publications', 'files'], (tx, done) => {
            const store = tx.objectStore('publications');
            store.get(key).onsuccess = event => {
                const next = change(event.target.result);
                if (!next) return done(null);
                store.put(next);
                if (next.status === 'published' || (next.status === 'cancelled' && next.discard_requested)) {
                    next.descriptor.items.forEach(item => tx.objectStore('files').delete(`${key}:${item.client_uid}`));
                }
                done(next);
            };
        });
    }

    async restart(key, account) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(['publications', 'files'], 'readwrite');
            const publications = tx.objectStore('publications');
            const files = tx.objectStore('files');
            let result;
            let failure;
            const fail = message => { failure = new Error(message); tx.abort(); };
            tx.oncomplete = () => resolve(result);
            tx.onerror = tx.onabort = () => reject(failure || tx.error || new Error('The upload could not be restarted. Your media is still saved.'));
            publications.get(key).onsuccess = event => {
                const current = event.target.result;
                if (!current || current.account !== account || !['expired', 'cancelled'].includes(current.status)) return fail('This upload cannot be restarted.');
                const oldItems = current.descriptor.items;
                if (!oldItems?.length) return fail('Original media is unavailable on this device. Select it again.');
                const client_uid = crypto.randomUUID();
                const nextKey = `${account}:${client_uid}`;
                const items = oldItems.map(item => ({ ...item, client_uid: crypto.randomUUID() }));
                const blobs = new Array(items.length);
                let remaining = items.length;
                oldItems.forEach((item, index) => {
                    files.get(`${key}:${item.client_uid}`).onsuccess = event => {
                        if (!event.target.result?.blob) return fail('Original media is unavailable on this device. Select it again.');
                        blobs[index] = event.target.result.blob;
                        if (--remaining) return;
                        result = { key: nextKey, account, client_uid, descriptor: { ...current.descriptor, client_uid, items }, status: 'queued', progress: 0, created_at: Date.now(), updated_at: Date.now(), retry_at: 0 };
                        publications.add(result);
                        items.forEach((item, index) => files.add({ key: `${nextKey}:${item.client_uid}`, blob: blobs[index] }));
                        publications.put({ ...current, status: 'cancelled', discard_requested: true, superseded_by: nextKey });
                        oldItems.forEach(item => files.delete(`${key}:${item.client_uid}`));
                    };
                });
            };
        });
    }

    async lease(key, owner, ttl = 30000) {
        return this.transaction(['leases'], (tx, done) => {
            const store = tx.objectStore('leases');
            store.get(key).onsuccess = event => {
                const current = event.target.result;
                if (current && current.owner !== owner && current.expires > Date.now()) return done(false);
                store.put({ key, owner, expires: Date.now() + ttl });
                done(true);
            };
        });
    }

    async release(key, owner) {
        await this.transaction(['leases'], tx => {
            const store = tx.objectStore('leases');
            store.get(key).onsuccess = event => {
                if (event.target.result?.owner === owner) store.delete(key);
            };
        });
    }
}
