<template>
    <article class="base-publication border-b border-b-bord-tr">
        <div class="px-4 pt-4">
            <div class="mb-3 flex items-center gap-3">
                <img class="size-10 rounded-full object-cover" v-bind:src="adData.advertiser?.avatar_url" v-bind:alt="adData.advertiser?.name">
                <div class="min-w-0">
                    <strong class="block truncate text-par-s font-bold text-lab-pr2">{{ adData.advertiser?.name }}</strong>
                    <span class="block text-cap-l font-semibold text-lab-sc">{{ $t('labels.ad') }}</span>
                </div>
            </div>
            <p v-if="adData.content" class="mb-3 whitespace-pre-line text-par-s leading-relaxed text-lab-pr2">{{ adData.content }}</p>
            <a v-bind:href="adData.click_url || adData.target_url" target="_blank" rel="noopener" class="block overflow-hidden rounded-2xl bg-fill-fv">
                <video v-if="adData.media_type === 'video' && adData.video_url" class="aspect-video w-full object-cover" v-bind:src="adData.video_url" v-bind:poster="adData.thumbnail_url" muted playsinline controls></video>
                <img v-else class="aspect-video w-full object-cover" v-bind:src="adData.preview_image_url" v-bind:alt="adData.title">
            </a>
            <div class="py-3">
                <h3 class="text-par-m font-bold text-lab-pr2">{{ adData.title }}</h3>
                <a v-if="adData.cta_type !== 'NO_BUTTON'" v-bind:href="adData.click_url || adData.target_url" target="_blank" rel="noopener" class="mt-3 block rounded-xl bg-lab-pr2 px-4 py-2.5 text-center text-par-s font-bold text-bg-pr">
                    {{ adData.cta_text }}
                </a>
            </div>
        </div>
    </article>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
    props: {
        adData: { type: Object, required: true }
    }
});
</script>