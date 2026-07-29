<template>
    <div v-if="showSoundbar" class="shadow-xs">
        <SoundbarPlayer></SoundbarPlayer>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { useAudioStore } from '@M/store/audio/audio.store.js';

    import SoundbarPlayer from '@M/components/soundbar/SoundbarPlayer.vue';

    export default defineComponent({
        setup: function(props) {
            const audioStore = useAudioStore();

            return {
                showSoundbar: computed(() => {
                    return audioStore.audioData !== null;
                })
            };
        },
        components: {
            SoundbarPlayer: SoundbarPlayer
        }
    });
</script>