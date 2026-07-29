<template>
    <div v-on:click="lightboxImages" class="w-full max-w-[68vw] overflow-hidden rounded-lg bg-fill-tr cursor-pointer group sm:max-w-64">
        <img v-on:error="onImageError" v-bind:src="imageUrl" v-bind:alt="mediaData.userName" class="block w-full h-auto smoothing group-hover:opacity-90 transition-all">
    </div>
</template>

<script>
    import { defineComponent, ref, computed } from 'vue';

    import { useLightboxStore } from '@M/store/lightbox/lightbox.store.js';

    export default defineComponent({
        props: {
            mediaData: {
                type: Object,
                required: true
            }
        },
        setup: function(props) {
            const lightboxStore = useLightboxStore();
            const mediaData = ref(props.mediaData);
            const isImageError = ref(false);

            const imageUrl = computed(() => {
                if(isImageError.value) {
                    return assetUrl('assets/images/fallback/fallback-512x256.png');
                }

                return mediaData.value.mediaItem.source_url;
            });

            return {
                imageUrl: imageUrl,
                onImageError: () => {
                    isImageError.value = true;
                },
                lightboxImages: () => {
                    lightboxStore.add({
                        albumName: mediaData.value.userName,
                        date: mediaData.value.date,
                        images: [mediaData.value.mediaItem.source_url]
                    });
                },
            }
        }
    });
</script>
