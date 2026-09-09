<template>
    <section v-if="visibleRows.length" class="publication-outbox border-b border-bord-pr bg-bg-pr" aria-label="Your uploads">
        <div v-for="row in visibleRows" :key="row.key" class="publication-row px-4 py-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 text-par-s text-lab-pr">
                    <SvgIcon type="line" :name="kindOf(row) === 'chat' ? 'send-01' : 'upload-01'" classes="size-icon-small shrink-0" />
                    <span class="min-w-0 break-words">{{ row.descriptor.content || title(row) }}</span>
                </div>
                <p class="text-cap-l text-lab-sc mt-1" role="status">{{ statusLabel(row) }}</p>
                <progress v-if="['uploading', 'queued', 'waiting'].includes(row.status)" :value="row.progress || 0" max="100" class="publication-progress" aria-label="Upload progress" />
                <p v-if="row.error && ['failed', 'auth_required', 'expired'].includes(row.status)" class="text-cap-l text-red-900 break-words">{{ typeof row.error === 'string' ? row.error : row.error.message }}</p>
            </div>
            <button v-if="['failed', 'auth_required', 'expired', 'cancelled'].includes(row.status)" type="button" class="publication-action text-lab-pr" title="Retry upload" aria-label="Retry upload" :disabled="busy.has(row.key)" @click="act('retry', row)">
                <SvgIcon type="line" name="upload-01" classes="size-icon-small" />
            </button>
            <button type="button" class="publication-action text-lab-sc" title="Cancel upload" aria-label="Cancel upload" :disabled="busy.has(row.key) || row.status === 'cancelling'" @click="act('cancel', row)">
                <SvgIcon type="solid" name="x" classes="size-icon-small" />
            </button>
        </div>
        <p v-if="actionError" role="alert" class="px-4 pb-2 text-cap-l text-red-900">{{ actionError }}</p>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { publicationManager, publicationRows } from '@/kernel/services/media/publications/index.js';
const props = defineProps({ chatId: { type: [String, Number], default: null } });
const emit = defineEmits(['published']);
const kindOf = row => row.descriptor.kind;
const successMessage = row => ({
    story: __t('toast.story.story_published'),
    chat: __t('toast.chat.message_published'),
})[kindOf(row)] || __t('toast.post_published');
watch(publicationRows, (rows, previous) => {
    for (const row of rows) {
        if (row.status === 'published' && previous.some(old => old.key === row.key && old.status !== 'published')) {
            toastSuccess(successMessage(row));
            emit('published', row);
        }
    }
});
const busy = ref(new Set());
const actionError = ref('');
const visibleRows = computed(() => publicationRows.value.filter(row => row.status !== 'published' && !(row.status === 'cancelled' && (row.discard_requested || row.remote_only || row.native)) &&
    (props.chatId ? kindOf(row) === 'chat' && String(row.descriptor.chat_id) === String(props.chatId) : kindOf(row) !== 'chat')));
const title = row => ({ post: 'Post', story: 'Story', chat: 'Message' })[kindOf(row)] || 'Media';
const statusLabel = row => {
    const kind = kindOf(row);
    return ({
        queued: kind === 'story' ? 'Starting story upload' : 'Queued',
        uploading: `Uploading ${row.progress || 0}%`,
        processing: kind === 'story' ? 'Publishing story' : 'Publishing media',
        publishing: kind === 'story' ? 'Publishing story' : 'Publishing',
        waiting: 'Waiting to resume',
        feature_disabled: 'Waiting for media publishing to reopen',
        failed: 'Upload failed',
        auth_required: 'Sign in to resume',
        cancelling: 'Cancelling',
        cancelled: 'Cancelled on server',
        expired: row.remote_only ? 'Upload expired. Select media again.' : 'Upload expired. Original media saved.',
    })[row.status] || row.status;
};
async function act(action, row) {
    busy.value.add(row.key); actionError.value = '';
    try { await publicationManager[action](row); } catch (error) { actionError.value = error.message; }
    finally { busy.value.delete(row.key); }
}
</script>

<style scoped>
.publication-row { display: flex; align-items: center; gap: 8px; min-width: 0; }
.publication-action { width: 36px; height: 36px; flex: 0 0 36px; display: grid; place-items: center; }
.publication-action:disabled { opacity: .45; }
.publication-progress { display: block; width: 100%; height: 4px; margin-top: 8px; accent-color: #16a34a; }
</style>
