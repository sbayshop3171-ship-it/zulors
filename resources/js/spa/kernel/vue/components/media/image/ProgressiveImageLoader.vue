<template>
    <div class="relative block size-full min-h-20 bg-bg-pr overflow-hidden">
        <img
            v-if="base64Image && ! isLoaded"
            v-bind:src="base64Image"
            v-bind="$attrs"
            class="absolute inset-0 size-full object-cover"
        alt="Image">
        <div v-show="isSensitive" class="absolute inset-0">
            <SensitiveContentAlert></SensitiveContentAlert>
        </div>
        <img
            ref="imageRef"
            v-bind:src="src"
            v-bind="$attrs"
            v-bind:loading="loading"
            decoding="async"
            v-bind:fetchpriority="fetchPriority"
            class="relative z-10"
        alt="Image">
    </div>
</template>

<script>
    import { defineComponent, ref, onMounted, onUnmounted } from 'vue';
    
    import SensitiveContentAlert from '@D/components/media/SensitiveContentAlert.vue';

    export default defineComponent({
        emits: ['load', 'error'],
        props: {
            base64Image: {
                type: String,
                default: ''
            },
            src: {
                type: String,
                default: ''
            },
            isSensitive: {
                type: Boolean,
                default: false
            },
            loading: {
                type: String,
                default: 'lazy',
                validator: (value) => ['eager', 'lazy'].includes(value)
            },
            fetchPriority: {
                type: String,
                default: 'auto',
                validator: (value) => ['high', 'low', 'auto'].includes(value)
            }
        },
        setup: function(props, context) {
            const isLoaded = ref(false);
            const imageRef = ref(null);

            onMounted(() => {
                if(imageRef.value?.complete) {
                    isLoaded.value = true;

                    if(imageRef.value.naturalWidth && imageRef.value.naturalHeight) {
                        context.emit('load', { target: imageRef.value });
                    }

                    return;
                }

                imageRef.value.onload = (event) => {
                    isLoaded.value = true;
                    context.emit('load', event);
                }

                imageRef.value.onerror = (event) => {
                    isLoaded.value = true;
                    context.emit('error', event);
                }
            });

            onUnmounted(() => {
                if (imageRef.value) {
                    imageRef.value.onload = null;
                    imageRef.value.onerror = null;
                }
            });

            return {
                isLoaded: isLoaded,
                imageRef: imageRef
            };
        },
        components: {
            SensitiveContentAlert: SensitiveContentAlert
        }
    });
</script>
