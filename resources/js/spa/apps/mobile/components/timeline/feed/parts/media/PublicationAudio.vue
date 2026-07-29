<template>
    <div class="px-4">
        <AudioPlayer
            v-bind:mediaItem="mediaItem"
            v-bind:key="mediaItem.id"
            v-bind:label="label"
        v-bind:isDisabled="MediaStatusUtils.isProcessing(mediaItem.status)"></AudioPlayer>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import AudioPlayer from '@M/components/players/audio/AudioPlayer.vue';

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
            AudioPlayer: AudioPlayer
        }
    });
</script>
