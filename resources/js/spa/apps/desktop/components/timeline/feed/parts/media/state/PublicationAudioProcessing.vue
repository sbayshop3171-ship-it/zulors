<template>
    <div class="w-full pb-2 items-center flex overflow-hidden mt-2">
        <div class="shrink-0">
            <h4 class="text-lab-pr text-cap-l leading-none animate-pulse">
                {{ $t('labels.audio_processing') }}
            </h4>
        </div>
        <div class="ml-6">
            <PrimaryDotsAnimation></PrimaryDotsAnimation>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, onMounted, onUnmounted } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useTimelineStore } from '@D/store/timeline/timeline.store.js';
    import BRD from '@/kernel/websockets/brd/index.js';

    export default defineComponent({
        props: {
            mediaItem: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            const timelineStore = useTimelineStore();
            const authStore = useAuthStore();
            const mediaItem = ref(props.mediaItem);

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
