<template>
    <div ref="adRoot">
        <TimelinePublication
            v-if="adData.source_type === 'post' && adData.source_post"
            v-bind:postData="adData.source_post"
            v-bind:sponsoredAdData="adData"
            source="sponsored_ad"
            feedType="for_you"
            v-bind:sponsoredAdId="Number(adData.id)"
            v-on:hide-ad="$emit('hide')"
        >
            <template #sponsored-cta>
                <AdFeedAttachment v-bind:adData="adData"></AdFeedAttachment>
            </template>
        </TimelinePublication>
        <AdFeedCreative v-else v-bind:adData="adData" v-on:hide="$emit('hide')"></AdFeedCreative>
    </div>
</template>

<script>
import { defineComponent, onMounted, onUnmounted, ref } from 'vue';
import TimelinePublication from '@D/components/timeline/feed/TimelinePublication.vue';
import AdFeedAttachment from '@/kernel/vue/components/ads/AdFeedAttachment.vue';
import AdFeedCreative from '@/kernel/vue/components/ads/AdFeedCreative.vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

export default defineComponent({
    emits: ['hide'],
    props: {
        adData: { type: Object, required: true }
    },
    components: {
        TimelinePublication,
        AdFeedAttachment,
        AdFeedCreative
    },
    setup(props) {
        const adRoot = ref(null);
        let observer = null;
        let timer = null;
        let sent = false;
        let visibleNow = false;
        const sendViewable = () => {
            if(sent || !props.adData.delivery_id) return;
            const video = adRoot.value?.querySelector('video');
            if(video && video.paused) {
                video.addEventListener('playing', () => {
                    if(visibleNow) timer = window.setTimeout(sendViewable, 2000);
                }, { once: true });
                return;
            }
            sent = true;
            colibriAPI().ads().params({ placement: 'feed' }).with({ visible_ratio: 0.5, visible_seconds: video ? 2 : 1, is_video: Boolean(video) }).sendTo(`deliveries/${props.adData.delivery_id}/viewable`).catch(() => { sent = false; });
        };
        onMounted(() => {
            if(!adRoot.value || !window.IntersectionObserver) return;
            observer = new IntersectionObserver(entries => {
                const visible = entries.some(entry => entry.isIntersecting && entry.intersectionRatio >= 0.5);
                visibleNow = visible;
                window.clearTimeout(timer);
                if(visible) timer = window.setTimeout(sendViewable, props.adData.media_type === 'video' ? 2000 : 1000);
            }, { threshold: [0.5] });
            observer.observe(adRoot.value);
        });
        onUnmounted(() => { window.clearTimeout(timer); observer?.disconnect(); });
        return { adRoot };
    }
});
</script>
