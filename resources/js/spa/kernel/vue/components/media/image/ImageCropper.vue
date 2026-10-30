<template>
    <div class="bg-bg-pr">
        <div class="px-4 py-3 border-b border-b-bord-pr">
            <h3 class="text-par-l font-semibold text-lab-pr">
                {{ cropTitle }}
            </h3>
            <p class="mt-1 text-par-s text-lab-sc">
                Drag to position. Pinch or use the zoom controls to resize.
            </p>
        </div>

        <div class="p-4">
            <div
                ref="frameRef"
                class="relative mx-auto bg-white overflow-hidden select-none touch-none"
                v-bind:class="frameClasses"
                v-bind:style="frameStyle"
                v-on:pointerdown="startDrag"
                v-on:pointermove="dragImage"
                v-on:pointerup="stopDrag"
                v-on:pointercancel="stopDrag"
            >
                <img
                    ref="imageRef"
                    v-bind:src="state.previewUrl"
                    class="absolute left-1/2 top-1/2 max-w-none"
                    v-bind:class="state.isDragging ? 'cursor-grabbing' : 'cursor-grab'"
                    v-bind:style="imageStyle"
                    draggable="false"
                    alt="Crop preview"
                    v-on:load="handleImageLoad"
                >

                <div class="pointer-events-none absolute inset-0 ring-2 ring-white/80" v-bind:class="mode === 'avatar' ? 'rounded-full' : 'rounded-md'"></div>
                <div class="pointer-events-none absolute inset-0 bg-black/0" v-if="! state.imageLoaded">
                    <div class="h-full w-full bg-fill-qt animate-pulse"></div>
                </div>
            </div>
        </div>

        <div class="px-4 pb-4">
            <div class="flex items-center justify-between text-par-s text-lab-sc mb-2">
                <span>Zoom</span>
                <span>{{ zoomLabel }}</span>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="size-11 shrink-0 rounded-full border border-bord-pr text-par-l font-medium text-lab-pr disabled:opacity-40"
                    aria-label="Zoom out"
                    v-bind:disabled="! state.imageLoaded || isBusy || state.zoom <= minZoom + 0.005"
                    v-on:click="zoomOut"
                >−</button>
                <input
                    class="min-w-0 w-full accent-brand-900"
                    type="range"
                    v-bind:min="minZoom"
                    max="4"
                    step="0.01"
                    v-model.number="state.zoom"
                    v-on:input="handleZoomChange"
                    v-bind:disabled="! state.imageLoaded || isBusy"
                    aria-label="Adjust image size"
                >
                <button
                    type="button"
                    class="size-11 shrink-0 rounded-full border border-bord-pr text-par-l font-medium text-lab-pr disabled:opacity-40"
                    aria-label="Zoom in"
                    v-bind:disabled="! state.imageLoaded || isBusy || state.zoom >= 4"
                    v-on:click="zoomIn"
                >+</button>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <button
                    type="button"
                    class="text-par-n font-medium text-lab-sc disabled:opacity-60"
                    v-bind:disabled="! state.imageLoaded || isBusy"
                    v-on:click="resetCrop"
                >
                    Reset
                </button>

                <div class="flex items-center gap-5">
                    <button
                        type="button"
                        class="text-par-n font-medium text-lab-sc disabled:opacity-60"
                        v-bind:disabled="isBusy"
                        v-on:click="$emit('cancel')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="text-par-n font-semibold text-brand-900 disabled:text-lab-sc"
                        v-bind:disabled="! state.imageLoaded || isBusy"
                        v-on:click="exportCroppedImage"
                    >
                        <span v-if="isBusy" class="inline-flex items-center px-2">
                            <span class="colibri-primary-animation"></span>
                        </span>
                        <span v-else>Save</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, reactive, computed, ref, watch, nextTick, onBeforeUnmount } from 'vue';

    export default defineComponent({
        emits: ['cancel', 'save'],
        props: {
            file: {
                type: File,
                required: true
            },
            mode: {
                type: String,
                default: 'avatar'
            },
            isSubmitting: {
                type: Boolean,
                default: false
            }
        },
        setup: function(props, context) {
            const frameRef = ref(null);
            const imageRef = ref(null);

            const state = reactive({
                previewUrl: '',
                imageLoaded: false,
                isDragging: false,
                isExporting: false,
                naturalWidth: 0,
                naturalHeight: 0,
                frameWidth: 0,
                frameHeight: 0,
                displayWidth: 0,
                displayHeight: 0,
                offsetX: 0,
                offsetY: 0,
                zoom: 1,
                dragStartX: 0,
                dragStartY: 0,
                pointerStartX: 0,
                pointerStartY: 0
            });

            let resizeObserver = null;
            const activePointers = new Map();
            let pinchStart = null;

            const cropConfig = computed(() => {
                return (props.mode === 'cover') ? {
                    title: 'Adjust cover photo',
                    width: 1500,
                    height: 500
                } : {
                    title: 'Adjust profile photo',
                    width: 256,
                    height: 256
                };
            });

            const frameClasses = computed(() => {
                return (props.mode === 'cover') ? 'w-full rounded-md' : 'w-full max-w-[360px] rounded-full';
            });

            const frameStyle = computed(() => {
                return {
                    aspectRatio: `${cropConfig.value.width} / ${cropConfig.value.height}`
                };
            });

            const imageStyle = computed(() => {
                return {
                    width: `${state.displayWidth}px`,
                    height: `${state.displayHeight}px`,
                    transform: `translate(calc(-50% + ${state.offsetX}px), calc(-50% + ${state.offsetY}px))`
                };
            });

            const cropTitle = computed(() => {
                return cropConfig.value.title;
            });

            const zoomLabel = computed(() => {
                return `${Math.round(state.zoom * 100)}%`;
            });

            const isBusy = computed(() => {
                return props.isSubmitting || state.isExporting;
            });

            const minZoom = computed(() => {
                if(! state.naturalWidth || ! state.naturalHeight || ! state.frameWidth || ! state.frameHeight) {
                    return 1;
                }

                const coverScale = Math.max(
                    state.frameWidth / state.naturalWidth,
                    state.frameHeight / state.naturalHeight
                );
                const containScale = Math.min(
                    state.frameWidth / state.naturalWidth,
                    state.frameHeight / state.naturalHeight
                );

                // Permit zooming out until the complete source image fits in the crop frame.
                // The remaining area is filled by the same neutral background used on export.
                return Math.min(1, containScale / coverScale);
            });

            const revokePreviewUrl = () => {
                if(state.previewUrl) {
                    URL.revokeObjectURL(state.previewUrl);
                    state.previewUrl = '';
                }
            };

            const preparePreview = () => {
                revokePreviewUrl();
                state.imageLoaded = false;
                state.naturalWidth = 0;
                state.naturalHeight = 0;
                state.zoom = 1;
                state.offsetX = 0;
                state.offsetY = 0;

                if(props.file) {
                    state.previewUrl = URL.createObjectURL(props.file);
                }
            };

            const measureFrame = () => {
                if(frameRef.value) {
                    const frameRect = frameRef.value.getBoundingClientRect();

                    state.frameWidth = frameRect.width;
                    state.frameHeight = frameRect.height;
                }
            };

            const clampOffset = () => {
                // Keep oversized images from exposing blank edges, while still allowing
                // smaller images to move through the crop frame over its white backdrop.
                const limitX = Math.abs(state.displayWidth - state.frameWidth) / 2;
                const limitY = Math.abs(state.displayHeight - state.frameHeight) / 2;

                state.offsetX = Math.min(limitX, Math.max(-limitX, state.offsetX));
                state.offsetY = Math.min(limitY, Math.max(-limitY, state.offsetY));
            };

            const updateDisplaySize = () => {
                if(state.imageLoaded && state.frameWidth && state.frameHeight && state.naturalWidth && state.naturalHeight) {
                    const baseScale = Math.max(state.frameWidth / state.naturalWidth, state.frameHeight / state.naturalHeight);
                    const displayScale = baseScale * state.zoom;

                    state.displayWidth = state.naturalWidth * displayScale;
                    state.displayHeight = state.naturalHeight * displayScale;

                    clampOffset();
                }
            };

            const resetCrop = () => {
                state.zoom = 1;
                state.offsetX = 0;
                state.offsetY = 0;

                measureFrame();
                updateDisplaySize();
            };

            const handleImageLoad = () => {
                if(imageRef.value) {
                    state.naturalWidth = imageRef.value.naturalWidth;
                    state.naturalHeight = imageRef.value.naturalHeight;
                    state.imageLoaded = true;

                    nextTick(() => {
                        resetCrop();
                    });
                }
            };

            const handleZoomChange = () => {
                state.zoom = Math.min(4, Math.max(minZoom.value, Number(state.zoom) || minZoom.value));
                measureFrame();
                updateDisplaySize();
            };

            const setZoom = (zoom) => {
                state.zoom = Math.min(4, Math.max(minZoom.value, zoom));
                measureFrame();
                updateDisplaySize();
            };

            const zoomIn = () => setZoom(state.zoom + 0.1);
            const zoomOut = () => setZoom(state.zoom - 0.1);

            const pointerDistance = (first, second) => {
                return Math.hypot(second.x - first.x, second.y - first.y);
            };

            const pointerCenter = (first, second) => {
                return {
                    x: (first.x + second.x) / 2,
                    y: (first.y + second.y) / 2
                };
            };

            const capturePointer = (event) => {
                if(frameRef.value && frameRef.value.setPointerCapture) {
                    try {
                        frameRef.value.setPointerCapture(event.pointerId);
                    }
                    catch (error) {}
                }
            };

            const startDrag = (event) => {
                if(! state.imageLoaded || isBusy.value) {
                    return;
                }

                event.preventDefault();
                activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                capturePointer(event);

                if(activePointers.size === 1) {
                    state.isDragging = true;
                    state.dragStartX = state.offsetX;
                    state.dragStartY = state.offsetY;
                    state.pointerStartX = event.clientX;
                    state.pointerStartY = event.clientY;
                    pinchStart = null;
                    return;
                }

                if(activePointers.size >= 2) {
                    const [first, second] = [...activePointers.values()];
                    const center = pointerCenter(first, second);

                    state.isDragging = false;
                    pinchStart = {
                        distance: Math.max(pointerDistance(first, second), 1),
                        zoom: state.zoom,
                        centerX: center.x,
                        centerY: center.y,
                        offsetX: state.offsetX,
                        offsetY: state.offsetY
                    };
                }
            };

            const dragImage = (event) => {
                if(! activePointers.has(event.pointerId)) {
                    return;
                }

                event.preventDefault();
                activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

                if(activePointers.size >= 2 && pinchStart) {
                    const [first, second] = [...activePointers.values()];
                    const center = pointerCenter(first, second);
                    const distance = pointerDistance(first, second);

                    setZoom(pinchStart.zoom * (distance / pinchStart.distance));
                    state.offsetX = pinchStart.offsetX + (center.x - pinchStart.centerX);
                    state.offsetY = pinchStart.offsetY + (center.y - pinchStart.centerY);
                    clampOffset();
                }
                else if(state.isDragging) {
                    state.offsetX = state.dragStartX + (event.clientX - state.pointerStartX);
                    state.offsetY = state.dragStartY + (event.clientY - state.pointerStartY);

                    clampOffset();
                }
            };

            const stopDrag = (event) => {
                if(event && activePointers.has(event.pointerId)) {
                    activePointers.delete(event.pointerId);

                    if(frameRef.value && frameRef.value.releasePointerCapture) {
                        try {
                            frameRef.value.releasePointerCapture(event.pointerId);
                        }

                        catch (error) {}
                    }
                }

                pinchStart = null;

                if(activePointers.size === 1 && state.imageLoaded && ! isBusy.value) {
                    const [pointer] = activePointers.values();
                    state.isDragging = true;
                    state.dragStartX = state.offsetX;
                    state.dragStartY = state.offsetY;
                    state.pointerStartX = pointer.x;
                    state.pointerStartY = pointer.y;
                }
                else {
                    state.isDragging = false;
                }
            };

            const exportCroppedImage = () => {
                if(! imageRef.value || ! state.imageLoaded || isBusy.value) {
                    return false;
                }

                state.isExporting = true;
                measureFrame();
                updateDisplaySize();

                const canvas = document.createElement('canvas');
                const context2d = canvas.getContext('2d');
                const outputScaleX = cropConfig.value.width / state.frameWidth;
                const outputScaleY = cropConfig.value.height / state.frameHeight;
                const outputWidth = state.displayWidth * outputScaleX;
                const outputHeight = state.displayHeight * outputScaleY;
                const outputX = ((state.frameWidth - state.displayWidth) / 2 + state.offsetX) * outputScaleX;
                const outputY = ((state.frameHeight - state.displayHeight) / 2 + state.offsetY) * outputScaleY;

                canvas.width = cropConfig.value.width;
                canvas.height = cropConfig.value.height;

                context2d.fillStyle = '#ffffff';
                context2d.fillRect(0, 0, canvas.width, canvas.height);
                context2d.drawImage(imageRef.value, outputX, outputY, outputWidth, outputHeight);

                canvas.toBlob((blob) => {
                    state.isExporting = false;

                    if(blob) {
                        const fileName = `${props.mode}-${Date.now()}.jpg`;
                        const croppedFile = new File([blob], fileName, {
                            type: 'image/jpeg'
                        });

                        context.emit('save', croppedFile);
                    }
                    else {
                        alert('Could not prepare image. Please try again.');
                    }
                }, 'image/jpeg', 0.94);
            };

            watch(() => props.file, () => {
                preparePreview();
            }, {
                immediate: true
            });

            watch(() => props.mode, () => {
                nextTick(() => {
                    resetCrop();
                });
            });

            nextTick(() => {
                if(frameRef.value && window.ResizeObserver) {
                    resizeObserver = new ResizeObserver(() => {
                        measureFrame();
                        updateDisplaySize();
                    });

                    resizeObserver.observe(frameRef.value);
                }
            });

            onBeforeUnmount(() => {
                revokePreviewUrl();

                if(resizeObserver) {
                    resizeObserver.disconnect();
                }
            });

            return {
                state: state,
                frameRef: frameRef,
                imageRef: imageRef,
                frameClasses: frameClasses,
                frameStyle: frameStyle,
                imageStyle: imageStyle,
                cropTitle: cropTitle,
                zoomLabel: zoomLabel,
                isBusy: isBusy,
                minZoom: minZoom,
                resetCrop: resetCrop,
                handleImageLoad: handleImageLoad,
                handleZoomChange: handleZoomChange,
                zoomIn: zoomIn,
                zoomOut: zoomOut,
                startDrag: startDrag,
                dragImage: dragImage,
                stopDrag: stopDrag,
                exportCroppedImage: exportCroppedImage
            };
        }
    });
</script>
