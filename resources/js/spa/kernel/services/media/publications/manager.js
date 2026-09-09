import { PublicationDatabase } from './database.js';
import { NativePublicationBridge } from './native-bridge.js';
import { transferItem } from './transfer.js';

export const terminalStatuses = ['published', 'failed', 'cancelled', 'expired', 'auth_required'];
const active = row => !terminalStatuses.includes(row.status);
const identity = value => value == null ? null : String(value);
const clientUid = row => identity(row.client_uid ?? row.descriptor?.client_uid ?? row.publication?.client_uid);
const serverId = row => identity(row.server_id ?? row.publication?.id ?? row.id);
const samePublication = (a, b) => Boolean((clientUid(a) && clientUid(a) === clientUid(b)) || (serverId(a) && serverId(a) === serverId(b)));

export class PublicationManager {
    constructor({ request, db = new PublicationDatabase(), bridge = new NativePublicationBridge(), transfer = transferItem, host = globalThis.window, locks = globalThis.navigator?.locks } = {}) {
        Object.assign(this, { request, db, bridge, transfer, host, locks });
        this.owner = crypto.randomUUID();
        this.account = null;
        this.rows = [];
        this.listeners = new Set();
        this.controllers = new Map();
        this.capability = null;
        this.nativeAccount = null;
        this.nativeRows = [];
        this.epoch = 0;
        if (host?.BroadcastChannel) {
            this.channel = new host.BroadcastChannel('zulors-media-publications-v1');
            this.channel.onmessage = ({ data }) => {
                if (data.event === 'account' && identity(data.account) !== this.account) {
                    this.pause();
                    this.capability = null;
                }
                this.refresh().then(() => {
                    this.rows.filter(row => row.cancel_requested).forEach(row => this.controllers.get(row.key)?.abort());
                    this.wake();
                }).catch(() => {});
            };
        }
        this.onVisible = () => {
            this.reconcile().catch(() => {});
            this.wake();
        };
        host?.addEventListener('online', this.onVisible);
        host?.addEventListener('focus', this.onVisible);
        this.bridge.listeners.add(() => this.refresh().catch(() => {}));
    }

    subscribe(listener) { this.listeners.add(listener); listener(this.rows); return () => this.listeners.delete(listener); }
    notify() { this.listeners.forEach(listener => listener(this.rows)); }
    broadcast() { this.channel?.postMessage({ event: 'changed' }); }
    pause() {
        this.epoch++;
        clearTimeout(this.timer);
        this.controllers.forEach(controller => controller.abort());
    }

    async setAccount(value) {
        const account = identity(value);
        if (this.account === account && this.capability) return;
        this.pause();
        this.account = account;
        this.capability = null;
        this.nativeAccount = null;
        this.nativeRows = [];
        this.rows = [];
        this.notify();
        this.channel?.postMessage({ event: 'account', account });
        if (this.nativeCapability?.enabled) await this.bridge.request('setAccount', { user_id: null }).catch(() => {});
        if (this.account !== account) return;
        if (!account) {
            return;
        }
        await this.capabilities();
        if (this.account !== account) return;
        await this.refresh();
        await this.reconcile();
        this.wake();
    }

    async capabilities(force = false) {
        if (!this.account) return null;
        if (this.capability && !force) return this.capability;
        const account = this.account;
        const epoch = this.epoch;
        try {
            const result = await this.request('GET', '/capabilities');
            if (this.account !== account || this.epoch !== epoch) return null;
            if (identity(result.user_id) !== account) {
                this.pause();
                this.capability = null;
                return null;
            }
            this.capability = result;
            if (this.nativeCapability === undefined) this.nativeCapability = await this.bridge.capabilities();
            if (this.account !== account || this.epoch !== epoch) return null;
            if (this.nativeCapability?.enabled && this.nativeAccount !== account) {
                await this.bridge.request('setAccount', { user_id: result.user_id });
                if (this.account !== account || this.epoch !== epoch) return null;
                this.nativeAccount = account;
            }
            return result;
        } catch { return null; }
    }

    async enabled(kind) {
        const capability = await this.capabilities();
        return Boolean(capability?.enabled && capability.kinds?.includes(kind));
    }

    async pick(kind, type) {
        const account = this.account;
        if (!await this.enabled(kind) || !this.nativeCapability?.enabled) return null;
        const files = await this.bridge.pick(type);
        if (account !== this.account) throw new Error('Account changed. Select media again.');
        return files;
    }

