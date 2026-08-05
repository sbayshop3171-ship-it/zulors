<template>
    <div v-bind:key="audioData.id" class="pb-2">
        <div v-if="state.isLoaded" v-on:click="seekAudio" class="max-w-full flex flex-col justify-center h-6">
            <div class="bg-fill-sc h-0.5 cursor-pointer leading-zero flex overflow-hidden w-full">
                <span class="h-full max-w-full bg-brand-900 transition-width ease-in-out" v-bind:style="{width: `${playerState.progressBar}%`}"></span>
            </div>
        </div>
        <div v-if="playerState.errors.length > 0" class="bg-red-900/90 backdrop-blur-md text-center py-3 px-4">
            <span class="block text-white text-cap-s leading-tighter">{{ $t('soundbar.playback_failed') }}</span>
        </div>
        <div class="flex items-center h-10 px-4">
            <div class="inline-flex items-center w-3/12">
                <template v-if="state.isLoaded">
                    <div class="shrink-0 leading-zero">
                        <button v-if="playerState.playing" v-on:click="pauseAudio" class="size-icon-small shrink-0 text-lab-pr3 outline-hidden">
                            <SvgIcon type="solid" name="pause" classes="size-full"></SvgIcon>
                        </button>
                        <button v-else v-on:click="playAudio" class="size-icon-small shrink-0 text-lab-pr3 outline-hidden">
                            <SvgIcon type="solid" name="play" classes="size-full"></SvgIcon>
                        </button>
                    </div>
                    <div class="shrink-0 w-10 ml-1 justify-center outline-hidden">
                        <button v-on:click="changeSpeedRate" class="shrink-0 uppercase w-full font-semibold text-par-s text-lab-pr3 opacity-90 smoothing hover:text-brand-900">
                            {{ playerState.rate }}x
                        </button>
                    </div>
                </template>
                <template v-else>
                    <div class="shrink-0 leading-zero ml-4">
                        <div class="colibri-primary-animation"></div>
                    </div>
                </template>
            </div>
            <div class="shrink-0 w-6/12">
                <h4 class="text-lab-sc text-cap-l text-center font-medium truncate">
                    {{ audioLabel }}
                </h4>
            </div>
            <div class="w-3/12 shrink-0 inline-flex items-center justify-end">
                <div class="shrink-0 leading-zero">
                    <button v-on:click="closeSoundbar" class="size-icon-normal shrink-0 text-lab-pr3 outline-hidden">
                        <SvgIcon type="solid" name="x" classes="size-full"></SvgIcon>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, reactive, ref, onMounted, onUnmounted } from 'vue';
    import { useAudioStore } from '@M/store/audio/audio.store.js';
    import { Howl } from 'howler';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { durationObjectToSeconds } from '@/kernel/helpers/media/audio/index.js';

    export default defineComponent({
        setup: function(props) {
            const audioStore = useAudioStore();
            const audioFile = ref(null);
            const audioData = computed(() => {
                return audioStore.audioData;
            });

            const state = reactive({
                isLoaded: false
            });

            const speedRates = [1, 1.5, 2, 2.5, 3];
            const playerState = computed(() => {
                return audioStore.playerState;
            });
            const metadataDurationSeconds = computed(() => {
                return durationObjectToSeconds(audioData.value?.metadata?.duration)
                    || Number(audioData.value?.metadata?.duration_seconds || 0);
            });
            let progressTimer = 0;

            const initializeAudioFile = (() => {
                stopProgressUpdater();

                if(audioFile.value !== null) {
                    audioFile.value.stop();
                    audioFile.value.unload();
                }

                state.isLoaded = false;
                audioStore.clearStateErrors();
                audioStore.updateStateValue('playing', false);
                audioStore.updateStateValue('playbackTime', 0);
                audioStore.updateStateValue('progressBar', 0);
                audioStore.updateStateValue('durationSeconds', metadataDurationSeconds.value);
                audioStore.updateStateValue('isLoading', true);

                audioFile.value = new Howl({
                    src: [audioData.value.source_url],
                    format: audioData.value?.extension ? [audioData.value.extension] : undefined,
                    html5: true,
                    preload: true,
                    rate: playerState.value.rate,
                    mute: playerState.value.isMuted,
                    onload: function() {
                        state.isLoaded = true;
                        audioStore.updateStateValue('isLoading', false);
                        syncDuration(audioFile.value.duration());
                    },
                    onplay: function() {
                        startProgressUpdater();
                        audioStore.updateStateValue('playing', true);
                        syncDuration(audioFile.value.duration());
                    },
                    onpause: function() {
                        stopProgressUpdater();
                        audioStore.updateStateValue('playing', false);
                    },
                    onend: function() {
                        stopProgressUpdater();
                        audioStore.updateStateValue('playing', false);
                        audioStore.updateStateValue('playbackTime', syncDuration(audioFile.value.duration()));
                        audioStore.updateStateValue('progressBar', 100);
                    },
                    onseek: function(time) {
                        audioStore.updateStateValue('playbackTime', time);
                    },
                    onloaderror: (id, error) => {
                        state.isLoaded = false;
                        audioStore.updateStateValue('isLoading', false);
                        audioStore.addStateError(`Failed to load audio (ID: ${id}). Error: ${error}`);
                    },
                    onplayerror: (id, error) => {
                        audioStore.updateStateValue('isLoading', false);
                        audioStore.addStateError(`Failed to play audio (ID: ${id}). Error: ${error}`);
                        audioFile.value.once('unlock', () => {
                            audioFile.value.play();
                        });
                    },
                });
            });

            function syncDuration(duration) {
                const resolvedDuration = Math.max(1, Math.ceil(Number(duration) || metadataDurationSeconds.value || 1));

                audioStore.updateStateValue('durationSeconds', resolvedDuration);

                return resolvedDuration;
            }

            function startProgressUpdater() {
                stopProgressUpdater();

                function updateProgress() {
                    if (!audioFile.value) {
                        return;
                    }

                    const currentTime = Number(audioFile.value.seek() || 0);
                    const duration = syncDuration(audioFile.value.duration());

                    audioStore.updateStateValue('progressBar', duration > 0 ? Math.round(Math.min(100, (currentTime / duration) * 100)) : 0);
                    audioStore.updateStateValue('playbackTime', Math.max(0, Math.floor(currentTime)));

                    if (audioFile.value.playing()) {
                        progressTimer = requestAnimationFrame(updateProgress);
                    }
                }

                progressTimer = requestAnimationFrame(updateProgress);
            }

            function stopProgressUpdater() {
                if(progressTimer) {
                    cancelAnimationFrame(progressTimer);
                    progressTimer = 0;
                }
            }

            const playAudio = () => {
                if(audioFile.value && ! playerState.value.playing) {
                    audioFile.value.play();
                }
            }

            const pauseAudio = () => {
                if(audioFile.value && playerState.value.playing) {
                    audioFile.value.pause();
                }
            }

            const seekAudio = (event) => {
                if(! audioFile.value) {
                    return;
                }

                try {
                    const progressBar = event.currentTarget;
                    const rect = progressBar.getBoundingClientRect();
                    const clickPosition = (event.clientX - rect.left);
                    const percentage = (clickPosition / rect.width);
                    const duration = syncDuration(audioFile.value.duration());
                    const newTime = (duration * percentage);

                    audioFile.value.seek(newTime);

                    playAudio();

                } catch (error) {
                    audioFile.value.seek(0);

                    playAudio();
                }
            }

            onMounted(() => {
                initializeAudioFile();

                audioFile.value?.play();

                const togglePlayback = () => {
                    if(playerState.value.playing) {
                        audioFile.value.pause();
                    }
                    else{
                        audioFile.value.play();
                    }
                };

                const reinitializeSoundbar = () => {
                    initializeAudioFile();
                    audioFile.value?.play();
                };

                colibriEventBus.on('soundbar:play', togglePlayback);
                colibriEventBus.on('soundbar:reinitialize', reinitializeSoundbar);
                colibriEventBus.on('media:pause-all', pauseAudio);

                state.togglePlayback = togglePlayback;
                state.reinitializeSoundbar = reinitializeSoundbar;
            });

            onUnmounted(() => {
                colibriEventBus.off('soundbar:play', state.togglePlayback);
                colibriEventBus.off('soundbar:reinitialize', state.reinitializeSoundbar);

                stopProgressUpdater();
                audioFile.value?.pause();
                audioFile.value?.unload();

                colibriEventBus.off('media:pause-all', pauseAudio);
            });

            return {
                audioData: audioData,
                playerState: playerState,
                playAudio: playAudio,
                pauseAudio: pauseAudio,
                seekAudio: seekAudio,
                state: state,
                audioLabel: computed(() => {
                    return audioStore.label ? audioStore.label : audioData.value.metadata.file_name;
                }),
                changeSpeedRate: () => {
                    const currentIndex = speedRates.indexOf(playerState.value.rate);

                    const nextIndex = ((currentIndex + 1) < speedRates.length) ? (currentIndex + 1) : 0;

                    audioStore.updateStateValue('rate', speedRates[nextIndex]);

                    audioFile.value.rate(playerState.value.rate);
                },
                closeSoundbar: () => {
                    stopProgressUpdater();
                    audioFile.value?.stop();
                    audioFile.value?.unload();
                    state.isLoaded = false;

                    audioStore.remove();
                }
            };
        }
    });
</script>
