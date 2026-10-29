<template>
    <article class="border-b border-bord-tr bg-bg-pr text-lab-pr2">
        <div class="flex items-center gap-3 px-4 pt-4">
            <RouterLink v-if="advertiser.username" v-bind:to="{ name: 'profile_index', params: { id: advertiser.username } }" class="flex min-w-0 flex-1 items-center gap-3">
                <img class="size-10 shrink-0 rounded-full object-cover" v-bind:src="advertiser.avatar_url" v-bind:alt="advertiser.name">
                <span class="min-w-0"><strong class="block truncate text-sm">{{ advertiser.name }}</strong><small class="text-lab-sc">Ad</small></span>
            </RouterLink>
            <div v-else class="flex min-w-0 flex-1 items-center gap-3">
                <img class="size-10 shrink-0 rounded-full object-cover" v-bind:src="advertiser.avatar_url" v-bind:alt="advertiser.name">
                <span class="min-w-0"><strong class="block truncate text-sm">{{ advertiser.name }}</strong><small class="text-lab-sc">Ad</small></span>
            </div>
            <AdDisclosureMenu v-bind:adData="adData" placement="feed" v-on:hide="$emit('hide')"></AdDisclosureMenu>
        </div>
        <div v-if="caption" class="px-4 py-3 text-sm leading-6">
            <p v-bind:class="expanded ? 'whitespace-pre-line' : 'line-clamp-3 whitespace-pre-line'">{{ caption }}</p>
            <button v-if="caption.length > 150" type="button" class="font-semibold text-lab-sc" v-on:click="expanded = !expanded">{{ expanded ? 'See less' : 'See more' }}</button>
        </div>
        <div v-if="mediaUrl" class="relative aspect-square w-full overflow-hidden bg-black">
            <img v-if="poster" v-bind:src="poster" alt="" class="pointer-events-none absolute inset-0 size-full scale-110 object-cover opacity-45 blur-2xl">
            <video v-if="adData.media_type === 'video' && adData.video_url" ref="videoRef" class="relative size-full object-contain" v-bind:src="adData.video_url" v-bind:poster="poster" muted playsinline controls preload="metadata"></video>
            <img v-else class="relative size-full object-contain" v-bind:src="mediaUrl" v-bind:alt="adData.headline || adData.title">
        </div>
        <AdFeedAttachment v-bind:adData="adData"></AdFeedAttachment>
        <div class="flex items-center gap-5 px-4 py-3 text-sm text-lab-sc">
            <button type="button" v-on:click="engage('like')" v-bind:class="{ 'text-brand-900': viewer.liked }" class="inline-flex items-center gap-1.5"><SvgIcon name="heart-rounded" type="line" classes="size-5"></SvgIcon><span>{{ counts.likes || 0 }}</span></button>
            <button type="button" v-on:click="commentsOpen = true" class="inline-flex items-center gap-1.5"><SvgIcon name="message-circle-02" type="line" classes="size-5"></SvgIcon><span>{{ counts.comments || 0 }}</span></button>
            <button type="button" v-on:click="shareAd" class="inline-flex items-center gap-1.5"><SvgIcon name="share-06" type="line" classes="size-5"></SvgIcon><span>{{ counts.shares || 0 }}</span></button>
            <button type="button" v-on:click="engage('save')" v-bind:class="{ 'text-brand-900': viewer.saved }" class="ml-auto"><SvgIcon name="bookmark" type="line" classes="size-5"></SvgIcon></button>
        </div>
        <AdCommentsDialog v-if="commentsOpen" v-bind:adData="adData" placement="feed" v-on:close="commentsOpen = false" v-on:updated="updateCounts"></AdCommentsDialog>
    </article>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';
import AdDisclosureMenu from './AdDisclosureMenu.vue';
import AdFeedAttachment from './AdFeedAttachment.vue';
import AdCommentsDialog from './AdCommentsDialog.vue';

const props = defineProps({ adData: { type: Object, required: true } });
defineEmits(['hide']);
const advertiser = computed(() => props.adData.advertiser || {});
const caption = computed(() => props.adData.primary_text || props.adData.content || '');
const mediaUrl = computed(() => props.adData.media_url || props.adData.preview_image_url);
const poster = computed(() => props.adData.thumbnail_url || props.adData.preview_image_url);
const counts = ref({ ...props.adData.engagement_counts });
const viewer = ref({ ...props.adData.viewer_engagement });
const expanded = ref(false);
const commentsOpen = ref(false);
const videoRef = ref(null);
let observer;

const updateCounts = (next) => { counts.value = { ...counts.value, ...next }; };
const engage = async (type) => {
    try {
        const response = await colibriAPI().ads().params({ placement: 'feed' }).with({
            type, delivery_id: props.adData.delivery_id
        }).sendTo('engage/' + props.adData.id);
        updateCounts(response.data.data.counts || {});
        viewer.value[type === 'like' ? 'liked' : 'saved'] = response.data.data.active;
    } catch (_) {}
};
const shareAd = async () => {
    try {
        if(navigator.share) await navigator.share({ url: window.location.href });
        else if(navigator.clipboard) await navigator.clipboard.writeText(window.location.href);
        await colibriAPI().ads().params({ placement: 'feed' }).with({
            event_type: 'share', delivery_id: props.adData.delivery_id
        }).sendTo('event/' + props.adData.id);
        counts.value.shares = Number(counts.value.shares || 0) + 1;
    } catch (_) {}
};
onMounted(() => {
    if(!videoRef.value || !window.IntersectionObserver) return;
    observer = new IntersectionObserver((entries) => {
        if(!videoRef.value) return;
        if(entries.some((entry) => entry.intersectionRatio >= 0.5)) videoRef.value.play().catch(() => {});
        else videoRef.value.pause();
    }, { threshold: [0, 0.5] });
    observer.observe(videoRef.value);
});
onUnmounted(() => observer?.disconnect());
</script>
