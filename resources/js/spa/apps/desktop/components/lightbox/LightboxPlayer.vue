<template>
    <div v-if="albumData" class="fixed inset-0 z-50 bg-black">
        <ImagePlayer v-bind:albumImages="albumData.images" v-on:close="closeLightbox"></ImagePlayer>
        
        <div class="backdrop-close-button-container-top-right">
            <BackdropCloseButton v-on:click="closeLightbox" ></BackdropCloseButton>
        </div>
        <div class="fixed left-0 right-0 bottom-0 from-black to-black/0 bg-gradient-to-t">
            <div class="flex w-full items-center justify-between px-6 py-6">
                <div class="mr-4">
                    <span v-if="albumData.albumName || albumData.date" class="text-par-s inline-flex leading-4 text-white gap-3 items-center opacity-60">
                        <span v-if="albumData.albumName" class="pl-2 first:pl-0">{{ albumData.albumName }}</span>
                        <span v-if="albumData.date" class="pl-2 first:pl-0">{{ albumData.date }}</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>

    import { defineComponent, computed, defineAsyncComponent, onMounted, onUnmounted, ref, watch } from 'vue';

    import { useLightboxStore } from '@D/store/lightbox/lightbox.store.js';

    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import hotkeys from 'hotkeys-js';

    import BackdropCloseButton from '@D/components/inter-ui/buttons/BackdropCloseButton.vue';

    export default defineComponent({
        setup: function() {
            const lightboxStore = useLightboxStore();
            
            onMounted(() => {
                hotkeys('esc', closeLightbox);
            });

            onUnmounted(() => {
                hotkeys.unbind('esc');
            });

            const closeLightbox = () => {
                lightboxStore.remove();
            }

            const albumData = computed(() => {
                return lightboxStore.albumData;
            });

            watch(albumData, () => {
                if (albumData.value) {
                    colibriEventBus.emit('lightbox:opened');
                } else {
                    colibriEventBus.emit('lightbox:closed');
                }
            });

            return {
                albumData: albumData,
                closeLightbox: closeLightbox
            }
        },
        components: {
            ImagePlayer: defineAsyncComponent(() => {
                return import('@D/components/lightbox/parts/ImagePlayer.vue')
            }),
            BackdropCloseButton: BackdropCloseButton
        }
    });
</script>