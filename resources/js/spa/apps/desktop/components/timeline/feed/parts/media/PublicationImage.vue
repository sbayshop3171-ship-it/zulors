<template>
    <div v-if="previewMedia.length" v-bind:class="gridClasses">
        <div
            v-for="(imageItem, idx) in previewMedia"
            v-bind:key="imageItem.id || imageItem.source_url || idx"
            class="relative bg-fill-tr overflow-hidden"
            v-bind:class="getTileClasses(idx)"
        >

            <ProgressiveImageLoader
                v-bind:base64Image="imageItem.lqip_base64"
                v-bind:src="imageItem.source_url"
                v-bind:isSensitive="isSensitive"
                v-bind:class="getImageClasses"
                alt="Image"
            ></ProgressiveImageLoader>

            <div v-if="shouldShowMoreOverlay(idx)" class="absolute inset-0 z-20 flex-center bg-black/55 text-white text-title-1 font-bold pointer-events-none">
                +{{ extraCount }}
            </div>
        </div>
    </div>
</template>
<script>
    import { defineComponent, computed } from 'vue';
    import ProgressiveImageLoader from '@/kernel/vue/components/media/image/ProgressiveImageLoader.vue';

    const MAX_GRID_IMAGES = 5;

    export default defineComponent({
        props: {
            postMedia: {
                type: Array,
                default: () => []
            },
            isSensitive: {
                type: Boolean,
                default: false
            }
        },
        setup: function(props) {
            const postMedia = computed(() => {
                if(Array.isArray(props.postMedia)) {
                    return props.postMedia.filter((mediaItem) => {
                        return Boolean(mediaItem?.source_url);
                    });
                }

                return [];
            });

            const previewMedia = computed(() => {
                return postMedia.value.slice(0, MAX_GRID_IMAGES);
            });

            const mediaCount = computed(() => {
                return previewMedia.value.length;
            });

            const extraCount = computed(() => {
                return Math.max(postMedia.value.length - MAX_GRID_IMAGES, 0);
            });

            const gridClasses = computed(() => {
                if(mediaCount.value === 1) {
                    return 'block';
                }

                if(mediaCount.value === 2 || mediaCount.value === 4) {
                    return 'grid grid-cols-2 gap-0.5';
                }

                if(mediaCount.value === 3) {
                    return 'grid grid-cols-3 gap-0.5';
                }

                return 'grid grid-cols-6 gap-0.5';
            });

            const getTileClasses = (idx) => {
                if(mediaCount.value === 1) {
                    return 'max-h-[620px]';
                }

                if(mediaCount.value === 3 && idx === 0) {
                    return 'col-span-2 row-span-2 aspect-square';
                }

                if(mediaCount.value >= 5) {
                    return idx < 2 ? 'col-span-3 aspect-square' : 'col-span-2 aspect-square';
                }

                return 'aspect-square';
            };

            const shouldShowMoreOverlay = (idx) => {
                return extraCount.value > 0 && idx === previewMedia.value.length - 1;
            };

            return {
                previewMedia: previewMedia,
                extraCount: extraCount,
                gridClasses: gridClasses,
                getTileClasses: getTileClasses,
                getImageClasses: computed(() => {
                    return mediaCount.value === 1 ? 'block w-full max-h-[620px] object-contain' : 'block size-full object-cover';
                }),
                shouldShowMoreOverlay: shouldShowMoreOverlay
            }
        },
        components: {
            ProgressiveImageLoader: ProgressiveImageLoader
        }
    });
</script>
