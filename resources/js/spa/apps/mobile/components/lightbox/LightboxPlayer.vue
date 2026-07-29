<template>
    <div v-if="albumData" v-on:click.stop="closeLightbox" class="fixed inset-0 z-50 bg-black">
        <div class="fixed top-0 h-12 left-0 right-0 text-center flex items-center justify-center">
            <span class="text-par-m text-white font-mono opacity-60">{{ currentImageNumber + 1 }}/{{ imagesLength }}</span>
        </div>
        <div class="fixed top-12 left-0 right-0 bottom-12 overflow-hidden">
            <swiper-container class="h-full w-full" v-on:swiperslidechange="handleSlideChange" init="true" slidesPerView="1" spaceBetween="0" allowTouchMove="true" effect="slide" speed="200">
                <swiper-slide v-for="image in albumData.images" v-bind:key="image" class="size-full">
                    <img v-bind:src="image" alt="Image" class="size-full object-contain">
                </swiper-slide>
            </swiper-container>
        </div>
        <div v-if="albumData.albumName" class="fixed bottom-0 left-0 right-0 h-12 flex items-center justify-center">
            <span class="text-cap-s text-white opacity-60">{{ albumData.albumName }}</span>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, ref } from 'vue';
    import { register  } from 'swiper/element/bundle';

    register();

    import { useLightboxStore } from '@M/store/lightbox/lightbox.store.js';

    export default defineComponent({
        setup: function() {
            const lightboxStore = useLightboxStore();
            const currentImageNumber = ref(0);

            const closeLightbox = () => {
                lightboxStore.remove();
            }

            const albumData = computed(() => {
                return lightboxStore.albumData;
            });

            return {
                albumData: albumData,
                currentImageNumber: currentImageNumber,
                imagesLength: computed(() => {
                    return albumData.value.images.length;
                }),
                handleSlideChange: function(event) {
                    currentImageNumber.value = event.target.swiper.activeIndex;
                },
                closeLightbox: closeLightbox
            }
        }
    });
</script>