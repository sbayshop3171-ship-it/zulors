<template>
    <div v-for="mediaItem in postMedia" v-bind:key="mediaItem.id" class="overflow-hidden min-h-44 border border-bord-card bg-fill-qt rounded-2xl relative">

        <img v-bind:src="mediaItem.metadata.preview_url" class="w-full block" alt="Image">
        
        <MediaBlurOverlay v-if="mediaItem.deleted"></MediaBlurOverlay>
        <div v-else-if="canDelete" class="absolute top-4 right-4 inline-block z-10">
            <MediaDeleteButton v-on:click="deleteMedia(mediaItem)"></MediaDeleteButton>
        </div>

        <div class="absolute bottom-2 left-2 inline-block z-10">
            <GiphyAttribution></GiphyAttribution>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    import MediaDeleteButton from '@D/components/timeline/editor/buttons/MediaDeleteButton.vue';
    import GiphyAttribution from '@/kernel/vue/components/attributes/GiphyAttribution.vue';

    import MediaBlurOverlay from '@D/components/timeline/editor/animations/MediaBlurOverlay.vue';

    export default defineComponent({
        props: {
            postMedia: {
                type: Object,
                default: {}
            },
            canDelete: {
                type: Boolean,
                default: true
            }
        },
        emits: ['delete'],
        setup: function(props, context) {
            const postMedia = computed(() => {
                return props.postMedia;
            });

            return {
                postMedia: postMedia,
                canDelete: computed(() => {
                    return props.canDelete;
                }),
                deleteMedia: (mediaItem) => {
                    context.emit('delete', mediaItem);
                }
            };
        },
        components: {
            MediaDeleteButton: MediaDeleteButton,
            GiphyAttribution: GiphyAttribution,
            MediaBlurOverlay: MediaBlurOverlay
        }
    });
</script>
