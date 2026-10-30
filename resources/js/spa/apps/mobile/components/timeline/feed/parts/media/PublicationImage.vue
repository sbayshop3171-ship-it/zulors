<template>
    <div v-if="previewMedia.length" v-bind:class="gridClasses">
        <div
            v-for="(imageItem, idx) in previewMedia"
            v-bind:key="imageItem.id || imageItem.source_url || idx"
            class="relative bg-fill-tr overflow-hidden"
            v-bind:class="getTileClasses(idx)"
        >

            <img v-if="sponsoredFeed && mediaCount === 1 && !isSensitive" v-bind:src="imageItem.source_url" alt="" aria-hidden="true" class="pointer-events-none absolute inset-0 size-full scale-110 object-cover blur-2xl opacity-60">
            <ProgressiveImageLoader
                v-bind:base64Image="imageItem.lqip_base64"
                v-bind:src="imageItem.source_url"
                v-bind:isSensitive="isSensitive"
                v-bind:loading="isPriority && idx === 0 ? 'eager' : 'lazy'"
                v-bind:fetchPriority="isPriority && idx === 0 ? 'high' : 'auto'"
                v-bind:class="getImageClasses"
                alt="Image"
            ></ProgressiveImageLoader>

            <div v-if="shouldShowMoreOverlay(idx)" class="absolute inset-0 z-20 flex-center bg-black/55 text-white text-title-2 font-bold pointer-events-none">
                +{{ extraCount }}
            </div>
        </div>
    </div>
</template>
<script>
    import { defineComponent, computed } from 'vue';
    import ProgressiveImageLoader from '@/kernel/vue/components/media/image/ProgressiveImageLoader.vue';
    import { PUBLICATION_IMAGE_GALLERY_LIMIT, getPublicationImageGalleryLayout, getPublicationImageTileClasses, isPublicationImageGalleryMoreTile } from '@/kernel/services/media/publication-image-gallery.js';

    export default defineComponent({
        props: {
            postMedia: {
                type: Array,
                default: () => []
            },
            isSensitive: {
                type: Boolean,
                default: false
            },
            sponsoredFeed: { type: Boolean, default: false },
            isPriority: {
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
                return postMedia.value.slice(0, PUBLICATION_IMAGE_GALLERY_LIMIT);
            });

            const mediaCount = computed(() => {
                return previewMedia.value.length;
            });

            const getTileClasses = (idx) => {
                const singleTileClass = props.sponsoredFeed ? 'aspect-square' : 'max-h-[70vh]';
                return getPublicationImageTileClasses(mediaCount.value, idx, singleTileClass);
            };

            const shouldShowMoreOverlay = (idx) => {
                return isPublicationImageGalleryMoreTile(postMedia.value.length, idx);
            };

            return {
                previewMedia: previewMedia,
                extraCount: computed(() => getPublicationImageGalleryLayout(postMedia.value.length).extraCount),
                mediaCount: mediaCount,
                gridClasses: computed(() => getPublicationImageGalleryLayout(mediaCount.value).gridClasses),
                getTileClasses: getTileClasses,
                getImageClasses: computed(() => {
                    if(props.sponsoredFeed && mediaCount.value === 1) return 'relative block size-full object-contain';
                    return mediaCount.value === 1 ? 'block w-full max-h-[70vh] object-contain' : 'block size-full object-cover';
                }),
                shouldShowMoreOverlay: shouldShowMoreOverlay
            }
        },
        components: {
            ProgressiveImageLoader: ProgressiveImageLoader
        }
    });
</script>
