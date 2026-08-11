<template>
    <audio ref="remoteAudioRef" class="call-remote-audio" aria-hidden="true" autoplay playsinline></audio>

    <div
        v-if="callStore.isVisible"
        class="call-overlay-root fixed inset-0 isolate z-[1200]"
        v-bind:class="isMini ? 'pointer-events-none' : 'pointer-events-auto'">
        <button
            v-if="isMini"
            type="button"
            class="pointer-events-auto fixed bottom-5 left-1/2 flex h-12 max-w-[calc(100vw-2rem)] -translate-x-1/2 items-center gap-3 rounded-lg border border-bord-pr bg-bg-pr px-4 text-left shadow-2xl"
            v-on:click.stop.prevent="onExpand"
            v-on:touchend.stop.prevent="onExpand">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-green-600 text-white">
                <SvgIcon name="phone" type="line" classes="size-4"></SvgIcon>
            </span>
            <span class="min-w-0">
                <span class="block truncate text-par-s font-semibold text-lab-pr">{{ callStore.title }}</span>
                <span class="block text-par-xs text-lab-sc">{{ durationText }}</span>
            </span>
        </button>

        <div v-else class="call-overlay-backdrop pointer-events-auto absolute inset-0 flex items-center justify-center overflow-y-auto bg-black/45 px-4 py-6">
            <div class="call-overlay-card pointer-events-auto relative z-10 w-full max-w-[390px] rounded-lg border border-bord-pr bg-bg-pr shadow-2xl">
                <div class="flex items-center justify-end px-4 pt-4">
                    <button
                        v-if="callStore.isActive"
                        type="button"
                        class="flex size-9 items-center justify-center rounded-full text-lab-sc hover:bg-fill-qt hover:text-lab-pr"
                        v-on:click.stop.prevent="onMinimize"
                        v-on:touchend.stop.prevent="onMinimize">
                        <SvgIcon name="chevron-down" type="solid" classes="size-5"></SvgIcon>
                    </button>
                </div>

                <div class="call-overlay-body px-6 pb-6 text-center">
                    <div class="call-overlay-avatar mx-auto flex size-28 items-center justify-center overflow-hidden rounded-full bg-fill-qt text-lab-pr">
                        <img v-if="callStore.avatarUrl" v-bind:src="callStore.avatarUrl" class="size-full object-cover" alt="Avatar">
                        <span v-else class="text-title-1 font-bold">{{ avatarInitial }}</span>
                    </div>

                    <h3 class="call-overlay-title mt-4 truncate text-title-3 font-bold text-lab-pr">{{ callStore.title }}</h3>
                    <p class="mt-1 text-par-s text-lab-sc">{{ statusText }}</p>

                    <p v-if="callStore.error" class="mt-3 rounded-lg bg-red-900/10 px-3 py-2 text-par-s font-medium text-red-900">
                        {{ callStore.error }}
                    </p>

                    <div v-if="callStore.isIncoming" class="call-overlay-controls mt-8 grid grid-cols-2 gap-4">
                        <button
                            type="button"
                            class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr"
                            v-on:click.stop.prevent="onDecline"
                            v-on:touchend.stop.prevent="onDecline">
                            <span class="call-overlay-control-icon flex size-14 items-center justify-center rounded-full bg-red-900 text-white">
                                <SvgIcon name="x" type="solid" classes="size-6"></SvgIcon>
                            </span>
                            <span>Decline</span>
                        </button>
                        <button
                            type="button"
                            class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr disabled:opacity-60"
                            v-bind:disabled="callStore.isAnswering"
                            v-on:click.stop.prevent="onAnswer"
                            v-on:touchend.stop.prevent="onAnswer">
                            <span class="call-overlay-control-icon flex size-14 items-center justify-center rounded-full bg-green-600 text-white">
                                <SvgIcon name="phone" type="line" classes="size-6"></SvgIcon>
                            </span>
                            <span>Answer</span>
                        </button>
                    </div>

                    <div v-else class="call-overlay-controls mt-8 grid grid-cols-3 gap-4">
                        <button
                            type="button"
                            class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr"
                            v-on:click.stop.prevent="onSpeakerToggle"
                            v-on:touchend.stop.prevent="onSpeakerToggle">
                            <span class="call-overlay-control-icon flex size-14 items-center justify-center rounded-full bg-fill-qt text-lab-pr">
                                <SvgIcon v-bind:name="callStore.speakerEnabled ? 'volume-max' : 'volume-x'" type="line" classes="size-6"></SvgIcon>
                            </span>
                            <span>Speaker</span>
                        </button>

                        <button type="button" class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-sc opacity-60" disabled>
                            <span class="call-overlay-control-icon flex size-14 items-center justify-center rounded-full bg-fill-qt text-lab-sc">
                                <SvgIcon name="video-recorder" type="line" classes="size-6"></SvgIcon>
                            </span>
                            <span>Video</span>
                        </button>

                        <button
                            type="button"
                            class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr"
                            v-on:click.stop.prevent="onMuteToggle"
                            v-on:touchend.stop.prevent="onMuteToggle">
                            <span class="call-overlay-control-icon flex size-14 items-center justify-center rounded-full bg-fill-qt text-lab-pr">
                                <SvgIcon v-bind:name="callStore.isMuted ? 'volume-x' : 'microphone-01'" type="line" classes="size-6"></SvgIcon>
                            </span>
                            <span>{{ callStore.isMuted ? 'Unmute' : 'Mute' }}</span>
                        </button>

                        <button
                            type="button"
                            class="col-start-2 flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr"
                            v-on:click.stop.prevent="onEndCall"
                            v-on:touchend.stop.prevent="onEndCall">
                            <span class="call-overlay-control-icon flex size-14 items-center justify-center rounded-full bg-red-900 text-white">
                                <SvgIcon name="phone" type="line" classes="size-6 rotate-[135deg]"></SvgIcon>
                            </span>
                            <span>End</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { computed, defineComponent, onBeforeUnmount, ref, watch } from 'vue';

    export default defineComponent({
        props: {
            callStore: {
                type: Object,
                required: true
            }
        },
        setup: function(props) {
            const remoteAudioRef = ref(null);
            const lastControlActionAt = ref(0);
            const formatDuration = (seconds) => {
                const safeSeconds = Math.max(0, Number(seconds || 0));
                const minutes = Math.floor(safeSeconds / 60);
                const remainingSeconds = safeSeconds % 60;

                return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
            };

            const detachRemoteStream = () => {
                if(! remoteAudioRef.value) {
                    return;
                }

                try {
                    remoteAudioRef.value.pause?.();
                    remoteAudioRef.value.srcObject = null;
                    remoteAudioRef.value.removeAttribute?.('src');
                    remoteAudioRef.value.load?.();
                }
                catch(error) {}
            };
            const runControlAction = (callback) => {
                const now = Date.now();

                if(now - lastControlActionAt.value < 320) {
                    return false;
                }

                lastControlActionAt.value = now;

                try {
                    callback?.();
                }
                catch(error) {}

                return true;
            };

            const attachRemoteStream = () => {
                if(! remoteAudioRef.value) {
                    return;
                }

                props.callStore.attachRemoteOutputElement?.(remoteAudioRef.value);

                if(! props.callStore.remoteStream) {
                    detachRemoteStream();

                    return;
                }

                if(remoteAudioRef.value.srcObject !== props.callStore.remoteStream) {
                    remoteAudioRef.value.srcObject = props.callStore.remoteStream;
                }

                remoteAudioRef.value.volume = remoteVolume();
                remoteAudioRef.value.play?.().catch(() => {});
            };
            const remoteVolume = () => {
                if(props.callStore.audioRouteSettling) {
                    return 0;
                }

                return props.callStore.hasNativeAudioBridge ? 1 : (props.callStore.speakerEnabled ? 1 : 0);
            };

            watch(() => props.callStore.remoteStream, attachRemoteStream, {
                immediate: true
            });
            watch(() => props.callStore.status, (status) => {
                if(['idle', 'ended', 'missed', 'declined', 'busy', 'failed'].includes(status)) {
                    detachRemoteStream();
                }
            });
            watch(() => props.callStore.speakerEnabled, () => {
                if(remoteAudioRef.value) {
                    remoteAudioRef.value.volume = remoteVolume();
                }
            });
            watch(() => props.callStore.audioRouteSettling, () => {
                if(remoteAudioRef.value) {
                    remoteAudioRef.value.volume = remoteVolume();
                }
            });
            watch(() => props.callStore.peer, () => {
                if(remoteAudioRef.value) {
                    props.callStore.attachRemoteOutputElement?.(remoteAudioRef.value);
                }
            });
            onBeforeUnmount(() => {
                props.callStore.attachRemoteOutputElement?.(null);
                detachRemoteStream();
            });

            const durationText = computed(() => {
                return formatDuration(props.callStore.durationSeconds);
            });
            const ringCountdownText = computed(() => {
                if(props.callStore.status !== 'ringing' || ! props.callStore.ringSecondsRemaining) {
                    return '';
                }

                return formatDuration(props.callStore.ringSecondsRemaining);
            });

            return {
                remoteAudioRef: remoteAudioRef,
                onExpand: () => runControlAction(() => props.callStore.expand()),
                onMinimize: () => runControlAction(() => props.callStore.minimize()),
                onDecline: () => runControlAction(() => props.callStore.declineCall()),
                onAnswer: () => runControlAction(() => props.callStore.answerCall()),
                onSpeakerToggle: () => runControlAction(() => props.callStore.toggleSpeaker()),
                onMuteToggle: () => runControlAction(() => props.callStore.toggleMute()),
                onEndCall: () => runControlAction(() => props.callStore.endCall()),
                isMini: computed(() => {
                    return props.callStore.minimized && props.callStore.status === 'connected';
                }),
                avatarInitial: computed(() => {
                    return String(props.callStore.title || 'Z').charAt(0).toUpperCase();
                }),
                durationText: durationText,
                statusText: computed(() => {
                    if(props.callStore.status === 'ringing' && props.callStore.direction === 'incoming') {
                        return ringCountdownText.value ? `Incoming voice call · ${ringCountdownText.value}` : 'Incoming voice call';
                    }

                    if(props.callStore.status === 'ringing') {
                        return ringCountdownText.value ? `Calling... ${ringCountdownText.value}` : 'Calling...';
                    }

                    if(['accepted', 'connecting'].includes(props.callStore.status)) {
                        if(props.callStore.networkState === 'reconnecting') {
                            return 'Reconnecting...';
                        }

                        return 'Connecting...';
                    }

                    if(props.callStore.status === 'connected') {
                        if(props.callStore.qualityNotice) {
                            return props.callStore.durationSeconds
                                ? `${props.callStore.qualityNotice} · ${durationText.value}`
                                : props.callStore.qualityNotice;
                        }

                        return props.callStore.durationSeconds ? `Connected · ${durationText.value}` : 'Connected';
                    }

                    if(props.callStore.status === 'declined') {
                        return 'Call declined';
                    }

                    if(props.callStore.status === 'busy') {
                        return 'Busy';
                    }

                    if(props.callStore.status === 'missed') {
                        return 'Missed call';
                    }

                    return 'Call ended';
                })
            };
        }
    });
