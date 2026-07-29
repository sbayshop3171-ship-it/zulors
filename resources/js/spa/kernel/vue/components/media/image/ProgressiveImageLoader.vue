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
            loading="eager"
            decoding="async"
            fetchpriority="high"
            class="relative z-10"
        alt="Image">
    </div>
</template>

<script>
    import { defineComponent, ref, onMounted, onUnmounted } from 'vue';
    
    import SensitiveContentAlert from '@D/components/media/SensitiveContentAlert.vue';

    export default defineComponent({
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
            }
        },
        setup: function(props) {
            const isLoaded = ref(false);
            const imageRef = ref(null);

            onMounted(() => {
                if(imageRef.value?.complete) {
                    isLoaded.value = true;

                    return;
                }

                imageRef.value.onload = () => {
                    isLoaded.value = true;
                }

                imageRef.value.onerror = () => {
                    isLoaded.value = true;
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
