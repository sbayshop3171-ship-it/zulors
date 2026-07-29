<template>
    <div class="bg-bg-pr">
        <div class="px-4 py-3 border-b border-b-bord-pr">
            <h3 class="text-par-l font-semibold text-lab-pr">
                {{ cropTitle }}
            </h3>
            <p class="mt-1 text-par-s text-lab-sc">
                Drag the image to position it, then adjust zoom.
            </p>
        </div>

        <div class="p-4">
            <div
                ref="frameRef"
                class="relative mx-auto bg-black overflow-hidden select-none touch-none"
                v-bind:class="frameClasses"
                v-bind:style="frameStyle"
                v-on:pointerdown="startDrag"
                v-on:pointermove="dragImage"
                v-on:pointerup="stopDrag"
                v-on:pointercancel="stopDrag"
                v-on:pointerleave="stopDrag"
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
            <input
                class="w-full accent-brand-900"
                type="range"
                min="1"
                max="4"
                step="0.01"
                v-model.number="state.zoom"
                v-on:input="handleZoomChange"
                v-bind:disabled="! state.imageLoaded || isBusy"
            >

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
                const limitX = Math.max(0, (state.displayWidth - state.frameWidth) / 2);
                const limitY = Math.max(0, (state.displayHeight - state.frameHeight) / 2);

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
                measureFrame();
                updateDisplaySize();
            };

            const startDrag = (event) => {
                if(state.imageLoaded && ! isBusy.value) {
                    state.isDragging = true;
                    state.dragStartX = state.offsetX;
                    state.dragStartY = state.offsetY;
                    state.pointerStartX = event.clientX;
                    state.pointerStartY = event.clientY;

                    if(frameRef.value.setPointerCapture) {
                        frameRef.value.setPointerCapture(event.pointerId);
                    }
                }
            };

            const dragImage = (event) => {
                if(state.isDragging) {
                    state.offsetX = state.dragStartX + (event.clientX - state.pointerStartX);
                    state.offsetY = state.dragStartY + (event.clientY - state.pointerStartY);

                    clampOffset();
                }
            };

            const stopDrag = (event) => {
                if(state.isDragging) {
                    state.isDragging = false;

                    if(frameRef.value && frameRef.value.releasePointerCapture) {
                        try {
                            frameRef.value.releasePointerCapture(event.pointerId);
                        }

                        catch (error) {}
                    }
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
                const scale = state.displayWidth / state.naturalWidth;
                const sourceWidth = state.frameWidth / scale;
                const sourceHeight = state.frameHeight / scale;
                const sourceX = (state.naturalWidth / 2) - (state.offsetX / scale) - (sourceWidth / 2);
                const sourceY = (state.naturalHeight / 2) - (state.offsetY / scale) - (sourceHeight / 2);

                canvas.width = cropConfig.value.width;
                canvas.height = cropConfig.value.height;

                context2d.fillStyle = '#ffffff';
                context2d.fillRect(0, 0, canvas.width, canvas.height);
                context2d.drawImage(
                    imageRef.value,
                    Math.max(0, Math.min(state.naturalWidth - sourceWidth, sourceX)),
                    Math.max(0, Math.min(state.naturalHeight - sourceHeight, sourceY)),
                    sourceWidth,
                    sourceHeight,
                    0,
                    0,
                    canvas.width,
                    canvas.height
                );

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
                resetCrop: resetCrop,
                handleImageLoad: handleImageLoad,
                handleZoomChange: handleZoomChange,
                startDrag: startDrag,
                dragImage: dragImage,
                stopDrag: stopDrag,
                exportCroppedImage: exportCroppedImage
            };
        }
    });
</script>
