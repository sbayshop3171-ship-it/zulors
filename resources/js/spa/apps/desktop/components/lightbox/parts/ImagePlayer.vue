<template>
    <button v-if="hasPrev" v-on:click.stop="slidePrev" class="outline-0 cursor-pointer size-12 inline-flex-center rounded-full bg-fill-pr hover:bg-fill-tr fixed top-half left-6 -translate-y-1/2">
        <div class="size-6 text-bg-sc opacity-40 -translate-x-px">
            <SvgIcon name="chevron-left"></SvgIcon>
        </div>
    </button>
    <div v-on:click="$emit('close')" class="select-none flex px-30 py-0 items-center justify-center h-full">
        <div class="h-full flex items-center" v-if="currentImageSource">
            <div class="block select-none max-w-lightbox-content bg-bg-pr" v-bind:class="currentImageGrow">
                <template v-if="TS.imageLoadFailed">
                    <div class="text-center text-par-s text-red-900">
                        😬 <br> Unable to load this image. <br> Please check your internet connection and try again.
                    </div>
                </template>
                <template v-else>
                    <img ref="currentImageRef"
                        class="shadow-md"
                        v-bind:class="currentImageGrow"
                        v-bind:src="currentImageSource"
                        v-bind:key="currentImageIndex"
                    alt="Image">
                </template>
            </div>
        </div>
    </div>
    <button v-if="hasNext" v-on:click.stop="slideNext" class="outline-0 cursor-pointer size-12 inline-flex-center rounded-full bg-fill-pr hover:bg-fill-tr fixed top-half right-6 -translate-y-1/2">
        <div class="size-6 text-bg-sc opacity-40 translate-x-px">
            <SvgIcon name="chevron-right"></SvgIcon>
        </div>
    </button>

    <div class="fixed top-6 left-6 2xl:top-8 2xl:right-8 inline-block leading-4">
        <span class="text-par-s text-white opacity-60">{{ $t('labels.images') }} {{ currentImageIndex + 1 }}/{{ imagesLength }}</span>
    </div>
</template>

<script>
    import { defineComponent, computed, ref, onMounted, onUnmounted } from 'vue';
    import hotkeys from 'hotkeys-js';
    
    export default defineComponent({
        props: {
            albumImages: {
                type: Array,
                default: []
            }
        },
        emits: ['close'],
        setup: function(props) {
            const currentImageGrow = ref('w-full min-w-content');
            const currentImageRef = ref(null);
            const currentImageIndex = ref(0);
            const currentImageLoadFailed = ref(false);

            const slidePrev = () => {
                if(hasPrev.value) {
                    currentImageIndex.value -= 1;
                }
            }
            
            const slideNext = () => {
                if(hasNext.value) {
                    currentImageIndex.value += 1;
                }
            }

            const hasPrev = computed(() => {
                return (props.albumImages[currentImageIndex.value - 1] === undefined) ? false : true;
            });

            const hasNext = computed(() => {
                return (props.albumImages[currentImageIndex.value + 1] === undefined) ? false : true;
            });

            onMounted(() => {
                document.body.classList.add('overflow-hidden');

                if (currentImageRef.value) {
                    currentImageRef.value.onload = () => {
                        const width = currentImageRef.value.naturalWidth;
                        const height = currentImageRef.value.naturalHeight;
                        
                        if (width < height) {
                            currentImageGrow.value = 'h-full';
                        }
                    };

                    currentImageRef.value.onerror = () => {
                        currentImageLoadFailed.value = true;
                    }
                }

                hotkeys('left,up', slidePrev);
                hotkeys('right,down', slideNext);
            });

            onUnmounted(() => {
                document.body.classList.remove('overflow-hidden');

                if (currentImageRef.value) {
                    currentImageRef.value.onload = null;
                    currentImageRef.value.onerror = null;
                }

                hotkeys.unbind('left,up');
                hotkeys.unbind('right,down');
            });

            return {
                TS: {
                    imageLoadFailed: currentImageLoadFailed.value,
                },
                currentImageRef: currentImageRef,
                currentImageGrow: currentImageGrow,
                albumImages: props.albumImages,
                currentImageSource: computed(() => {
                    return props.albumImages[currentImageIndex.value] ?? null;
                }),
                currentImageIndex: currentImageIndex,
                slideNext: slideNext,
                slidePrev: slidePrev,
                hasPrev: hasPrev,
                hasNext: hasNext,
                imagesLength: computed(() => {
                    return props.albumImages.length;
                })
            }
        }
    });
</script>