<template>
    <div v-for="(imageGroup, groupIndex) in imageGroups" v-bind:key="groupIndex">
        <template v-if="layout === 'quote'">
            <div v-if="imageGroup.length > 1" class="grid grid-cols-2 gap-px">
                <div v-for="(imageItem, imageIndex) in imageGroup" v-bind:key="mediaKey(imageItem, imageIndex)" class="aspect-square overflow-hidden" v-on:click="selectImage(groupIndex, imageIndex, $event)">
                    <ProgressiveImageLoader
                        v-bind:base64Image="imageItem.lqip_base64"
                        v-bind:src="imageItem.source_url"
                        v-bind:isSensitive="isSensitive"
                        v-bind:loading="isPriority && groupIndex === 0 && imageIndex === 0 ? 'eager' : 'lazy'"
                        v-bind:fetchPriority="isPriority && groupIndex === 0 && imageIndex === 0 ? 'high' : 'auto'"
                        class="block size-full object-cover"
                        alt="Image"
                    ></ProgressiveImageLoader>
                </div>
            </div>
            <div v-else class="block overflow-hidden" v-on:click="selectImage(groupIndex, 0, $event)">
                <ProgressiveImageLoader
                    v-bind:base64Image="imageGroup[0].lqip_base64"
                    v-bind:src="imageGroup[0].source_url"
                    v-bind:isSensitive="isSensitive"
                    v-bind:loading="isPriority ? 'eager' : 'lazy'"
                    v-bind:fetchPriority="isPriority ? 'high' : 'auto'"
                    class="block size-full object-cover"
                    alt="Image"
                ></ProgressiveImageLoader>
            </div>
        </template>

        <template v-else>
            <div v-if="imageGroup.length === 1" class="block" v-bind:class="{ 'mt-px': groupIndex > 0 }">
                <div class="overflow-hidden" v-on:click="selectImage(groupIndex, 0, $event)">
                    <ProgressiveImageLoader
                        v-bind:base64Image="imageGroup[0].lqip_base64"
                        v-bind:src="imageGroup[0].source_url"
                        v-bind:isSensitive="isSensitive"
                        v-bind:loading="isPriority && groupIndex === 0 ? 'eager' : 'lazy'"
                        v-bind:fetchPriority="isPriority && groupIndex === 0 ? 'high' : 'auto'"
                        class="block size-full object-cover"
                        alt="Image"
                    ></ProgressiveImageLoader>
                </div>
            </div>

            <div v-else-if="imageGroup.length === 2" class="grid grid-cols-2 gap-px">
                <div v-for="(imageItem, imageIndex) in imageGroup" v-bind:key="mediaKey(imageItem, imageIndex)" class="max-h-96 overflow-hidden" v-on:click="selectImage(groupIndex, imageIndex, $event)">
                    <ProgressiveImageLoader
                        v-bind:base64Image="imageItem.lqip_base64"
                        v-bind:src="imageItem.source_url"
                        v-bind:isSensitive="isSensitive"
                        v-bind:loading="isPriority && groupIndex === 0 && imageIndex === 0 ? 'eager' : 'lazy'"
                        v-bind:fetchPriority="isPriority && groupIndex === 0 && imageIndex === 0 ? 'high' : 'auto'"
                        class="block size-full object-cover"
                        alt="Image"
                    ></ProgressiveImageLoader>
                </div>
            </div>

            <div v-else-if="imageGroup.length === 3 || imageGroup.length === 4" class="grid grid-cols-3 gap-px">
                <div
                    v-for="(imageItem, imageIndex) in imageGroup"
                    v-bind:key="mediaKey(imageItem, imageIndex)"
                    class="aspect-square overflow-hidden"
                    v-bind:class="{
                        'col-span-2 row-span-2': imageGroup.length === 3 && imageIndex === 0,
                        'col-span-3': imageGroup.length === 4 && imageIndex === 3,
                    }"
                    v-on:click="selectImage(groupIndex, imageIndex, $event)"
                >
                    <ProgressiveImageLoader
                        v-bind:base64Image="imageItem.lqip_base64"
                        v-bind:src="imageItem.source_url"
                        v-bind:isSensitive="isSensitive"
                        v-bind:loading="isPriority && groupIndex === 0 && imageIndex === 0 ? 'eager' : 'lazy'"
                        v-bind:fetchPriority="isPriority && groupIndex === 0 && imageIndex === 0 ? 'high' : 'auto'"
                        class="block size-full object-cover"
                        alt="Image"
                    ></ProgressiveImageLoader>
                </div>
            </div>

            <template v-else-if="imageGroup.length === 5">
                <div class="mb-px grid grid-cols-3 gap-px">
                    <div v-for="(imageItem, imageIndex) in imageGroup.slice(0, 3)" v-bind:key="mediaKey(imageItem, imageIndex)" class="aspect-square overflow-hidden" v-on:click="selectImage(groupIndex, imageIndex, $event)">
                        <ProgressiveImageLoader
                            v-bind:base64Image="imageItem.lqip_base64"
                            v-bind:src="imageItem.source_url"
                            v-bind:isSensitive="isSensitive"
                            v-bind:loading="isPriority && groupIndex === 0 && imageIndex === 0 ? 'eager' : 'lazy'"
                            v-bind:fetchPriority="isPriority && groupIndex === 0 && imageIndex === 0 ? 'high' : 'auto'"
                            class="block size-full object-cover"
                            alt="Image"
                        ></ProgressiveImageLoader>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-px">
                    <div v-for="(imageItem, localIndex) in imageGroup.slice(3)" v-bind:key="mediaKey(imageItem, localIndex + 3)" class="col-span-1 overflow-hidden" v-on:click="selectImage(groupIndex, localIndex + 3, $event)">
                        <ProgressiveImageLoader
                            v-bind:base64Image="imageItem.lqip_base64"
                            v-bind:src="imageItem.source_url"
                            v-bind:isSensitive="isSensitive"
                            class="block size-full max-h-60 object-cover"
                            alt="Image"
                        ></ProgressiveImageLoader>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>

<script>
    import { computed, defineComponent } from 'vue';
    import ProgressiveImageLoader from '@/kernel/vue/components/media/image/ProgressiveImageLoader.vue';

    export default defineComponent({
        emits: ['select'],
        props: {
            postMedia: { type: Array, default: () => [] },
            isSensitive: { type: Boolean, default: false },
            isPriority: { type: Boolean, default: false },
            isSelectable: { type: Boolean, default: false },
            layout: { type: String, default: 'publication', validator: (value) => ['publication', 'quote'].includes(value) },
        },
        setup(props, context) {
            const visibleMedia = computed(() => Array.isArray(props.postMedia)
                ? props.postMedia.filter((mediaItem) => Boolean(mediaItem?.source_url || mediaItem?.preview_url))
                : []);
            const imageGroups = computed(() => {
                if(props.layout === 'quote') return visibleMedia.value.length ? [visibleMedia.value.slice(0, 2)] : [];
                const groups = [];
                for(let index = 0; index < visibleMedia.value.length; index += 5) {
                    groups.push(visibleMedia.value.slice(index, index + 5));
                }
                return groups;
            });

            return {
                imageGroups,
                mediaKey: (mediaItem, index) => String(mediaItem.preview_key || mediaItem.id || mediaItem.source_url || index),
                selectImage: (groupIndex, imageIndex, event) => {
                    if(!props.isSelectable) return;
                    event?.stopPropagation?.();
                    context.emit('select', groupIndex * (props.layout === 'quote' ? 2 : 5) + imageIndex);
                },
            };
        },
        components: { ProgressiveImageLoader },
    });
</script>
