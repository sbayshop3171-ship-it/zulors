<template>
    <div ref="reelRoot" v-if="isPostCreative" class="relative h-full snap-start snap-always overflow-hidden bg-black text-white">
        <ReelItem
            v-bind:postData="adData.placement_creative?.source_post || adData.source_post"
            v-bind:active="active"
            isNear
            v-bind:distanceFromActive="0"
            v-bind:position="0"
            v-bind:feedSessionId="''"
            v-bind:sponsoredAdId="Number(adData.id)"
        ></ReelItem>
        <span class="pointer-events-none absolute left-4 top-16 z-40 rounded-full bg-black/55 px-2.5 py-1 text-cap-l font-semibold backdrop-blur">{{ $t('labels.ad') }} · Sponsored</span>
        <a v-if="adData.cta_enabled && adData.click_url" v-bind:href="adData.click_url" target="_blank" rel="noopener" class="absolute bottom-40 left-4 right-20 z-40 inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-par-s font-bold text-black">
            <SvgIcon v-if="adData.cta_icon" v-bind:name="adData.cta_icon" v-bind:type="adData.cta_icon === 'whatsapp' ? 'social' : 'line'" classes="size-4"></SvgIcon>{{ adData.cta_text }}
        </a>
    </div>
    <section ref="reelRoot" v-else class="relative h-full snap-start snap-always overflow-hidden bg-black text-white">
        <div class="absolute inset-0">
            <video
                ref="videoRef"
                v-if="mediaType === 'video' && videoUrl"
                class="size-full object-cover"
                v-bind:src="videoUrl"
                v-bind:poster="thumbnailUrl || adData.preview_image_url"
                preload="metadata"
                v-bind:autoplay="active"
                muted
                loop
                playsinline
                v-on:playing="handlePlaying"
                v-on:pause="handlePause"
                v-on:waiting="handlePause"
                v-on:timeupdate="handleTimeUpdate"
                v-on:ended="handleEnded"></video>
            <img v-else class="size-full object-cover" v-bind:src="mediaUrl || adData.preview_image_url" v-bind:alt="adData.title">
        </div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-1/2 bg-gradient-to-t from-black/85 via-black/35 to-transparent"></div>
        <div class="absolute left-4 right-20 bottom-16 z-20">
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
        <div class="absolute inset-x-4 bottom-2 z-30">
            <input v-model="commentDraft" v-on:keyup.enter="submitComment" class="w-full rounded-full border border-white/10 bg-black/65 px-4 py-2.5 text-par-s text-white outline-none placeholder:text-white/70" placeholder="Add comment..." aria-label="Add comment">
        </div>
        <div class="absolute right-3 bottom-20 z-20 flex flex-col items-center gap-3">
            <button type="button" v-on:click.stop="recordSocialEvent('like')" class="inline-flex flex-col items-center gap-1 text-white"><span v-bind:class="engagementState.viewer.liked ? 'text-red-400' : 'text-white'" class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="heart-rounded" type="line" classes="size-7"></SvgIcon></span><span class="text-cap-l font-semibold leading-none">{{ engagementState.counts.likes }}</span></button>
            <button type="button" v-on:click.stop="recordSocialEvent('comment')" class="inline-flex flex-col items-center gap-1 text-white"><span class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="message-circle-02" type="line" classes="size-7"></SvgIcon></span><span class="text-cap-l font-semibold leading-none">{{ engagementState.counts.comments }}</span></button>
            <button type="button" v-on:click.stop="recordSocialEvent('share')" class="inline-flex flex-col items-center gap-1 text-white"><span class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="share-06" type="line" classes="size-7"></SvgIcon></span><span class="text-cap-l font-semibold leading-none">{{ engagementState.counts.shares }}</span></button>
            <button type="button" v-on:click.stop="recordSocialEvent('save')" class="inline-flex flex-col items-center gap-1 text-white"><span v-bind:class="engagementState.viewer.saved ? 'text-amber-300' : 'text-white'" class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center"><SvgIcon name="bookmark" type="line" classes="size-7"></SvgIcon></span><span class="text-cap-l font-semibold leading-none">{{ engagementState.counts.saves }}</span></button>
        </div>
    </section>
</template>

<script>
import { computed, defineComponent, onMounted, onUnmounted, ref, watch } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import ReelItem from '@D/components/reels/ReelItem.vue';

