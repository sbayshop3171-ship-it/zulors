<template>
    <div class="relative inline-flex" v-on:click.stop>
        <button type="button" class="inline-flex size-8 items-center justify-center rounded-full opacity-80 hover:opacity-100" aria-label="Ad options" v-on:click="open = !open">
            <span aria-hidden="true" class="text-xl leading-none">⋯</span>
        </button>
        <div v-if="open" v-bind:class="placement === 'feed' ? 'right-0 top-full mt-2' : 'bottom-full left-0 mb-2'" class="absolute z-50 w-56 rounded-xl border border-white/15 bg-[#171a22] p-2 text-left text-sm text-white shadow-2xl">
            <button type="button" class="w-full rounded-lg px-3 py-2 text-left hover:bg-white/10" v-on:click="showReason = !showReason">Why this ad?</button>
            <p v-if="showReason" class="px-3 pb-2 text-xs leading-5 text-white/70">{{ reasonText }}</p>
            <button type="button" class="w-full rounded-lg px-3 py-2 text-left hover:bg-white/10 disabled:opacity-50" v-bind:disabled="hiding" v-on:click="hideAd">Hide ad</button>
            <p v-if="error" class="px-3 pt-1 text-xs text-red-300">{{ error }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const props = defineProps({
    adData: { type: Object, required: true },
    placement: { type: String, required: true }
});
const emit = defineEmits(['hide']);
const open = ref(false);
const showReason = ref(false);
const hiding = ref(false);
const error = ref('');
const reasonText = computed(() => {
    const reasons = props.adData.why_this_ad || [];
    if(reasons.includes('interest_match')) return 'This ad may match topics you are interested in.';
    if(reasons.includes('context_match')) return 'This ad may relate to content you are viewing.';
    return 'This advertiser is reaching people on Zulors.';
});

const hideAd = async () => {
    if(hiding.value) return;
    hiding.value = true;
    error.value = '';
    try {
        await colibriAPI().ads().params({ placement: props.placement }).with({
            event_type: 'hide', delivery_id: props.adData.delivery_id
        }).sendTo(`event/${props.adData.id}`);
        open.value = false;
        emit('hide');
    } catch (_) {
        error.value = 'Could not hide this ad. Please try again.';
    } finally {
        hiding.value = false;
    }
};
</script>
