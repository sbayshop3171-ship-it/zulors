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
            v-bind:sponsoredAd="adData"
            v-on:hide-ad="$emit('hide')"
        ></ReelItem>
    </div>
    <section ref="reelRoot" v-else class="relative h-full snap-start snap-always overflow-hidden bg-black text-white">
        <img v-if="thumbnailUrl" v-bind:src="thumbnailUrl" alt="" class="pointer-events-none absolute inset-0 size-full scale-110 object-cover opacity-35 blur-2xl">
        <video
            ref="videoRef"
            v-if="mediaType === 'video' && videoUrl"
            class="absolute inset-0 size-full object-contain"
            v-on:click.stop="togglePlayback"
            v-bind:src="videoUrl"
            v-bind:poster="thumbnailUrl || adData.preview_image_url"
            v-bind:preload="active ? 'auto' : 'metadata'"
            v-bind:autoplay="active"
            v-bind:muted="isMuted"
            loop playsinline
            v-on:playing="handlePlaying"
            v-on:pause="handlePause"
            v-on:waiting="handleBuffering"
            v-on:timeupdate="handleTimeUpdate"
            v-on:ended="handleEnded"></video>
        <img v-else class="absolute inset-0 size-full object-contain" v-bind:src="mediaUrl || adData.preview_image_url" v-bind:alt="adData.title">
        <AdReelOverlay v-bind:adData="adData" v-on:hide="$emit('hide')"></AdReelOverlay>
        <div v-if="isBuffering" class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center" aria-label="Video buffering"><span class="size-9 animate-spin rounded-full border-2 border-white/30 border-t-white"></span></div>
        <button v-if="playbackPaused" type="button" class="absolute inset-0 z-20 flex items-center justify-center bg-black/10" aria-label="Play video" v-on:click.stop="togglePlayback"><span class="flex size-14 items-center justify-center rounded-full bg-black/55 text-white"><SvgIcon name="play" type="solid" classes="ml-1 size-7"></SvgIcon></span></button>
        <button v-if="mediaType === 'video'" type="button" class="absolute right-3 top-28 z-40 inline-flex size-10 items-center justify-center rounded-full bg-black/50 text-white" v-on:click.stop="toggleMute" v-bind:aria-label="isMuted ? 'Unmute' : 'Mute'"><SvgIcon v-bind:name="isMuted ? 'volume-x' : 'volume-max'" type="line" classes="size-5"></SvgIcon></button>
        <div class="absolute right-3 bottom-24 z-20 flex flex-col items-center gap-3">
            <button type="button" v-on:click.stop="recordSocialEvent('like')" class="flex flex-col items-center gap-1"><SvgIcon name="heart-rounded" type="line" classes="size-7"></SvgIcon><span class="text-xs">{{ engagementState.counts.likes }}</span></button>
            <button type="button" v-on:click.stop="recordSocialEvent('comment')" class="flex flex-col items-center gap-1"><SvgIcon name="message-circle-02" type="line" classes="size-7"></SvgIcon><span class="text-xs">{{ engagementState.counts.comments }}</span></button>
            <button type="button" v-on:click.stop="recordSocialEvent('share')" class="flex flex-col items-center gap-1"><SvgIcon name="share-06" type="line" classes="size-7"></SvgIcon><span class="text-xs">{{ engagementState.counts.shares }}</span></button>
            <button type="button" v-on:click.stop="recordSocialEvent('save')" class="flex flex-col items-center gap-1"><SvgIcon name="bookmark" type="line" classes="size-7"></SvgIcon><span class="text-xs">{{ engagementState.counts.saves }}</span></button>
        </div>
        <AdCommentsDialog v-if="commentsOpen" v-bind:adData="adData" placement="reels" v-on:close="commentsOpen = false" v-on:updated="updateCounts"></AdCommentsDialog>
    </section>
</template>

<script>
import { computed, defineComponent, onMounted, onUnmounted, ref, watch } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import ReelItem from '@M/components/reels/ReelItem.vue';
import AdReelOverlay from '@/kernel/vue/components/ads/AdReelOverlay.vue';
import AdCommentsDialog from '@/kernel/vue/components/ads/AdCommentsDialog.vue';
import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';

export default defineComponent({
    components: {
        ReelItem, AdReelOverlay, AdCommentsDialog, SvgIcon
    },
    emits: ['hide'],
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
        const commentsOpen = ref(false);
        const isMuted = ref(true);
        const playbackPaused = ref(false);
        const isBuffering = ref(false);
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
            playbackPaused.value = false;
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
            playbackPaused.value = false;
            isBuffering.value = false;
            if(! didStartVideo) {
                didStartVideo = true;
                sendEvent('video_start');
            }
        };

        const handlePause = () => {
            isVideoPlaying = false;
            if(props.active) playbackPaused.value = true;
            isBuffering.value = false;
        };
        const handleBuffering = () => { isVideoPlaying = false; isBuffering.value = true; };
        const togglePlayback = () => {
            const video = videoRef.value;
            if(!video) return;
            if(video.paused) {
                playbackPaused.value = false;
                video.play().catch(() => { playbackPaused.value = true; });
            } else {
                playbackPaused.value = true;
                video.pause();
            }
        };

        const recordSocialEvent = (eventType) => {
            if (eventType === 'like' || eventType === 'save') {
                return colibriAPI().ads().params({ placement: 'reels' }).with({ type: eventType, delivery_id: props.adData.delivery_id }).sendTo(`engage/${props.adData.id}`).then((response) => {
                    engagementState.value.counts = { ...engagementState.value.counts, ...response.data.data.counts };
                    engagementState.value.viewer[eventType === 'like' ? 'liked' : 'saved'] = response.data.data.active;
                }).catch(() => {});
            }
            if (eventType === 'comment') {
                commentsOpen.value = true;
                return;
            }
            if (eventType === 'share') {
                const link = window.location.href;
                const share = navigator.share
                    ? navigator.share({ url: link })
                    : navigator.clipboard?.writeText(link);
                if(!share) return;
                return Promise.resolve(share).then(() => {
                    engagementState.value.counts.shares = Number(engagementState.value.counts.shares || 0) + 1;
                    return sendEvent('share');
                }).catch(() => {});
            }
            return sendEvent(eventType);
        };
        const updateCounts = (counts) => {
            engagementState.value.counts = { ...engagementState.value.counts, ...counts };
        };
        const toggleMute = () => {
            isMuted.value = !isMuted.value;
            if(videoRef.value) videoRef.value.muted = isMuted.value;
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

        return { videoRef, reelRoot, active: computed(() => props.active), isPostCreative, mediaType, videoUrl, mediaUrl, thumbnailUrl, handlePlaying, handlePause, handleBuffering, handleTimeUpdate, handleEnded, recordSocialEvent, engagementState, commentsOpen, isMuted, toggleMute, playbackPaused, isBuffering, togglePlayback, updateCounts };
    }
});
</script>
