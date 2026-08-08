<template>
    <audio ref="remoteAudioRef" autoplay playsinline></audio>

    <div v-if="callStore.isVisible" class="fixed inset-0 z-[1200] pointer-events-none">
        <button
            v-if="isMini"
            type="button"
            class="pointer-events-auto fixed bottom-5 left-1/2 flex h-12 max-w-[calc(100vw-2rem)] -translate-x-1/2 items-center gap-3 rounded-lg border border-bord-pr bg-bg-pr px-4 text-left shadow-2xl"
        v-on:click="callStore.expand">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-green-600 text-white">
                <SvgIcon name="phone" type="line" classes="size-4"></SvgIcon>
            </span>
            <span class="min-w-0">
                <span class="block truncate text-par-s font-semibold text-lab-pr">{{ callStore.title }}</span>
                <span class="block text-par-xs text-lab-sc">{{ durationText }}</span>
            </span>
        </button>

        <div v-else class="pointer-events-auto absolute inset-0 flex items-center justify-center bg-black/45 px-4 py-6 backdrop-blur-sm">
            <div class="w-full max-w-[390px] rounded-lg border border-bord-pr bg-bg-pr shadow-2xl">
                <div class="flex items-center justify-end px-4 pt-4">
                    <button
                        v-if="callStore.isActive"
                        type="button"
                        class="flex size-9 items-center justify-center rounded-full text-lab-sc hover:bg-fill-qt hover:text-lab-pr"
                    v-on:click="callStore.minimize">
                        <SvgIcon name="chevron-down" type="solid" classes="size-5"></SvgIcon>
                    </button>
                </div>

                <div class="px-6 pb-6 text-center">
                    <div class="mx-auto flex size-28 items-center justify-center overflow-hidden rounded-full bg-fill-qt text-lab-pr">
                        <img v-if="callStore.avatarUrl" v-bind:src="callStore.avatarUrl" class="size-full object-cover" alt="Avatar">
                        <span v-else class="text-title-1 font-bold">{{ avatarInitial }}</span>
                    </div>

                    <h3 class="mt-4 truncate text-title-3 font-bold text-lab-pr">{{ callStore.title }}</h3>
                    <p class="mt-1 text-par-s text-lab-sc">{{ statusText }}</p>

                    <p v-if="callStore.error" class="mt-3 rounded-lg bg-red-900/10 px-3 py-2 text-par-s font-medium text-red-900">
                        {{ callStore.error }}
                    </p>

                    <div v-if="callStore.isIncoming" class="mt-8 grid grid-cols-2 gap-4">
                        <button type="button" class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr" v-on:click="callStore.declineCall">
                            <span class="flex size-14 items-center justify-center rounded-full bg-red-900 text-white">
                                <SvgIcon name="x" type="solid" classes="size-6"></SvgIcon>
                            </span>
                            <span>Decline</span>
                        </button>
                        <button
                            type="button"
                            class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr disabled:opacity-60"
                            v-bind:disabled="callStore.isAnswering"
                        v-on:click="callStore.answerCall">
                            <span class="flex size-14 items-center justify-center rounded-full bg-green-600 text-white">
                                <SvgIcon name="phone" type="line" classes="size-6"></SvgIcon>
                            </span>
                            <span>Answer</span>
                        </button>
                    </div>

                    <div v-else class="mt-8 grid grid-cols-3 gap-4">
                        <button type="button" class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr" v-on:click="callStore.toggleSpeaker">
                            <span class="flex size-14 items-center justify-center rounded-full bg-fill-qt text-lab-pr">
                                <SvgIcon v-bind:name="callStore.speakerEnabled ? 'volume-max' : 'volume-x'" type="line" classes="size-6"></SvgIcon>
                            </span>
                            <span>Speaker</span>
                        </button>

                        <button type="button" class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-sc opacity-60" disabled>
                            <span class="flex size-14 items-center justify-center rounded-full bg-fill-qt text-lab-sc">
                                <SvgIcon name="video-recorder" type="line" classes="size-6"></SvgIcon>
                            </span>
                            <span>Video</span>
                        </button>

                        <button type="button" class="flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr" v-on:click="callStore.toggleMute">
                            <span class="flex size-14 items-center justify-center rounded-full bg-fill-qt text-lab-pr">
                                <SvgIcon v-bind:name="callStore.isMuted ? 'volume-x' : 'microphone-01'" type="line" classes="size-6"></SvgIcon>
                            </span>
                            <span>{{ callStore.isMuted ? 'Unmute' : 'Mute' }}</span>
                        </button>

                        <button type="button" class="col-start-2 flex flex-col items-center gap-2 text-par-s font-semibold text-lab-pr" v-on:click="callStore.endCall">
                            <span class="flex size-14 items-center justify-center rounded-full bg-red-900 text-white">
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
    import { computed, defineComponent, ref, watch } from 'vue';

    export default defineComponent({
        props: {
            callStore: {
                type: Object,
                required: true
            }
        },
        setup: function(props) {
            const remoteAudioRef = ref(null);
            const formatDuration = (seconds) => {
                const safeSeconds = Math.max(0, Number(seconds || 0));
                const minutes = Math.floor(safeSeconds / 60);
                const remainingSeconds = safeSeconds % 60;

                return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
            };

            const attachRemoteStream = () => {
                if(remoteAudioRef.value && props.callStore.remoteStream) {
                    remoteAudioRef.value.srcObject = props.callStore.remoteStream;
                    remoteAudioRef.value.volume = props.callStore.speakerEnabled ? 1 : 0;
                    remoteAudioRef.value.play?.().catch(() => {});
                }
            };

            watch(() => props.callStore.remoteStream, attachRemoteStream);
            watch(() => props.callStore.speakerEnabled, () => {
                if(remoteAudioRef.value) {
                    remoteAudioRef.value.volume = props.callStore.speakerEnabled ? 1 : 0;
                }
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
