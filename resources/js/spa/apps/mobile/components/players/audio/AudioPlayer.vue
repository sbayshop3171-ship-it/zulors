<template>
    <div class="overflow-hidden" v-bind:title="label || mediaItem.metadata.file_name">
        <div class="flex justify-between items-center gap-2.5">
            <div class="shrink-0">
                <template v-if="isDisabled">
                    <AudioPlayButton v-bind:disabled="true"></AudioPlayButton>
                </template>
                <template v-else>
                    <template v-if="playerState">
                        <AudioPlayButton v-if="playerState.playing" iconName="pause" v-on:click="togglePlay"></AudioPlayButton>
                        <AudioPlayButton v-else v-on:click="togglePlay"></AudioPlayButton>
                    </template>
                    <template v-else>
                        <AudioPlayButton v-on:click="addAudio"></AudioPlayButton>
                    </template>
                </template>
            </div>
            <div class="flex-1 overflow-hidden">
                <div class="mb-1 text-lab-pr">
                    <svg :viewBox="`0 0 ${visualBars.length * 4} 32`" class="h-6">
                        <rect v-for="(bar, i) in visualBars" :height="bar * 28" :key="i" :x="i * 4" :y="32 - bar * 28" width="2" rx="1" fill="currentColor" opacity="0.5"/>
                    </svg>
                </div>
                <p class="text-cap-l text-lab-sc" v-bind:title="$filters.fileSize(mediaItem.size)">
                    {{ $filters.mediaDuration(audioDuration) }}
                </p>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { useAudioStore } from '@M/store/audio/audio.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { buildStableWaveformBars, resolveMediaDuration } from '@/kernel/helpers/media/audio/index.js';

    import AudioPlayButton from '@M/components/players/audio/parts/AudioPlayButton.vue';

    export default defineComponent({
        props: {
            isDisabled: {
                type: Boolean,
                default: false
            },
            label: {
                type: String,
                default: ''
            },
            mediaItem: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            const audioStore = useAudioStore();

            const playerState = computed(() => {
                if (audioStore.audioData) {
                    if(audioStore.audioData.id === props.mediaItem.id) {
                        return audioStore.playerState;
                    }
                }

                return null;
            });
            return {
                playerState: playerState,
                mediaItem: props.mediaItem,
                audioDuration: computed(() => {
                    return resolveMediaDuration(
                        props.mediaItem?.metadata?.duration,
                        props.mediaItem?.metadata?.duration_seconds,
                    );
                }),
                addAudio: () => {
                    audioStore.add(props.mediaItem, props.label);

                    colibriEventBus.emit('soundbar:reinitialize');
                },
                togglePlay: () => {
                    colibriEventBus.emit('soundbar:play');
                },
                visualBars: computed(() => {
                    return buildStableWaveformBars(props.mediaItem);
                }),
            }
        },
        components: {
            AudioPlayButton: AudioPlayButton
        }
    });
</script>
