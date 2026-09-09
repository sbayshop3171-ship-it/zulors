import { ref, onBeforeUnmount, watch } from 'vue';
import { publicationManager } from '@/kernel/services/media/publications/index.js';
import { isPublicationMedia, selectPublicationMedia, releasePublicationSelection } from '@/kernel/services/media/publications/selection.js';

export function useMediaPublication(kind) {
    const selections = ref([]);
    const selecting = ref(false);
    let mounted = true;
    let sequence = 0;
    let clientUid = crypto.randomUUID();
    const clear = () => {
        sequence++;
        clientUid = crypto.randomUUID();
        selections.value.forEach(releasePublicationSelection);
        selections.value = [];
    };
    const select = async file => {
        if (!file || !isPublicationMedia(file) || !await publicationManager.enabled(kind)) return false;
        if (kind !== 'post' && selections.value.length) clear();
        if (selections.value.length >= 10 || (selections.value.length && (selections.value[0].type === 'video' || (file.mime || file.type).startsWith('video/')))) throw new Error('Choose one video, or up to 10 photos.');
        const token = sequence;
        const account = publicationManager.account;
        selecting.value = true;
        try {
            const item = await selectPublicationMedia(file, account);
            if (!mounted || token !== sequence) { releasePublicationSelection(item); return true; }
            selections.value.push(item);
            return true;
        } finally { selecting.value = false; }
    };
    const pick = async (type, input) => {
        const items = await publicationManager.pick(kind, type);
        if (items === null) input?.click();
        else for (const item of items) {
            if (!await select(item)) throw new Error('Choose a JPEG, PNG, WebP photo or a supported video.');
        }
    };
    const remove = item => {
        releasePublicationSelection(item);
        selections.value = selections.value.filter(value => value.client_uid !== item.client_uid);
    };
    const enqueue = descriptor => publicationManager.enqueue({ ...descriptor, kind, client_uid: clientUid }, selections.value);
    onBeforeUnmount(() => { mounted = false; clear(); });
    return { selections, selecting, select, pick, remove, clear, enqueue };
}

export function useChatMediaPublication(chatStore, content, reply, state) {
    const composer = useMediaPublication('chat');
    watch(() => chatStore.chatId, composer.clear);
    const submit = async () => {
        if (!composer.selections.value.length) return false;
        if (state.isSubmitting || composer.selecting.value) return true;
        const chatId = chatStore.chatId;
        state.isSubmitting = true;
        try {
            await composer.enqueue({ chat_id: chatId, content: content.value, ...(reply.value ? { parent_id: reply.value.id } : {}) });
            composer.clear();
            if (chatStore.chatId === chatId) { content.value = ''; reply.value = null; }
        } catch (error) { globalThis.toastError?.(error.message); }
        finally { state.isSubmitting = false; }
        return true;
    };
    return { ...composer, submit };
}
