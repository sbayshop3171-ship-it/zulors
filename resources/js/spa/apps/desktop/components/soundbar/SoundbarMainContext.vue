<template>
    <Teleport v-if="showSoundbar" to="body">
        <div class="fixed bottom-0 left-0 right-0 z-50 h-14 border-t border-fill-tr shadow-vertical-qt">
            <SoundbarPlayer></SoundbarPlayer>
        </div>
    </Teleport>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { useAudioStore } from '@D/store/audio/audio.store.js';
    import { useUIStore } from '@D/store/global/ui.store.js';

    import SoundbarPlayer from '@D/components/soundbar/SoundbarPlayer.vue';

    export default defineComponent({
        setup: function(props) {
            const audioStore = useAudioStore();
            const uiStore = useUIStore();

            return {
                showSoundbar: computed(() => {
                    if (uiStore.cheatSheet.isOpen) {
                        return false;
                    }

                    return audioStore.audioData !== null;
                })
            };
        },
        components: {
            SoundbarPlayer: SoundbarPlayer
        }
    });
</script>
