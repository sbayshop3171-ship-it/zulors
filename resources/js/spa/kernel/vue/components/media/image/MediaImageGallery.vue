<template>
    <div v-if="visibleMedia.length" class="media-image-gallery relative w-full">
        <ContentImage
            v-if="visibleMedia.length === 1"
            v-bind:mediaItem="visibleMedia[0]"
            v-bind:isSensitive="isSensitive"
            v-bind:loading="isPriority ? 'eager' : 'lazy'"
            v-bind:fetchPriority="isPriority ? 'high' : 'auto'"
            v-on:click="selectImage(0, $event)"
            v-on:load="refreshSwiperHeight"
        >
            <slot name="overlay" v-bind:mediaItem="visibleMedia[0]" v-bind:index="0"></slot>
        </ContentImage>

        <template v-else>
            <swiper-container
                ref="swiperElement"
                slides-per-view="1"
                space-between="0"
                auto-height="true"
                allow-touch-move="true"
                grab-cursor="true"
                prevent-clicks="true"
                prevent-clicks-propagation="true"
                v-on:swiperslidechange="handleSlideChange"
            >
                <swiper-slide v-for="(mediaItem, index) in visibleMedia" v-bind:key="mediaKey(mediaItem, index)" class="media-image-gallery-slide">
                    <ContentImage
                        v-bind:mediaItem="mediaItem"
                        v-bind:isSensitive="isSensitive"
                        v-bind:loading="isPriority && index === 0 ? 'eager' : 'lazy'"
                        v-bind:fetchPriority="isPriority && index === 0 ? 'high' : 'auto'"
                        v-on:click="selectImage(index, $event)"
                        v-on:load="refreshSwiperHeight"
                    >
                        <slot name="overlay" v-bind:mediaItem="mediaItem" v-bind:index="index"></slot>
                    </ContentImage>
                </swiper-slide>
            </swiper-container>
            <button type="button" class="media-image-gallery-control left-3" aria-label="Previous image" v-bind:disabled="activeIndex === 0" v-on:click.stop="changeSlide(-1)">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" class="media-image-gallery-control right-3" aria-label="Next image" v-bind:disabled="activeIndex >= visibleMedia.length - 1" v-on:click.stop="changeSlide(1)">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="pointer-events-none absolute right-3 top-3 z-20 rounded-full bg-black/65 px-2.5 py-1 text-xs font-semibold text-white" aria-live="polite">
                {{ activeIndex + 1 }} / {{ visibleMedia.length }}
            </div>
        </template>
    </div>
</template>

<script>
    import { computed, defineComponent, nextTick, onMounted, ref, watch } from 'vue';
    import { register } from 'swiper/element/bundle';
    import ContentImage from '@/kernel/vue/components/media/image/ContentImage.vue';

    register();

    export default defineComponent({
        emits: ['select'],
        props: {
            mediaItems: { type: Array, default: () => [] },
            isSensitive: { type: Boolean, default: false },
            isPriority: { type: Boolean, default: false },
            isSelectable: { type: Boolean, default: false },
        },
        setup(props, context) {
            const swiperElement = ref(null);
            const activeIndex = ref(0);
            const visibleMedia = computed(() => Array.isArray(props.mediaItems)
                ? props.mediaItems.filter((mediaItem) => Boolean(mediaItem?.source_url || mediaItem?.preview_url || mediaItem?.metadata?.preview_url))
                : []);

            const refreshSwiperHeight = () => {
                nextTick(() => swiperElement.value?.swiper?.updateAutoHeight(0));
            };

            onMounted(() => {
                swiperElement.value?.swiper?.updateAutoHeight(0);
            });

            watch(() => visibleMedia.value.length, (count) => {
                activeIndex.value = Math.min(activeIndex.value, Math.max(0, count - 1));
                nextTick(() => {
                    swiperElement.value?.swiper?.update();
                    swiperElement.value?.swiper?.updateAutoHeight(0);
                });
            });

            return {
                swiperElement,
                visibleMedia,
                activeIndex,
                mediaKey: (mediaItem, index) => String(mediaItem.preview_key || mediaItem.id || mediaItem.source_url || mediaItem.preview_url || index),
                selectImage: (index, event) => {
                    if(! props.isSelectable) return;
                    event?.stopPropagation?.();
                    if(event?.target?.closest?.('button')) return;
                    context.emit('select', index);
                },
                handleSlideChange: (event) => {
                    activeIndex.value = event.target?.swiper?.activeIndex || 0;
                },
                changeSlide: (direction) => {
                    if(direction < 0) swiperElement.value?.swiper?.slidePrev();
                    else swiperElement.value?.swiper?.slideNext();
                },
                refreshSwiperHeight,
            };
        },
        components: { ContentImage },
    });
</script>

<style scoped>
    .media-image-gallery-slide {
        height: auto;
    }

    .media-image-gallery-control {
        position: absolute;
        top: 50%;
        z-index: 20;
        display: inline-flex;
        width: 2.5rem;
        height: 2.5rem;
        align-items: center;
        justify-content: center;
        transform: translateY(-50%);
        border-radius: 9999px;
        background: rgb(0 0 0 / 58%);
        color: white;
        transition: opacity 150ms ease;
    }

    .media-image-gallery-control:disabled {
        opacity: 0.35;
        cursor: default;
    }

    .media-image-gallery-control:not(:disabled) {
        cursor: pointer;
    }
</style>
