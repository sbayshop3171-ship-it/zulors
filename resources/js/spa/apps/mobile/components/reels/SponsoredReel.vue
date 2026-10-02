<template>
    <div v-if="adData.source_type === 'post' && adData.source_post" class="relative h-full snap-start snap-always overflow-hidden bg-black text-white">
        <ReelItem
            v-bind:postData="adData.source_post"
            active
            isNear
            v-bind:distanceFromActive="0"
            v-bind:position="0"
            v-bind:feedSessionId="''"
        ></ReelItem>
        <span class="pointer-events-none absolute left-4 top-16 z-40 rounded-full bg-black/55 px-2.5 py-1 text-cap-l font-semibold backdrop-blur">{{ $t('labels.ad') }} · Sponsored</span>
        <a v-if="adData.cta_enabled && adData.click_url" v-bind:href="adData.click_url" target="_blank" rel="noopener" class="absolute bottom-5 left-4 right-20 z-40 inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-par-s font-bold text-black">
            <SvgIcon v-if="adData.cta_icon" v-bind:name="adData.cta_icon" v-bind:type="adData.cta_icon === 'whatsapp' ? 'social' : 'line'" classes="size-4"></SvgIcon>{{ adData.cta_text }}
        </a>
    </div>
    <section v-else class="relative h-full snap-start snap-always overflow-hidden bg-black text-white">
        <div class="absolute inset-0">
            <video
                ref="videoRef"
                v-if="adData.media_type === 'video' && adData.video_url"
                class="size-full object-cover"
                v-bind:src="adData.video_url"
                v-bind:poster="adData.thumbnail_url || adData.preview_image_url"
                preload="metadata"
                autoplay
                muted
                loop
                playsinline
                v-on:timeupdate="handleTimeUpdate"
                v-on:ended="handleEnded"></video>
            <img v-else class="size-full object-cover" v-bind:src="adData.preview_image_url" v-bind:alt="adData.title">
        </div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-1/2 bg-gradient-to-t from-black/85 via-black/35 to-transparent"></div>
        <div class="absolute left-4 right-20 bottom-5 z-20">
            <span class="mb-2 inline-flex rounded-full bg-black/45 px-2.5 py-1 text-cap-l font-semibold backdrop-blur">{{ $t('labels.ad') }} · Sponsored</span>
            <div class="flex items-center gap-2">
                <img v-if="adData.advertiser?.avatar_url" class="size-8 rounded-full object-cover" v-bind:src="adData.advertiser.avatar_url" v-bind:alt="adData.advertiser.name">
                <strong class="block text-par-m font-bold">{{ adData.advertiser?.name }}</strong>
            </div>
            <p class="mt-2 line-clamp-3 text-par-s leading-5 text-white/95">{{ adData.primary_text || adData.content || adData.title }}</p>
            <a v-if="adData.cta_enabled && adData.click_url" v-bind:href="adData.click_url" target="_blank" rel="noopener" class="pointer-events-auto mt-3 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-par-s font-bold text-black">
                <SvgIcon v-if="adData.cta_icon" v-bind:name="adData.cta_icon" v-bind:type="adData.cta_icon === 'whatsapp' ? 'social' : 'line'" classes="size-4"></SvgIcon>{{ adData.cta_text }}
            </a>
        </div>
        <div class="absolute right-3 bottom-20 z-20 flex flex-col items-center gap-4">
            <button type="button" v-on:click.stop="recordSocialEvent('like')" class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="heart-rounded" type="line" classes="size-7"></SvgIcon></button>
            <button type="button" v-on:click.stop="recordSocialEvent('comment')" class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="message-circle-02" type="line" classes="size-7"></SvgIcon></button>
            <button type="button" v-on:click.stop="recordSocialEvent('share')" class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="share-06" type="line" classes="size-7"></SvgIcon></button>
            <button type="button" v-on:click.stop="recordSocialEvent('save')" class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="bookmark" type="line" classes="size-7"></SvgIcon></button>
        </div>
    </section>
</template>

<script>
import { defineComponent, onMounted, onUnmounted, ref } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import ReelItem from '@M/components/reels/ReelItem.vue';

export default defineComponent({
    components: {
        ReelItem
    },
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

        const recordSocialEvent = (eventType) => {
            if (eventType === 'like' || eventType === 'save') {
                return colibriAPI().ads().with({ type: eventType }).sendTo(`engage/${props.adData.id}`).catch(() => {});
            }
            if (eventType === 'comment') {
                const content = window.prompt('Write a comment');
                if (content && content.trim()) {
                    return colibriAPI().ads().with({ type: 'comment', content: content.trim() }).sendTo(`engage/${props.adData.id}`).catch(() => {});
                }
                return Promise.resolve();
            }
            return sendEvent(eventType);
        };

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
                if(videoRef.value && ! videoRef.value.paused) {
                    sendEvent('video_watch', { watch_time_seconds: watchSeconds });
                }
            }, 3000);
        });
        onUnmounted(() => window.clearInterval(watchTimer));

        return { videoRef, handleTimeUpdate, handleEnded, recordSocialEvent };
    }
});
</script>