    async enqueue(descriptor, selections) {
        const account = this.account;
        if (!await this.enabled(descriptor.kind) || account !== this.account) throw new Error('Media publishing is unavailable for this account.');
        if ((descriptor.privacy ?? 'all') !== 'all' || descriptor.selected_user_ids?.length || !(this.capability.privacy_options || ['all']).includes('all')) throw new Error('Only the Everyone audience is supported for media publishing.');
        if (!selections.length) throw new Error('Select a photo or video.');
        if (selections.some(item => item.account != null && identity(item.account) !== account)) throw new Error('Account changed. Select media again.');
        if ((descriptor.kind !== 'post' || selections.some(item => item.type === 'video')) && selections.length !== 1) throw new Error('Choose one video, or photos for a post.');
        if (selections.length > 10) throw new Error('Choose up to 10 photos.');
        if (selections.some(item => item.type === 'video' && !item.duration_seconds)) throw new Error('Video duration is unavailable. Select the video again.');
        const client_uid = descriptor.client_uid || crypto.randomUUID();
        const native = selections.every(item => item.native_file_id);
        if (!native && selections.some(item => item.native_file_id)) throw new Error('Choose media from the same picker.');
        const items = selections.map(item => ({
            client_uid: item.client_uid, type: item.type, name: item.name, mime: item.mime, size: item.size,
            ...(item.duration_seconds ? { duration_seconds: item.duration_seconds } : {}),
            ...(item.width ? { width: item.width, height: item.height } : {}),
            ...(native ? { native_file_id: item.native_file_id } : {}),
        }));
        const payload = JSON.parse(JSON.stringify({ content: '', privacy: 'all', selected_user_ids: [], ...descriptor, client_uid, items }));
        if (native) {
            const result = await this.bridge.request('enqueue', payload, 60000);
            await this.refresh();
            return result;
        }
        if (selections.some(item => !(item.file instanceof Blob) || item.file.size !== item.size)) throw new Error('The local media file is unavailable. Select it again.');
        const key = `${account}:${client_uid}`;
        const row = { key, account, client_uid, descriptor: payload, status: 'queued', progress: 0, created_at: Date.now(), updated_at: Date.now(), retry_at: 0 };
        try {
            await this.db.enqueue(row, selections.map(item => ({ key: `${key}:${item.client_uid}`, blob: item.file })));
        } catch (error) {
            throw new Error(error.name === 'QuotaExceededError' ? 'Not enough local storage. Your media is still in the editor. Free some space and publish again.' : `Media could not be saved. Your media is still in the editor. ${error.message}`);
        }
        // No editor may report queued or close before the transaction commits.
        await this.refresh();
        this.broadcast();
        this.wake();
        return row;
    }

    async refresh() {
        const account = this.account;
        const epoch = this.epoch;
        if (!account) return;
        const storedRows = await this.db.list(account);
        let nativeRows = this.nativeRows;
        if (this.nativeCapability?.enabled && this.nativeAccount === account) {
            const result = await this.bridge.request('list').catch(() => null);
            if (result !== null) nativeRows = (Array.isArray(result) ? result : result?.items || [])
                .filter(row => row.user_id == null || identity(row.user_id) === account).map(row => ({
                    ...row, client_uid: clientUid(row), server_id: serverId(row),
                    key: `native:${row.native_id || clientUid(row) || serverId(row)}`, account, native: true,
                    descriptor: row.descriptor || row.publication || row,
                }));
        }
        if (this.account !== account || this.epoch !== epoch) return;
        this.nativeRows = nativeRows;
        const rows = [];
        for (const row of nativeRows) if (!rows.some(current => samePublication(current, row))) rows.push(row);
        for (const row of storedRows) {
            const native = nativeRows.find(native => samePublication(native, row));
            if (native) {
                const owner = { native_id: native.native_id, id: native.id, client_uid: native.client_uid, server_id: native.server_id };
                if (JSON.stringify(row.native_owner) !== JSON.stringify(owner)) await this.db.update(row.key, current => current ? { ...current, native_owner: owner } : current);
                this.controllers.get(row.key)?.abort();
                continue;
            }
            const next = row.native_owner ? { ...row, ...row.native_owner, native: true } : row;
            if (!rows.some(current => samePublication(current, next))) rows.push(next);
        }
        if (this.account !== account || this.epoch !== epoch) return;
        this.rows = rows.sort((a, b) => (b.created_at || 0) - (a.created_at || 0));
        this.notify();
    }

    nativeOwner(row) {
        return row.native || row.native_owner || this.nativeRows.find(native => samePublication(native, row));
    }

    async actionRow(row) {
        const account = this.account;
        if (row.account !== account) return null;
        await this.refresh();
        if (this.account !== account) return null;
        const native = this.rows.find(current => current.native && samePublication(current, row));
        if (native) return native;
        const stored = row.key ? await this.db.get(row.key) : null;
        if (this.account !== account) return null;
        return stored?.native_owner ? { ...stored, ...stored.native_owner, native: true } : stored || row;
    }

