<template>
    <div class="overflow-hidden" v-bind:title="label || mediaItem?.metadata?.file_name">
        <div class="flex items-center gap-2.5">
            <div class="size-10 shrink-0 rounded-full border border-current/10 bg-current/10 flex items-center justify-center">
                <SvgIcon type="line" name="microphone-01" classes="size-icon-small opacity-75"></SvgIcon>
            </div>

            <div class="min-w-0 flex-1">
                <div class="text-current/70">
                    <svg :viewBox="`0 0 ${visualBars.length * 4} 32`" class="h-6 w-full">
                        <rect v-for="(bar, index) in visualBars" :key="index" :x="index * 4" :y="32 - (bar * 28)" width="2" rx="1" :height="bar * 28" fill="currentColor" opacity="0.45"></rect>
                    </svg>
                </div>

                <div class="mt-1 flex items-center justify-between gap-3 text-cap-l">
                    <p class="truncate opacity-80">
                        {{ $filters.mediaDuration(audioDuration) }}
                    </p>
                    <p class="truncate opacity-70">
                        {{ statusText }}
                    </p>
                </div>

                <div v-if="showProgress" class="mt-1 h-1 overflow-hidden rounded-full bg-current/10">
                    <div class="h-full rounded-full bg-current/45 transition-all duration-200" v-bind:style="{ width: progressValue + '%' }"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { computed, defineComponent } from 'vue';
    import { buildStableWaveformBars, resolveMediaDuration } from '@/kernel/helpers/media/audio/index.js';

    export default defineComponent({
        props: {
            label: {
                type: String,
                default: '',
            },
            mediaItem: {
                type: Object,
                default: () => {
                    return {};
                },
            },
            statusText: {
                type: String,
                default: 'Processing audio',
            },
            uploadProgress: {
                type: Number,
                default: 0,
            },
        },
        setup: function(props) {
            return {
                audioDuration: computed(() => {
                    return resolveMediaDuration(
                        props.mediaItem?.metadata?.duration,
                        props.mediaItem?.metadata?.duration_seconds,
                    );
                }),
                progressValue: computed(() => {
                    return Math.max(0, Math.min(100, Math.round(Number(props.uploadProgress) || 0)));
                }),
                showProgress: computed(() => {
                    return props.uploadProgress > 0 && props.uploadProgress < 100;
                }),
                visualBars: computed(() => {
                    return buildStableWaveformBars(props.mediaItem);
                }),
            };
        },
    });
</script>
