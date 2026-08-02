<template>
    <div class="h-full w-full overflow-hidden bg-black relative flex items-center justify-center">
        <img v-if="posterUrl" v-bind:src="posterUrl" class="absolute inset-0 size-full object-cover opacity-45 blur-sm scale-105" alt="Video thumbnail">
        <div class="absolute inset-0 bg-black/55"></div>
        <div class="relative z-10 w-full max-w-[260px] px-6 text-center text-white">
            <div class="mx-auto mb-5 size-20 rounded-full border-2 bg-white/10 inline-flex-center" v-bind:class="isFailed ? 'border-red-500/70' : 'border-white/25'">
                <span class="text-par-xl font-semibold leading-none">{{ progressLabel }}</span>
            </div>
            <h3 class="text-par-l font-semibold">
                {{ stageLabel }}
            </h3>
            <div v-if="! isFailed" class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/20">
                <span class="block h-full rounded-full bg-white transition-width ease-in-out" v-bind:style="{ width: `${progress}%` }"></span>
            </div>
        </div>
    </div>
</template>

<script>
    import { computed, defineComponent } from 'vue';

    export default defineComponent({
        props: {
            frameData: {
                type: Object,
                required: true
            }
        },
        setup: function(props) {
            const progress = computed(() => {
                return Math.max(1, Math.min(100, Number(props.frameData.progress?.display || props.frameData.progress?.overall || 1)));
            });

            const isFailed = computed(() => {
                return props.frameData.progress?.stage === 'failed' || props.frameData.media?.status === 'failed';
            });

            return {
                progress: progress,
                isFailed: isFailed,
                progressLabel: computed(() => {
                    return isFailed.value ? '!' : `${progress.value}%`;
                }),
                posterUrl: computed(() => {
                    return props.frameData.media?.thumbnail_url || props.frameData.media?.preview_url || props.frameData.media?.source_url || props.frameData.media?.lqip_base64;
                }),
                stageLabel: computed(() => {
                    switch(props.frameData.progress?.stage) {
                        case 'uploading':
                            return __t('labels.uploading');
                        case 'uploaded':
                            return __t('labels.story_uploaded_waiting');
                        case 'finishing':
                            return __t('labels.story_finishing_video');
                        case 'failed':
                            return __t('labels.story_failed_upload');
                        case 'ready':
                            return __t('labels.story_ready_soon');
                        default:
                            return __t('labels.story_processing_video');
                    }
                })
            };
        }
    });
</script>