    async reconcile() {
        const account = this.account;
        if (!account || !(await this.capabilities(true))) return;
        const epoch = this.epoch;
        const rows = await this.request('GET', '');
        if (this.account !== account || epoch !== this.epoch) return;
        for (const publication of rows || []) {
            const key = `${account}:${publication.client_uid}`;
            await this.db.update(key, current => this.merge(current || {
                key, account, client_uid: publication.client_uid, descriptor: { ...publication, items: publication.items || [] },
                created_at: Date.now(), remote_only: true,
            }, publication));
        }
        await this.refresh();
    }

    merge(row, publication) {
        if (['published', 'cancelled'].includes(row.status) && !['published', 'cancelled'].includes(publication.status)) return row;
        const expired = publication.status === 'cancelled' && /expired/i.test(typeof publication.error === 'string' ? publication.error : publication.error?.message || '');
        const retainWait = ['waiting', 'auth_required', 'failed'].includes(row.status) && active(publication);
        return { ...row, publication, server_id: publication.id, status: publication.status === 'published' ? 'published' : expired && !row.discard_requested ? 'expired' : row.cancel_requested ? 'cancelling' : retainWait ? row.status : publication.status,
            error: retainWait ? row.error : publication.error || null, retry_at: row.retry_at || 0, updated_at: Date.now(),
            progress: publication.status === 'published' ? 100 : row.progress || 0 };
    }

    async savePublication(row, publication) {
        return this.db.update(row.key, current => this.merge(current || row, publication));
    }

    wake(delay = 0) {
        clearTimeout(this.timer);
        if (!this.account) return;
        this.timer = setTimeout(() => this.tick().catch(() => {}), delay);
    }

    async tick() {
        if (this.running || !this.account || this.host?.navigator?.onLine === false) return;
        this.running = true;
        try {
            const account = this.account;
            const epoch = this.epoch;
            if (!await this.capabilities(true) || epoch !== this.epoch) return;
            await this.refresh();
            const run = async () => {
                const key = `worker:${account}`;
                if (!await this.db.lease(key, this.owner)) return;
                const heartbeat = setInterval(() => {
                    this.db.lease(key, this.owner).then(owned => { if (!owned) this.pause(); }).catch(() => this.pause());
                }, 8000);
                try {
                    const rows = await this.db.list(account);
                    for (const row of rows) {
                        if (epoch !== this.epoch || account !== this.account) break;
                        if (this.nativeOwner(row)) continue;
                        if (!this.capability.enabled && !row.server_id && (!row.cancel_requested || row.create_attempted)) {
                            if (active(row) && row.status !== 'feature_disabled') await this.db.update(row.key, current => ({ ...current, status: 'feature_disabled' }));
                            continue;
                        }
                        if (active(row) && (!row.retry_at || row.retry_at <= Date.now())) await this.process(row, epoch);
                    }
                } finally { clearInterval(heartbeat); await this.db.release(key, this.owner); }
            };
            if (this.locks) await this.locks.request(`zulors-publications:${account}`, { ifAvailable: true }, lock => lock ? run() : undefined);
            else await run();
        } finally {
            this.running = false;
            await this.refresh().catch(() => {});
            if (this.capability && this.rows.some(row => !row.native && active(row) && (this.capability.enabled || row.server_id || (row.cancel_requested && !row.create_attempted)))) this.wake(4000);
        }
    }

