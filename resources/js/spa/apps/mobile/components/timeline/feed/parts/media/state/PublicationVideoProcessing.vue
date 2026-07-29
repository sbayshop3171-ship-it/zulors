<template>
    <div class="bg-fill-pr overflow-hidden relative">
        <img v-bind:src="mediaItem.thumbnail_url" class="w-full h-full object-cover" alt="Video thumbnail">
        <div class="from-black/60 to-transparent bg-gradient-to-t absolute bottom-0 left-0 right-0 px-4 pb-4 pt-6">
            <span class="text-white text-cap-s leading-none animate-pulse animate-ease-in-out animate-infinite">
                {{ $t('labels.video_processing') }}
            </span>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, onMounted, onUnmounted } from 'vue';
    import { useAuthStore } from '@M/store/auth/auth.store.js';
    import { useTimelineStore } from '@M/store/timeline/timeline.store.js';
    import BRD from '@/kernel/websockets/brd/index.js';

    export default defineComponent({
        props: {
            mediaItem: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            const authStore = useAuthStore();
            const timelineStore = useTimelineStore();

            const mediaItem = computed(() => {
                return props.mediaItem;
            });

            const userId = authStore.userData.id;
            let refreshIntervalId = null;

            const syncProcessedMedia = (event) => {
                if(event.data.mediaable_id == mediaItem.value.mediaable_id) {
                    timelineStore.setPostMedia(event.data);

                    toastSuccess(__t('toast.post_published'));
                }
            };

            onMounted(() => {
                timelineStore.refreshFirstPage();

                refreshIntervalId = setInterval(() => {
                    timelineStore.refreshFirstPage();
                }, 15000);

                if(window.ColibriBRD) {
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [userId])).listen(BRD.getEvent('TIMELINE_MEDIA_PROCESSED'), syncProcessedMedia);
                }
            });

            onUnmounted(() => {
                if(refreshIntervalId) {
                    clearInterval(refreshIntervalId);
                }

                if(window.ColibriBRD) {
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [userId])).stopListening(BRD.getEvent('TIMELINE_MEDIA_PROCESSED'));
                }
            });

            return {
                mediaItem: mediaItem
            }
        }
    });
</script>
