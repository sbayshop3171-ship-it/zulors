<template>
    <div v-bind:key="audioData.id" class="relative">
        <div v-if="playerState.errors.length > 0" class="absolute bottom-full right-0 left-0 leading-none pt-1 pb-2 bg-red-900/90 backdrop-blur-md text-center">
            <span class="text-white text-cap-l">{{ $t('soundbar.playback_failed') }}</span>
        </div>
        <div class="absolute inset-0 -z-10">
            <canvas ref="soundbarVisualizerCanvas" class="w-full h-full"></canvas>
        </div>
        <div class="z-50 popup-background-pr" v-bind:class="[context === 'main' ? 'pl-page-offset pr-8' : 'px-8']">
            <div class="flex items-center h-14">
                <div class="shrink-0 inline-flex items-center gap-3">
                    <button v-if="playerState.playing" v-on:click="pauseAudio" class="size-icon-small cursor-pointer shrink-0 text-lab-pr3 outline-hidden">
                        <SvgIcon type="solid" name="pause" classes="size-full"></SvgIcon>
                    </button>
                    <button v-else v-on:click="playAudio" class="size-icon-small cursor-pointer shrink-0 text-lab-pr3 outline-hidden">
                        <SvgIcon type="solid" name="play" classes="size-full"></SvgIcon>
                    </button>
                </div>
                <div class="max-w-content min-w-80 flex-1 px-1 ml-6">
                    <div class="flex items-center w-full">
                        <div class="min-w-12">
                            <span class="text-par-s text-lab-pr3">{{ $filters.formatTime(playerState.playbackTime) }}</span>
                        </div>
                        <div v-on:click="seekAudio" class="bg-fill-pr h-1 cursor-pointer flex-1 mx-2 leading-zero flex overflow-hidden rounded-full">
                            <span class="h-full max-w-full bg-brand-900 transition-width ease-in-out" v-bind:style="{width: `${playerState.progressBar}%`}"></span>
                        </div>
                        <div class="min-w-12 text-right">
                            <span class="text-par-s text-lab-pr3">{{ $filters.mediaDuration(audioData.metadata.duration) }}</span>
                        </div>
                    </div>
                </div>
                <div class="shrink-0 inline-flex items-center ml-2">
                    <button v-on:click="muteAudio" v-if="playerState.isMuted" class="size-icon-normal cursor-pointer shrink-0 text-lab-pr3 outline-hidden">
                        <SvgIcon type="solid" name="volume-x" classes="size-full"></SvgIcon>
                    </button>
                    <button v-on:click="muteAudio" v-else class="size-icon-normal shrink-0 text-lab-pr3 outline-hidden cursor-pointer">
                        <SvgIcon type="solid" name="volume-max" classes="size-full"></SvgIcon>
                    </button>
                </div>
                <div class="shrink-0 inline-flex items-center ml-2 w-10 justify-center outline-hidden">
                    <button v-on:click="changeSpeedRate" class="shrink-0 cursor-pointer outline-hidden uppercase w-full font-semibold text-par-s text-lab-pr3 opacity-90 smoothing hover:text-brand-900">
                        {{ playerState.rate }}x
                    </button>
                </div>
                <div class="overflow-hidden inline-flex items-center mx-4 max-w-60">
                    <span class="text-lab-sc text-par-s font-medium truncate">
                        {{ audioLabel }}
                    </span>
                </div>
                <div class="shrink-0 ml-auto">
                    <PrimaryIconButton iconName="x" iconType="solid" v-on:click="closeSoundbar"></PrimaryIconButton>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, ref, onMounted, onUnmounted } from 'vue';
    import { useAudioStore } from '@D/store/audio/audio.store.js';
    import { Howl, Howler } from 'howler';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { audioVisualizer } from '@/kernel/services/audio-visualizer/index.js';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        components: {
            PrimaryIconButton: PrimaryIconButton
        },
        props: {
            context: {
                type: String,
                default: 'main'
            }
        },
        setup: function(props) {
            const audioStore = useAudioStore();
            const audioFile = ref(null);
            const audioData = computed(() => {
                return audioStore.audioData;
            });

            const soundbarVisualizerCanvas = ref(null);
            var soundbarVisualizer = null;
            const speedRates = [1, 1.5, 2, 2.5, 3];
            const playerState = computed(() => {
                return audioStore.playerState;
            });

            const initializeAudioFile = (() => {
                if(audioFile.value !== null) {
                    audioFile.value.unload();
                }

                audioFile.value = new Howl({
                    src: [audioData.value.source_url],
                    rate: playerState.value.rate,
                    mute: playerState.value.isMuted,
                    onplay: function() {
                        startProgressUpdater();
                        audioStore.updateStateValue('playing', true);

                        const audioContext = Howler.ctx;
                        const analyser = audioContext.createAnalyser();
                        Howler.masterGain.connect(analyser);
                        try {
                            soundbarVisualizer = audioVisualizer().setCanvas(soundbarVisualizerCanvas.value).setAudioElement({
                                context: audioContext,
                                source: analyser
                            });

                            soundbarVisualizer.linesWaveStart({
                                frequencyBand: 'mids',
                                fillColor: {
                                    gradient: [
                                        'red',
                                        'orange',
                                        'yellow',
                                        'green',
                                        'blue',
                                        'indigo',
                                        'violet',
                                        'purple'
                                    ]
                                },
                            });
                        } catch (error) {
                            // Ignore
                        }
                    },
                    onpause: function() {
                        stopProgressUpdater();
                        audioStore.updateStateValue('playing', false);
                    },
                    onend: function() {
                        stopProgressUpdater();
                        audioStore.updateStateValue('playing', false);
                    },
                    onseek: function(time) {
                        audioStore.updateStateValue('playbackTime', time);
                    },
                    onloaderror: (id, error) => {
                        audioStore.addStateError(`Failed to load audio (ID: ${id}). Error: ${error}`);
                    },
                    onplayerror: (id, error) => {
                        audioStore.addStateError(`Failed to play audio (ID: ${id}). Error: ${error}`);
                    },
                });
            });

            function startProgressUpdater() {
                function updateProgress() {
                    const currentTime = audioFile.value.seek();
                    const duration = audioFile.value.duration();

                    audioStore.updateStateValue('progressBar', Math.round((currentTime / duration) * 100));
                    audioStore.updateStateValue('playbackTime', Math.round(currentTime));

                    if (audioFile.value.playing()) {
                        window.progressTimer = requestAnimationFrame(updateProgress);
                    }
                }

                window.progressTimer = requestAnimationFrame(updateProgress);
            }

            function stopProgressUpdater() {
                cancelAnimationFrame(window.progressTimer);
            }

            const playAudio = () => {
                if(! playerState.value.playing) {
                    audioFile.value.play();
                }
            }

            const pauseAudio = () => {
                if(playerState.value.playing) {
                    audioFile.value.pause();
                }
            }

            const seekAudio = (event) => {
                try {
                    const progressBar = event.currentTarget;
                    const rect = progressBar.getBoundingClientRect();
                    const clickPosition = (event.clientX - rect.left);
                    const percentage = (clickPosition / rect.width);
                    const newTime = (audioFile.value.duration() * percentage);

                    audioFile.value.seek(newTime);

                    playAudio();

                } catch (error) {
                    audioFile.value.seek(0);

                    playAudio();
                }
            }

            onMounted(() => {
                initializeAudioFile();

                document.body.classList.add('sticky-bar-open');

                audioFile.value.play();

                colibriEventBus.on('soundbar:play', () => {
                    if(playerState.value.playing) {
                        audioFile.value.pause();
                    }
                    else{
                        audioFile.value.play();
                    }
                });

                colibriEventBus.on('soundbar:reset', closeSoundbar);

                colibriEventBus.on('soundbar:reinitialize', () => {
                    initializeAudioFile();
                    audioFile.value.play();
                });

                colibriEventBus.on('media:pause-all', pauseAudio);
            });

            onUnmounted(() => {
                document.body.classList.remove('sticky-bar-open');

                colibriEventBus.off('soundbar:play');
                colibriEventBus.off('soundbar:reinitialize');

                audioFile.value.pause();
                colibriEventBus.off('media:pause-all', pauseAudio);

                colibriEventBus.off('soundbar:reset', closeSoundbar);
            });

            const closeSoundbar = () => {
                audioFile.value.pause();
                audioFile.value.unload();

                audioStore.remove();
            }

            return {
                audioData: audioData,
                playerState: playerState,
                playAudio: playAudio,
                pauseAudio: pauseAudio,
                seekAudio: seekAudio,
                soundbarVisualizerCanvas: soundbarVisualizerCanvas,
                audioLabel: computed(() => {
                    return audioStore.label ? audioStore.label : audioData.value.metadata.file_name;
                }),
                muteAudio: () => {
                    if (playerState.value.isMuted) {
                        audioFile.value.mute(false);

                        audioStore.updateStateValue('isMuted', false);
                    }
                    else{
                        audioFile.value.mute(true);

                        audioStore.updateStateValue('isMuted', true);
                    }
                },
                changeSpeedRate: () => {
                    const currentIndex = speedRates.indexOf(playerState.value.rate);

                    const nextIndex = ((currentIndex + 1) < speedRates.length) ? (currentIndex + 1) : 0;

                    audioStore.updateStateValue('rate', speedRates[nextIndex]);

                    audioFile.value.rate(playerState.value.rate);
                },
                closeSoundbar: closeSoundbar
            };
        }
    });
</script>
