<template>
    <div ref="stage" class="story-image-canvas" v-on:pointerdown="pointerDown" v-on:pointermove="pointerMove" v-on:pointerup="pointerUp" v-on:pointercancel="pointerUp" v-on:wheel.prevent="wheelZoom" v-on:dblclick="doubleTap">
        <img v-bind:src="src" class="story-media-backdrop" alt="" aria-hidden="true">
        <div class="story-media-backdrop-shade"></div>
        <img ref="foreground" v-bind:src="src" class="story-image-canvas__foreground" v-bind:style="foregroundStyle" v-on:load="loaded" alt="Image">
        <button v-if="!isDefault" type="button" class="story-image-canvas__reset" aria-label="Reset image position" v-on:pointerdown.stop v-on:click.stop="reset">↺</button>
    </div>
</template>

<script>
import { defineComponent, computed, ref } from 'vue';

const DEFAULT_TRANSFORM = { scale: 1, translateX: 0, translateY: 0, rotation: 0 };

export default defineComponent({
    props: {
        src: { type: String, required: true },
        modelValue: { type: Object, default: () => ({ ...DEFAULT_TRANSFORM }) }
    },
    emits: ['update:modelValue', 'load'],
    setup(props, { emit }) {
        const stage = ref(null);
        const foreground = ref(null);
        const activePointers = new Map();
        let dragOrigin = null;
        let pinchOrigin = null;

        const transform = computed(() => ({ ...DEFAULT_TRANSFORM, ...(props.modelValue || {}) }));
        const isDefault = computed(() => transform.value.scale === 1 && transform.value.translateX === 0 && transform.value.translateY === 0);
        const foregroundStyle = computed(() => ({
            transform: `translate(-50%, -50%) translate3d(${transform.value.translateX}px, ${transform.value.translateY}px, 0) scale(${transform.value.scale}) rotate(${transform.value.rotation}deg)`
        }));

        const emitTransform = (next) => emit('update:modelValue', { ...transform.value, ...next });
        const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
        const bounds = () => {
            const box = stage.value?.getBoundingClientRect();
            const image = foreground.value;
            if(! box || ! image) return { x: Infinity, y: Infinity };
            const ratio = Math.min(box.width / (image.naturalWidth || box.width), box.height / (image.naturalHeight || box.height));
            const width = (image.naturalWidth || box.width) * ratio * transform.value.scale;
            const height = (image.naturalHeight || box.height) * ratio * transform.value.scale;
            return { x: Math.max(0, (width - box.width) / 2), y: Math.max(0, (height - box.height) / 2) };
        };
        const pan = (x, y) => {
            const limit = bounds();
            emitTransform({ translateX: clamp(x, -limit.x, limit.x), translateY: clamp(y, -limit.y, limit.y) });
        };
        const zoomAt = (delta) => {
            const scale = clamp(transform.value.scale + delta, 1, 4);
            emitTransform({ scale });
            requestAnimationFrame(() => pan(transform.value.translateX, transform.value.translateY));
        };
        const pointerDown = (event) => {
            event.currentTarget.setPointerCapture?.(event.pointerId);
            activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            if(activePointers.size === 1) dragOrigin = { x: event.clientX, y: event.clientY, tx: transform.value.translateX, ty: transform.value.translateY };
            if(activePointers.size === 2) {
                const points = [...activePointers.values()];
                pinchOrigin = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
            }
        };
        const pointerMove = (event) => {
            if(! activePointers.has(event.pointerId)) return;
            activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            if(activePointers.size === 2) {
                const points = [...activePointers.values()];
                const distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
                if(pinchOrigin) zoomAt((distance - pinchOrigin) / 240);
                pinchOrigin = distance;
                return;
            }
            if(dragOrigin) pan(dragOrigin.tx + event.clientX - dragOrigin.x, dragOrigin.ty + event.clientY - dragOrigin.y);
        };
        const pointerUp = (event) => {
            activePointers.delete(event.pointerId);
            if(activePointers.size < 2) pinchOrigin = null;
            if(activePointers.size === 0) dragOrigin = null;
        };
        const wheelZoom = (event) => zoomAt(event.deltaY > 0 ? -0.12 : 0.12);
        const doubleTap = () => zoomAt(transform.value.scale > 1 ? 1 - transform.value.scale : 0.5);
        const reset = () => emit('update:modelValue', { ...DEFAULT_TRANSFORM });
        const loaded = (event) => emit('load', event);

        return { stage, foreground, foregroundStyle, isDefault, pointerDown, pointerMove, pointerUp, wheelZoom, doubleTap, reset, loaded };
    }
});
</script>
