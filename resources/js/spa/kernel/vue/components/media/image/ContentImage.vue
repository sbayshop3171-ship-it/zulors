<template>
    <div class="content-image-frame relative w-full overflow-hidden bg-fill-tr" v-bind:style="frameStyle">
        <img
            v-if="fitToViewport && imageSource"
            v-bind:src="imageSource"
            class="content-image-backdrop"
            alt=""
            aria-hidden="true"
        >
        <ProgressiveImageLoader
            v-bind:base64Image="base64Image || mediaItem?.lqip_base64"
            v-bind:src="imageSource"
            v-bind:isSensitive="isSensitive"
            v-bind:loading="loading"
            v-bind:fetchPriority="fetchPriority"
            v-bind:alt="alt"
            class="content-image-loader"
            v-bind:class="{ 'bg-transparent': fitToViewport }"
            v-on:load="captureDimensions"
        ></ProgressiveImageLoader>
        <slot></slot>
    </div>
</template>

<script>
    import { computed, defineComponent, ref, watch } from 'vue';
    import { getImageAspectRatio } from '@/kernel/services/media/image-presentation.js';
    import ProgressiveImageLoader from '@/kernel/vue/components/media/image/ProgressiveImageLoader.vue';

    export default defineComponent({
        emits: ['load'],
        props: {
            mediaItem: { type: Object, default: () => ({}) },
            src: { type: String, default: '' },
            base64Image: { type: String, default: '' },
            alt: { type: String, default: 'Image' },
            isSensitive: { type: Boolean, default: false },
            loading: { type: String, default: 'lazy' },
            fetchPriority: { type: String, default: 'auto' },
            fitToViewport: { type: Boolean, default: false },
        },
        setup(props, context) {
            const loadedDimensions = ref({});
            const imageSource = computed(() => props.src
                || props.mediaItem?.source_url
                || props.mediaItem?.preview_url
                || props.mediaItem?.metadata?.preview_url
                || '');
            const frameStyle = computed(() => ({
                aspectRatio: String(getImageAspectRatio(props.mediaItem, loadedDimensions.value)),
                maxHeight: props.fitToViewport ? '55dvh' : undefined,
            }));

            watch(imageSource, () => {
                loadedDimensions.value = {};
            });

            return {
                imageSource,
                frameStyle,
                captureDimensions: (event) => {
                    const image = event?.target;
                    if(! image?.naturalWidth || ! image?.naturalHeight) return;

                    loadedDimensions.value = { width: image.naturalWidth, height: image.naturalHeight };
                    context.emit('load', {
                        width: image.naturalWidth,
                        height: image.naturalHeight,
                        event,
                    });
                },
            };
        },
        components: { ProgressiveImageLoader },
    });
</script>

<style scoped>
    .content-image-frame {
        aspect-ratio: 4 / 3;
    }

    .content-image-backdrop {
        position: absolute;
        inset: -1rem;
        display: block;
        width: calc(100% + 2rem);
        height: calc(100% + 2rem);
        object-fit: cover;
        filter: blur(1rem);
        opacity: 0.72;
        pointer-events: none;
    }

    .content-image-loader {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        min-height: 0;
    }

    .content-image-loader :deep(img) {
        display: block;
        width: 100%;
        height: 100%;
        min-height: 0;
        object-fit: contain;
    }
</style>
