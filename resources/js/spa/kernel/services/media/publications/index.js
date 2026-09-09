import { shallowRef } from 'vue';
import { AxiosAuth } from '@/kernel/services/axios/index.js';
import { PublicationManager } from './manager.js';

export const publicationManager = new PublicationManager({
    request: async (method, path, data, signal) => {
        const response = await AxiosAuth.request({
            method, url: `${window.location.origin}/api/media-publications${path}`, data, signal,
            headers: data?.client_uid ? { 'Idempotency-Key': data.client_uid } : {},
        });
        return response.data?.data;
    },
});

export const publicationRows = shallowRef([]);
publicationManager.subscribe(rows => { publicationRows.value = rows.map(row => ({ ...row })); });

export function setPublicationAccount(userId) {
    publicationManager.setAccount(userId).catch(() => {});
}