export default defineComponent({
    components: {
        ReelItem
    },
    props: {
        adData: { type: Object, required: true },
        active: { type: Boolean, default: false }
    },
    setup(props) {
        const videoRef = ref(null);
        const reelRoot = ref(null);
        let watchTimer = null;
        let watchSeconds = 0;
        let lastWatchTick = 0;
        let didOpenReel = false;
        let didStartVideo = false;
        let isVideoPlaying = false;
        let sentThreeSecondView = false;
        let sentCompletedView = false;
        const commentDraft = ref('');
        const engagementState = ref({
            counts: { ...(props.adData.engagement_counts || { likes: 0, comments: 0, shares: 0, saves: 0 }) },
            viewer: { ...(props.adData.viewer_engagement || { liked: false, saved: false }) }
        });

        const sendEvent = (eventType, payload = {}) => colibriAPI().ads()
            .params({ placement: 'reels' })
            .with({ event_type: eventType, delivery_id: props.adData.delivery_id, ...payload })
            .sendTo(`event/${props.adData.id}`)
            .catch(() => {});

        const placementCreative = computed(() => props.adData.placement_creative || {});
        const isPostCreative = computed(() => placementCreative.value.kind === 'post'
            ? Boolean(placementCreative.value.source_post || props.adData.source_post)
            : ! placementCreative.value.kind && props.adData.source_type === 'post' && Boolean(props.adData.source_post));
        const mediaType = computed(() => placementCreative.value.media_type || props.adData.media_type);
        const videoUrl = computed(() => placementCreative.value.video_url || (mediaType.value === 'video' ? props.adData.video_url : null));
        const mediaUrl = computed(() => placementCreative.value.media_url || props.adData.media_url);
        const thumbnailUrl = computed(() => placementCreative.value.thumbnail_url || props.adData.thumbnail_url);

        const markViewable = () => {
            if(!props.active || !props.adData.delivery_id) return;
            const video = reelRoot.value?.querySelector('video');
            if(video && video.paused) {
                video.addEventListener('playing', () => window.setTimeout(markViewable, 2000), { once: true });
                return;
            }
            colibriAPI().ads().params({ placement: 'reels' }).with({
                visible_ratio: 0.5, visible_seconds: video ? 2 : 1, is_video: Boolean(video)
            }).sendTo(`deliveries/${props.adData.delivery_id}/viewable`).catch(() => {});
        };

        const activateReel = () => {
            if(! didOpenReel) {
                didOpenReel = true;
                sendEvent('reel_open');
            }

            if(! isPostCreative.value && videoRef.value) {
                videoRef.value.play().catch(() => {});
            }
            window.setTimeout(markViewable, 1000);
            lastWatchTick = Date.now();
            if(! isPostCreative.value && ! watchTimer) {
                watchTimer = window.setInterval(() => {
                    if(isVideoPlaying && videoRef.value && ! videoRef.value.paused) {
                        const now = Date.now();
                        const watchDelta = Math.max(0, Math.round(((now - (lastWatchTick || now)) / 1000) * 10) / 10);
                        lastWatchTick = now;
                        watchSeconds += watchDelta;
                        if(watchDelta > 0) sendEvent('video_watch', { watch_time_seconds: watchDelta });
                    } else {
                        lastWatchTick = 0;
                    }
                }, 3000);
            }
        };

        const deactivateReel = () => {
            if(videoRef.value && ! isPostCreative.value) {
                videoRef.value.pause();
            }
            isVideoPlaying = false;
            lastWatchTick = 0;
            if(watchTimer) {
                window.clearInterval(watchTimer);
                watchTimer = null;
            }
        };

        const handlePlaying = () => {
            isVideoPlaying = true;
            if(! didStartVideo) {
                didStartVideo = true;
                sendEvent('video_start');
            }
        };

        const handlePause = () => { isVideoPlaying = false; };

        const recordSocialEvent = (eventType) => {
            if (eventType === 'like' || eventType === 'save') {
                return colibriAPI().ads().with({ type: eventType }).sendTo(`engage/${props.adData.id}`).then((response) => {
                    engagementState.value.counts = { ...engagementState.value.counts, ...response.data.data.counts };
                    engagementState.value.viewer[eventType === 'like' ? 'liked' : 'saved'] = response.data.data.active;
                }).catch(() => {});
            }
            if (eventType === 'comment') {
                return submitComment();
            }
            return sendEvent(eventType);
        };

        const submitComment = () => {
            const content = commentDraft.value.trim();
            if(! content) return Promise.resolve();
            return colibriAPI().ads().with({ type: 'comment', content }).sendTo(`engage/${props.adData.id}`).then((response) => {
                engagementState.value.counts = { ...engagementState.value.counts, ...response.data.data.counts };
                commentDraft.value = '';
            }).catch(() => {});
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

        watch(() => props.active, (isActive) => {
            if(isActive) activateReel();
            else deactivateReel();
        }, { immediate: true });
        onMounted(() => { if(props.active) activateReel(); });
        onUnmounted(() => deactivateReel());

        return { videoRef, reelRoot, active: computed(() => props.active), isPostCreative, mediaType, videoUrl, mediaUrl, thumbnailUrl, handlePlaying, handlePause, handleTimeUpdate, handleEnded, recordSocialEvent, commentDraft, submitComment, engagementState };
    }
});
</script>
