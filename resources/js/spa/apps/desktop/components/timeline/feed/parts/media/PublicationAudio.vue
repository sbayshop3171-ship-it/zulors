<template>
    <AudioPlayer
        v-bind:mediaItem="mediaItem"
        v-bind:key="mediaItem.id"
        v-bind:label="label"
    v-bind:isDisabled="MediaStatusUtils.isProcessing(mediaItem.status)"></AudioPlayer>
    <template v-if="MediaStatusUtils.isProcessing(mediaItem.status)">
        <PublicationAudioProcessing v-bind:mediaItem="mediaItem" v-bind:key="mediaItem.id"></PublicationAudioProcessing>
    </template>
</template>

<script>
    import { defineComponent, defineAsyncComponent, computed } from 'vue';
    import AudioPlayer from '@D/components/players/audio/AudioPlayer.vue';

    import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';

    export default defineComponent({
        props: {
            postMedia: {
                type: Object,
                default: {}
            },
            label: {
                type: String,
                default: ''
            }
        },
        setup: function(props) {
            const mediaItem = computed(() => {
                return props.postMedia[0];
            });

            return {
                mediaItem: mediaItem,
                MediaStatusUtils: MediaStatusUtils
            }
        },
        components: {
            AudioPlayer: AudioPlayer,
            PublicationAudioProcessing: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/media/state/PublicationAudioProcessing.vue');
            })
        }
    });
</script>