</script>

<style scoped>
    .call-remote-audio {
        display: none;
        pointer-events: none;
    }

    .call-overlay-root,
    .call-overlay-backdrop {
        touch-action: manipulation;
    }

    .call-overlay-root {
        pointer-events: auto;
        -webkit-tap-highlight-color: transparent;
    }

    .call-overlay-backdrop {
        padding-top: max(1rem, env(safe-area-inset-top));
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }

    .call-overlay-card {
        max-height: calc(100svh - 2rem);
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .call-overlay-card,
    .call-overlay-card button {
        pointer-events: auto;
        touch-action: manipulation;
    }

    .call-overlay-card button {
        user-select: none;
        -webkit-user-select: none;
        -webkit-touch-callout: none;
    }

    @supports not (height: 100svh) {
        .call-overlay-card {
            max-height: calc(100vh - 2rem);
        }
    }

    @media (orientation: landscape) and (max-height: 520px) {
        .call-overlay-backdrop {
            align-items: center;
            padding: 0.75rem;
        }

        .call-overlay-card {
            max-width: min(560px, calc(100vw - 1.5rem));
            max-height: calc(100svh - 1.5rem);
        }

        .call-overlay-body {
            padding-bottom: 1rem;
        }

        .call-overlay-avatar {
            width: 4.25rem;
            height: 4.25rem;
        }

        .call-overlay-title {
            margin-top: 0.75rem;
        }

        .call-overlay-controls {
            margin-top: 1rem;
            gap: 0.75rem;
        }

        .call-overlay-control-icon {
            width: 3rem;
            height: 3rem;
        }
    }
</style>
