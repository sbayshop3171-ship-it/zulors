<template>
    <div v-if="adData.source_type === 'post' && adData.source_post" class="base-publication min-h-40 border-b border-b-bord-tr">
        <div class="flex items-center justify-between px-4 pt-3">
            <span class="inline-flex items-center rounded-full bg-fill-fv px-2.5 py-1 text-cap-l font-bold text-lab-sc">{{ $t('labels.ad') }}</span>
            <span class="text-cap-l text-lab-sc">Sponsored</span>
        </div>
        <TimelinePublication
            v-bind:postData="adData.source_post"
            source="sponsored_ad"
            feedType="for_you"
        >
            <template #sponsored-cta>
                <div v-if="adData.cta_enabled && adData.click_url" class="mx-4 mb-3 flex items-center justify-between gap-3 rounded-lg border border-bord-pr bg-fill-fv px-3 py-2">
                    <p class="min-w-0 flex-1 truncate text-par-s font-semibold text-lab-pr2">{{ adData.headline || adData.title }}</p>
                    <a v-bind:href="adData.click_url" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-lab-pr2 px-3 py-1.5 text-cap-l font-bold text-bg-pr">
                        <SvgIcon v-if="adData.cta_icon" v-bind:name="adData.cta_icon" v-bind:type="adData.cta_icon === 'whatsapp' ? 'social' : 'line'" classes="size-4"></SvgIcon>{{ adData.cta_text }}
                    </a>
                </div>
            </template>
        </TimelinePublication>
    </div>
    <article v-else class="base-publication min-h-40 border-b border-b-bord-tr">
        <div class="px-4 pt-4">
            <div class="mb-3 flex items-center gap-3">
                <img class="size-10 rounded-full object-cover" v-bind:src="adData.advertiser?.avatar_url" v-bind:alt="adData.advertiser?.name">
                <div class="min-w-0">
                    <strong class="block truncate text-par-s font-bold text-lab-pr2">{{ adData.advertiser?.name }}</strong>
                    <span class="block text-cap-l font-semibold text-lab-sc">{{ $t('labels.ad') }} · Sponsored</span>
                </div>
            </div>
            <p v-if="adData.content" class="mb-3 whitespace-pre-line text-par-s leading-relaxed text-lab-pr2">{{ adData.content }}</p>
            <component
                v-bind:is="adData.click_url ? 'a' : 'div'"
                v-bind:href="adData.click_url || undefined"
                v-bind:target="adData.click_url ? '_blank' : undefined"
                v-bind:rel="adData.click_url ? 'noopener' : undefined"
                class="block overflow-hidden rounded-xl bg-fill-fv"
            >
                <video v-if="adData.media_type === 'video' && adData.video_url" class="aspect-video w-full object-cover" v-bind:src="adData.video_url" v-bind:poster="adData.thumbnail_url" muted playsinline controls></video>
                <img v-else class="aspect-video w-full object-cover" v-bind:src="adData.preview_image_url" v-bind:alt="adData.title">
            </component>
            <div class="mx-0 my-3 flex items-center justify-between gap-3 rounded-lg border border-bord-pr bg-fill-fv px-3 py-2">
                <h3 class="min-w-0 flex-1 truncate text-par-s font-semibold text-lab-pr2">{{ adData.headline || adData.title }}</h3>
                <a v-if="adData.cta_enabled && adData.click_url" v-bind:href="adData.click_url" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-lab-pr2 px-3 py-1.5 text-cap-l font-bold text-bg-pr">
                    <SvgIcon v-if="adData.cta_icon" v-bind:name="adData.cta_icon" v-bind:type="adData.cta_icon === 'whatsapp' ? 'social' : 'line'" classes="size-4"></SvgIcon>{{ adData.cta_text }}
                </a>
            </div>
        </div>
    </article>
</template>

<script>
import { defineComponent } from 'vue';
import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';

export default defineComponent({
    props: {
        adData: { type: Object, required: true }
    },
    components: {
        TimelinePublication,
        SvgIcon
    }
});
</script>
