<template>
    <div v-if="isOpen" class="fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-6">
        <button type="button" class="absolute inset-0 bg-black/50" aria-label="Close forward dialog" v-on:click="$emit('close')"></button>

        <section class="relative flex max-h-[min(680px,90dvh)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-bord-card bg-bg-pr shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="forward-message-title">
            <header class="flex items-center justify-between border-b border-bord-card px-5 py-4">
                <div class="min-w-0">
                    <h2 id="forward-message-title" class="text-par-l font-semibold text-lab-pr">{{ $t('chat.forward_message') }}</h2>
                    <p class="mt-1 truncate text-par-s text-lab-sc">
                        {{ $t('chat.forwarded_from', { name: sourceAuthor }) }}
                    </p>
                </div>
                <button type="button" class="ml-4 inline-flex size-10 shrink-0 items-center justify-center rounded-full text-lab-sc focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-900" aria-label="Close" v-on:click="$emit('close')">×</button>
            </header>

            <div class="border-b border-bord-card p-4">
                <input v-model.trim="searchQuery" type="search" class="h-11 w-full rounded-xl border border-bord-card bg-bg-sc px-4 text-par-m text-lab-pr outline-none placeholder:text-lab-sc focus:border-brand-900" v-bind:placeholder="$t('chat.search_conversations')">
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-2" role="listbox" aria-label="Choose a conversation">
                <button
                    v-for="chat in filteredChats"
                    v-bind:key="chat.chat_id"
                    type="button"
                    role="option"
                    v-bind:aria-selected="selectedChatId === chat.chat_id"
                    class="flex min-h-14 w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-900"
                    v-bind:class="selectedChatId === chat.chat_id ? 'bg-brand-900/10' : 'hover:bg-fill-qt'"
                    v-on:click="selectedChatId = chat.chat_id">
                    <img v-if="chat.chat_info?.avatar_url" v-bind:src="chat.chat_info.avatar_url" alt="" class="size-10 shrink-0 rounded-full object-cover">
                    <span v-else class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fill-qt text-par-m font-semibold text-lab-sc">{{ initials(chat.chat_info?.name) }}</span>
                    <span class="min-w-0 flex-1 truncate text-par-m font-medium text-lab-pr">{{ chat.chat_info?.name || 'Conversation' }}</span>
                    <span v-if="selectedChatId === chat.chat_id" class="text-brand-900" aria-hidden="true">✓</span>
                </button>

                <p v-if="! filteredChats.length" class="px-4 py-10 text-center text-par-m text-lab-sc">{{ $t('chat.no_conversations') }}</p>
            </div>

            <footer class="flex justify-end gap-3 border-t border-bord-card px-4 py-3 sm:px-5">
                <button type="button" class="min-h-11 rounded-xl px-4 text-par-m font-medium text-lab-pr2 hover:bg-fill-qt focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-900" v-on:click="$emit('close')">{{ $t('labels.cancel') }}</button>
                <button type="button" class="min-h-11 rounded-xl bg-brand-900 px-5 text-par-m font-semibold text-white transition-opacity hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-900 disabled:cursor-not-allowed disabled:opacity-50" v-bind:disabled="! selectedChatId || isSubmitting" v-on:click="$emit('confirm', selectedChatId)">
                    {{ isSubmitting ? $t('chat.sending_message') : $t('chat.forward') }}
                </button>
            </footer>
        </section>
    </div>
</template>

<script>
import { computed, defineComponent, ref, watch } from 'vue';

export default defineComponent({
    props: {
        isOpen: { type: Boolean, default: false },
        chats: { type: Array, default: () => [] },
        sourceMessage: { type: Object, default: null },
        isSubmitting: { type: Boolean, default: false },
    },
    emits: ['close', 'confirm'],
    setup(props) {
        const searchQuery = ref('');
        const selectedChatId = ref(null);

        watch(() => props.isOpen, (isOpen) => {
            if(isOpen) {
                searchQuery.value = '';
                selectedChatId.value = null;
            }
        });

        return {
            searchQuery,
            selectedChatId,
            sourceAuthor: computed(() => props.sourceMessage?.relations?.user?.name || props.sourceMessage?.forwarded_from?.user?.name || 'Unknown'),
            filteredChats: computed(() => {
                const query = searchQuery.value.toLocaleLowerCase();

                return props.chats.filter((chat) => {
                    return String(chat.chat_info?.name || '').toLocaleLowerCase().includes(query);
                });
            }),
            initials: (name = '') => String(name).trim().split(/\s+/).slice(0, 2).map(part => part[0] || '').join('').toUpperCase() || 'C',
        };
    },
});
</script>
