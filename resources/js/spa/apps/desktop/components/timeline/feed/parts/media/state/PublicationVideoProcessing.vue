<template>
    <div class="flex">
        <div v-bind:class="[isPortrait ? 'w-72' : 'w-full']"
        class="bg-fill-pr block border border-edge-pr rounded-xl overflow-hidden relative">
            <img v-bind:src="mediaItem.thumbnail_url" class="w-full h-full object-cover" alt="Video thumbnail">
            <div class="from-black/60 to-transparent bg-gradient-to-t absolute bottom-0 left-0 right-0 px-4 pb-4 pt-6">
                <span class="text-white text-cap-s leading-none animate-pulse animate-ease-in-out animate-infinite">
                    {{ $t('labels.video_processing') }}
                </span>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, onMounted, onUnmounted } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useTimelineStore } from '@D/store/timeline/timeline.store.js';

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
            }
        },
        setup: function(props) {
            const timelineStore = useTimelineStore();
            const authStore = useAuthStore();
            const mediaItem = computed(() => {
                return props.mediaItem;
            });

            const userId = authStore.userData.id;

            onMounted(() => {
                if(window.ColibriBRD) {
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [userId])).listen(BRD.getEvent('TIMELINE_MEDIA_PROCESSED'), function (event) {
                        if(event.data.mediaable_id == mediaItem.value.mediaable_id) {
                            timelineStore.setPostMedia(event.data);
                            
                            toastSuccess(__t('toast.post_published'));
                        }
                    });
                }
            });

            onUnmounted(() => {
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