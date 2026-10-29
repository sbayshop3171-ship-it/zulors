<template>
    <div class="fixed inset-0 z-[950] flex items-end justify-center bg-black/65 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Ad comments" v-on:click.self="$emit('close')" v-on:touchmove.stop>
        <div class="flex max-h-[min(80dvh,640px)] w-full max-w-lg flex-col rounded-t-2xl bg-white text-black shadow-2xl sm:rounded-2xl">
            <div class="flex items-center justify-between border-b border-black/10 px-4 py-3">
                <strong>Comments</strong>
                <button type="button" class="size-8 rounded-full text-xl" aria-label="Close comments" v-on:click="$emit('close')">×</button>
            </div>
            <div class="min-h-24 flex-1 space-y-3 overflow-y-auto px-4 py-4">
                <p v-if="loading" class="text-sm text-black/60">Loading comments...</p>
                <p v-else-if="!comments.length" class="text-sm text-black/60">No comments yet.</p>
                <div v-for="comment in comments" v-bind:key="comment.id" class="flex gap-2">
                    <img v-if="comment.user?.avatar_url" class="size-8 rounded-full object-cover" v-bind:src="comment.user.avatar_url" alt="">
                    <div class="min-w-0 rounded-xl bg-black/5 px-3 py-2 text-sm">
                        <strong>{{ comment.user?.name || comment.user?.username || 'User' }}</strong>
                        <p class="break-words">{{ comment.content }}</p>
                    </div>
                </div>
            </div>
            <form class="flex gap-2 border-t border-black/10 p-3" v-on:submit.prevent="submit">
                <input v-model="draft" class="min-w-0 flex-1 rounded-xl border border-black/15 px-3 py-2 text-sm outline-none focus:border-brand-900" maxlength="2000" placeholder="Add a comment..." aria-label="Add a comment">
                <button type="submit" class="rounded-xl bg-brand-900 px-4 text-sm font-bold text-white disabled:opacity-50" v-bind:disabled="submitting || !draft.trim()">Post</button>
            </form>
            <p v-if="error" class="px-4 pb-3 text-xs text-red-600">{{ error }}</p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const props = defineProps({
    adData: { type: Object, required: true },
    placement: { type: String, required: true }
});
const emit = defineEmits(['close', 'updated']);
const comments = ref([]);
const draft = ref('');
const loading = ref(true);
const submitting = ref(false);
const error = ref('');

const load = async () => {
    try {
        const response = await colibriAPI().ads().getFrom('engagements/' + props.adData.id);
        comments.value = response.data.data.comments || [];
        emit('updated', response.data.data.counts || {});
    } catch (_) {
        error.value = 'Comments could not be loaded.';
    } finally {
        loading.value = false;
    }
};
const submit = async () => {
    const content = draft.value.trim();
    if(!content || submitting.value) return;
    submitting.value = true;
    error.value = '';
    try {
        await colibriAPI().ads().params({ placement: props.placement }).with({
            type: 'comment', content, delivery_id: props.adData.delivery_id
        }).sendTo('engage/' + props.adData.id);
        draft.value = '';
        await load();
    } catch (_) {
        error.value = 'Comment could not be posted.';
    } finally {
        submitting.value = false;
    }
};
onMounted(load);
</script>
