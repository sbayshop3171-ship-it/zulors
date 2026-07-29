<template>
    <div v-for="(imageGroup, index) in postMedia" v-bind:key="index">
        <div v-if="imageGroup.length == 1" class="block">
            <div v-for="imageItem in imageGroup" v-bind:key="imageItem.id" class="overflow-hidden" v-bind:class="[postMedia.length > 1 ? 'mt-0.5' : '' ]">
                <ProgressiveImageLoader 
                    v-bind:base64Image="imageItem.lqip_base64"
                    v-bind:src="imageItem.source_url"
                    v-bind:isSensitive="isSensitive"
                class="block size-full object-cover" alt="Image"></ProgressiveImageLoader>
            </div>
        </div>
        <div v-else-if="imageGroup.length == 2">
            <div class="grid grid-cols-2 gap-0.5">
                <div v-for="imageItem in imageGroup" v-bind:key="imageItem.id" class="overflow-hidden max-h-96">
                    <ProgressiveImageLoader 
                        v-bind:base64Image="imageItem.lqip_base64"
                        v-bind:src="imageItem.source_url"
                        v-bind:isSensitive="isSensitive"
                    class="block size-full object-cover" alt="Image"></ProgressiveImageLoader>
                </div>
            </div>
        </div>
        <div v-else-if="imageGroup.length == 3">
            <div class="grid grid-cols-3 gap-0.5">
                <div v-for="(imageItem, idx) in imageGroup" 
                    v-bind:key="imageItem.id"
                    class="aspect-square overflow-hidden" 
                v-bind:class="{'col-span-2 row-span-2': (idx == 0)}">

                    <ProgressiveImageLoader 
                        v-bind:base64Image="imageItem.lqip_base64"
                        v-bind:src="imageItem.source_url"
                        v-bind:isSensitive="isSensitive"
                    class="block size-full object-cover" alt="Image"></ProgressiveImageLoader>
                </div>
            </div>
        </div>
        <div v-else-if="imageGroup.length == 4">
            <div class="grid grid-cols-3 gap-0.5">
                <div 
                    v-for="(imageItem, idx) in imageGroup" 
                    v-bind:key="imageItem.id"
                    class="aspect-square overflow-hidden" 
                v-bind:class="{'col-span-3': (idx == 3)}">

                    <ProgressiveImageLoader 
                        v-bind:base64Image="imageItem.lqip_base64"
                        v-bind:src="imageItem.source_url"
                        v-bind:isSensitive="isSensitive"
                    class="block size-full object-cover" alt="Image"></ProgressiveImageLoader>
                </div>
            </div>
        </div>
        <div v-else-if="imageGroup.length == 5">
            <div class="grid grid-cols-3 gap-0.5 mb-0.5">
                <div 
                    v-for="imageItem in imageGroup.slice(0, 3)"
                    v-bind:key="imageItem.id"
                class="aspect-square overflow-hidden">

                    <ProgressiveImageLoader 
                        v-bind:base64Image="imageItem.lqip_base64"
                        v-bind:src="imageItem.source_url"
                        v-bind:isSensitive="isSensitive"
                    class="block size-full object-cover" alt="Image"></ProgressiveImageLoader>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-0.5">
                <div 
                    v-for="imageItem in imageGroup.slice(3)"
                    v-bind:key="imageItem.id"
                class="col-span-1 overflow-hidden">

                    <ProgressiveImageLoader 
                        v-bind:base64Image="imageItem.lqip_base64"
                        v-bind:src="imageItem.source_url"
                        v-bind:isSensitive="isSensitive"
                    class="block size-full object-cover max-h-60" alt="Image"></ProgressiveImageLoader>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import { defineComponent, computed } from 'vue';
    import { Arr } from '@/kernel/helpers/javascript/index.js';
    import ProgressiveImageLoader from '@/kernel/vue/components/media/image/ProgressiveImageLoader.vue';

    export default defineComponent({
        props: {
            postMedia: {
                type: Array,
                default: {}
            },
            isSensitive: {
                type: Boolean,
                default: false
            }
        },
        setup: function(props) {
            const postMedia = computed(() => {
                return Arr.make(props.postMedia).chunk(5).value();
            });
            
            return {
                postMedia: postMedia
            }
        },
        components: {
            ProgressiveImageLoader: ProgressiveImageLoader
        }
    });
</script>