    async process(row, epoch) {
        if (this.nativeOwner(row)) return;
        const controller = new AbortController();
        this.controllers.set(row.key, controller);
        const check = () => {
            if (epoch !== this.epoch || controller.signal.aborted || row.account !== this.account || this.nativeOwner(row)) throw new DOMException('Paused', 'AbortError');
        };
        const request = async (method, path, data) => {
            check();
            if (!await this.db.lease(`worker:${row.account}`, this.owner)) throw new DOMException('Worker ownership changed', 'AbortError');
            check();
            const response = await this.request(method, path, data, controller.signal);
            check();
            return response;
        };
        try {
            if (!row.server_id) {
                if (row.cancel_requested && !row.create_attempted) {
                    await this.db.update(row.key, current => ({ ...current, status: 'cancelled' }));
                    return;
                }
                row = await this.db.update(row.key, current => ({ ...current, create_attempted: true }));
                row = await this.savePublication(row, await request('POST', '', row.descriptor));
            }
            const path = `/${encodeURIComponent(row.server_id)}`;
            if (row.cancel_requested) {
                let result;
                try { result = await request('DELETE', path); }
                catch (error) {
                    if (Number(error.status || error.response?.status) !== 409) throw error;
                    result = await request('GET', path);
                    if (!['published', 'cancelled'].includes(result.status)) throw error;
                }
                await this.db.update(row.key, current => this.merge({ ...current, cancel_requested: false }, result || { ...current.publication, status: 'cancelled' }));
                return;
            }
            if (row.retry_requested) {
                row = await this.savePublication(row, await request('POST', `${path}/retry`, {}));
                await this.db.update(row.key, current => ({ ...current, retry_requested: false }));
            } else row = await this.savePublication(row, await request('GET', path));
            if (row.cancel_requested || terminalStatuses.includes(row.status)) return;
            if (row.remote_only) return;
            for (const item of row.publication.items || []) {
                check();
                if (row.cancel_requested) break;
                if (['processed', 'ready', 'completed', 'processing', 'published'].includes(item.status)) continue;
                const itemPath = `${path}/items/${encodeURIComponent(item.id)}`;
                const resume = await request('POST', `${itemPath}/resume`, {});
                if (!resume.upload) {
                    if (resume.status === 'uploaded') row = await this.savePublication(row, await request('POST', `${itemPath}/complete`, { generation: resume.generation, parts: [] }));
                    continue;
                }
                const file = await this.db.file(`${row.key}:${item.client_uid}`);
                if (!file) {
                    if (row.remote_only) continue;
                    throw new Error('The local media file is unavailable. Cancel this upload and select it again.');
                }
                const parts = await this.transfer({ upload: resume.upload, completed_parts: resume.completed_parts,
                    file, concurrency: this.capability.upload_concurrency, signal: controller.signal,
                    beforePart: async () => {
                        check();
                        if (!await this.db.lease(`worker:${row.account}`, this.owner)) throw new DOMException('Worker ownership changed', 'AbortError');
                        check();
                    },
                    onProgress: progress => {
                        const all = row.publication.items;
                        const size = all.reduce((sum, value) => sum + value.size, 0);
                        const done = all.filter(value => value.id !== item.id && ['processed', 'processing', 'uploaded'].includes(value.status)).reduce((sum, value) => sum + value.size, 0);
                        progress = Math.round((done + item.size * progress / 100) / size * 100);
                        const view = this.rows.find(view => view.key === row.key);
                        if (view) { view.progress = progress; view.status = 'uploading'; this.notify(); }
                        if (!this.lastProgressSave || Date.now() - this.lastProgressSave > 750 || progress === 100) {
                            this.lastProgressSave = Date.now();
                            this.db.update(row.key, current => current && !current.cancel_requested ? { ...current, progress } : current).then(() => this.broadcast()).catch(() => {});
                        }
                    },
                });
                check();
                row = await this.savePublication(row, await request('POST', `${itemPath}/complete`, { generation: resume.generation, parts }));
            }
        } catch (error) {
            if (error.name === 'AbortError' || epoch !== this.epoch) return;
            const status = Number(error.status ?? error.response?.status ?? (error.request ? 0 : -1));
            const retryAfter = error.retryAfter || error.response?.headers?.['retry-after'];
            const delay = status === 429 ? Math.max(60000, Number(retryAfter) * 1000 || Date.parse(retryAfter) - Date.now() || 60000) : 10000;
            await this.db.update(row.key, current => {
                if (!current) return current;
                return { ...current, status: [401, 419].includes(status) ? 'auth_required' : current.cancel_requested ? 'cancelling' : [0, 429, 410, 503, 502, 504, 500].includes(status) || (error.upload && status === 403) ? 'waiting' : 'failed',
                    retry_requested: status === 410 || current.retry_requested, error: error.response?.data?.message || error.message,
                    retry_at: Date.now() + delay };
            });
        } finally { this.controllers.delete(row.key); this.broadcast(); }
    }

    async retry(row) {
        row = await this.actionRow(row);
        if (!row) return;
        if (row.native) await this.bridge.request('retry', { native_id: row.native_id, id: row.id, client_uid: row.client_uid });
        else if (['expired', 'cancelled'].includes(row.status)) {
            if (!await this.enabled(row.descriptor.kind)) throw new Error('Media publishing is paused. Your original media is still saved.');
            await this.db.restart(row.key, this.account);
        }
        else await this.db.update(row.key, current => ({ ...current, status: 'queued', retry_requested: Boolean(current.server_id), retry_at: 0, error: null }));
        await this.refresh(); this.broadcast(); this.wake();
    }

    async cancel(row) {
        row = await this.actionRow(row);
        if (!row) return;
        if (row.native) await this.bridge.request('cancel', { native_id: row.native_id, id: row.id, client_uid: row.client_uid });
        else {
            await this.db.update(row.key, current => ({ ...current, status: ['expired', 'cancelled'].includes(current.status) ? 'cancelled' : 'cancelling', discard_requested: true, cancel_requested: !['expired', 'cancelled'].includes(current.status), retry_at: 0 }));
            this.controllers.get(row.key)?.abort();
        }
        await this.refresh(); this.broadcast(); this.wake();
    }

    dispose() {
        this.pause(); this.channel?.close();
        this.host?.removeEventListener('online', this.onVisible);
        this.host?.removeEventListener('focus', this.onVisible);
    }
}
