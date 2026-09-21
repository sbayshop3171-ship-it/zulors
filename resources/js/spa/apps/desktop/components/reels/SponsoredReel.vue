<template>
    <section class="relative h-full snap-start snap-always overflow-hidden bg-black text-white">
        <a v-bind:href="adData.click_url || adData.target_url" target="_blank" rel="noopener" class="absolute inset-0">
            <video v-if="adData.media_type === 'video' && adData.video_url" ref="videoRef" class="size-full object-cover" v-bind:src="adData.video_url" v-bind:poster="adData.thumbnail_url || adData.preview_image_url" preload="metadata" autoplay muted playsinline v-on:timeupdate="handleTimeUpdate" v-on:ended="handleEnded"></video>
            <img v-else class="size-full object-cover" v-bind:src="adData.preview_image_url" v-bind:alt="adData.title">
        </a>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-1/2 bg-gradient-to-t from-black/85 via-black/35 to-transparent"></div>
        <div class="absolute left-4 right-20 bottom-5 z-20">
            <span class="mb-2 inline-block rounded bg-black/40 px-2 py-1 text-cap-l font-semibold">{{ $t('labels.ad') }}</span>
            <strong class="block text-par-m font-bold">{{ adData.advertiser?.name }}</strong>
            <p class="mt-2 line-clamp-3 text-par-s leading-5 text-white/95">{{ adData.content || adData.title }}</p>
            <a v-if="adData.cta_type !== 'NO_BUTTON'" v-bind:href="adData.click_url || adData.target_url" target="_blank" rel="noopener" class="pointer-events-auto mt-3 inline-flex rounded-xl bg-white px-4 py-2.5 text-par-s font-bold text-black">{{ adData.cta_text }}</a>
        </div>
        <div class="absolute right-3 bottom-20 z-20 flex flex-col items-center gap-4">
            <span class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="heart-rounded" type="line" classes="size-7"></SvgIcon></span>
            <span class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="message-circle-02" type="line" classes="size-7"></SvgIcon></span>
            <span class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="share-06" type="line" classes="size-7"></SvgIcon></span>
        </div>
    </section>
</template>

<script>
import { defineComponent, onMounted, onUnmounted, ref } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

export default defineComponent({
    props: {
        adData: { type: Object, required: true }
    },
    setup(props) {
        const videoRef = ref(null);
        let watchTimer = null;
        let watchSeconds = 0;
        let sentThreeSecondView = false;
        let sentCompletedView = false;
        const sendEvent = (eventType, payload = {}) => colibriAPI().ads()
            .params({ placement: 'reels' })
            .with({ event_type: eventType, ...payload })
            .sendTo(`event/${props.adData.id}`)
            .catch(() => {});
        const handleTimeUpdate = (event) => {
            const currentTime = Number(event.target.currentTime || 0);
            const duration = Number(event.target.duration || 0);
            watchSeconds = Math.max(watchSeconds, currentTime);
            if(watchSeconds >= 3 && ! sentThreeSecondView) {
                sentThreeSecondView = true;
                sendEvent('three_second_view', { watch_time_seconds: watchSeconds });
            }
            if(duration > 0 && currentTime / duration >= 0.95) {
                handleEnded();
            }
        };
        const handleEnded = () => {
            if(! sentCompletedView) {
                sentCompletedView = true;
                sendEvent('completed_view', { watch_time_seconds: watchSeconds, completion_rate: 1 });
            }
        };
        onMounted(() => {
            sendEvent('impression');
            watchTimer = window.setInterval(() => {
                if(videoRef.value && ! videoRef.value.paused) sendEvent('video_watch', { watch_time_seconds: watchSeconds });
            }, 3000);
        });
        onUnmounted(() => window.clearInterval(watchTimer));
        return { videoRef, handleTimeUpdate, handleEnded };
    }
});
</script>