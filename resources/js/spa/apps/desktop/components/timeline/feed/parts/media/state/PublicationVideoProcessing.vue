<template>
    <div class="flex">
        <div v-bind:class="frameWidthClass"
        class="bg-fill-pr block border border-edge-pr rounded-xl overflow-hidden relative">
            <div
                v-bind:style="frameStyle"
            class="relative">
                <img v-if="mediaItem.thumbnail_url" v-bind:src="mediaItem.thumbnail_url" class="size-full object-cover" alt="Video thumbnail">
                <div v-else class="size-full flex-center bg-fill-tr text-lab-sc">
                    <span class="size-icon-large">
                        <SvgIcon name="video-recorder" type="line"></SvgIcon>
                    </span>
                </div>
            </div>
            <div class="from-black/70 to-transparent bg-gradient-to-t absolute bottom-0 left-0 right-0 px-4 pb-4 pt-8">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-white text-cap-s leading-none animate-pulse animate-ease-in-out animate-infinite">
                        {{ progressLabel }}
                    </span>
                    <span class="text-white/80 text-cap-s leading-none">{{ progress }}%</span>
                </div>
                <div class="mt-2 h-1 overflow-hidden rounded-full bg-white/20">
                    <span class="block h-full rounded-full bg-white transition-width ease-in-out" v-bind:style="{ width: progressWidth }"></span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, onMounted, onUnmounted } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useTimelineStore } from '@D/store/timeline/timeline.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';
    import { videoFrameAspectStyle } from '@/kernel/services/media/video-metadata.js';

    import BRD from '@/kernel/websockets/brd/index.js';

    export default defineComponent({
        props: {
            mediaItem: {
                type: Object,
                default: {}
            },
            isPortrait: {
                type: Boolean,
                default: false
            },
            aspectRatio: {
                type: [Number, String],
                default: null
            }
        },
        setup: function(props) {
            const timelineStore = useTimelineStore();
            const authStore = useAuthStore();
            const mediaItem = computed(() => {
                return props.mediaItem;
            });
            const progress = computed(() => {
                const metadata = mediaItem.value?.metadata || {};
                const isUploading = Boolean(metadata.upload_state && metadata.upload_state !== 'uploaded');
                const rawProgress = isUploading
                    ? metadata.upload_progress
                    : metadata.processing_progress;

                return Math.max(0, Math.min(100, Number(rawProgress || 0)));
            });
            const progressLabel = computed(() => {
                const metadata = mediaItem.value?.metadata || {};

                if(metadata.upload_state === 'failed') {
                    return 'Upload failed';
                }

                if(metadata.processing_state === 'failed' || MediaStatusUtils.isFailed(mediaItem.value?.status)) {
                    return 'Processing failed';
                }

                return metadata.upload_state && metadata.upload_state !== 'uploaded' ? 'Uploading' : 'Processing';
            });
            const progressWidth = computed(() => {
                return `${Math.max(3, progress.value)}%`;
            });

            const userId = authStore.userData.id;
            let refreshIntervalId = null;
            let hasPublishedToastShown = false;

            const syncMediaData = (mediaData) => {
                if(! mediaData?.id || mediaData.mediaable_id != mediaItem.value.mediaable_id || mediaData.id != mediaItem.value.id) {
                    return;
                }

                Object.assign(mediaItem.value, mediaData);
                timelineStore.setPostMedia(mediaData);

                if(MediaStatusUtils.isProcessed(mediaData.status) && ! hasPublishedToastShown) {
                    hasPublishedToastShown = true;
                    toastSuccess(__t('toast.post_published'));
                }
            };

            const syncMediaEvent = (event) => {
                syncMediaData(event?.data || event);
            };

            onMounted(() => {
                timelineStore.refreshFirstPage();

                refreshIntervalId = setInterval(() => {
                    timelineStore.refreshFirstPage();
                }, 5000);

                if(window.ColibriBRD) {
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [userId])).listen(BRD.getEvent('TIMELINE_MEDIA_PROCESSED'), syncMediaEvent);
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [userId])).listen(BRD.getEvent('TIMELINE_MEDIA_UPDATED'), syncMediaEvent);
                }

                colibriEventBus.on('timeline:media-updated', syncMediaEvent);
            });

            onUnmounted(() => {
                if(refreshIntervalId) {
                    clearInterval(refreshIntervalId);
                }

                if(window.ColibriBRD) {
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [userId])).stopListening(BRD.getEvent('TIMELINE_MEDIA_PROCESSED'), syncMediaEvent);
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [userId])).stopListening(BRD.getEvent('TIMELINE_MEDIA_UPDATED'), syncMediaEvent);
                }

                colibriEventBus.off('timeline:media-updated', syncMediaEvent);
            });

            return {
                mediaItem: mediaItem,
                progress: progress,
                progressLabel: progressLabel,
                progressWidth: progressWidth,
                frameWidthClass: computed(() => {
                    return props.isPortrait ? 'w-full max-w-[348px] mx-auto' : 'w-full';
                }),
                frameStyle: computed(() => {
                    return videoFrameAspectStyle({
                        ...(mediaItem.value?.metadata || {}),
                        aspect_ratio: props.aspectRatio || mediaItem.value?.metadata?.aspect_ratio
                    }, props.isPortrait);
                })
            }
        }
    });
</script>
