<template>
    <div v-if="adData.cta_enabled && adData.click_url" class="flex min-h-16 items-center justify-between gap-3 border-y border-bord-pr bg-fill-fv px-4 py-2">
        <p class="min-w-0 flex-1 line-clamp-2 text-sm font-semibold leading-5 text-lab-pr2">{{ adData.headline || adData.title }}</p>
        <a class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-900 px-4 text-sm font-bold text-white transition-opacity hover:opacity-90" v-bind:href="adData.click_url" target="_blank" rel="noopener" v-on:click.stop>
            <SvgIcon v-if="adData.cta_icon" v-bind:name="adData.cta_icon" v-bind:type="adData.cta_icon === 'whatsapp' ? 'social' : 'line'" classes="size-4"></SvgIcon>
            <span>{{ adData.cta_text }}</span>
        </a>
    </div>
</template>

<script setup>
import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';
defineProps({ adData: { type: Object, required: true } });
</script>
