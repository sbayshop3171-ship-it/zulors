<template>
    <div class="overflow-hidden min-h-44 border border-bord-card bg-fill-qt rounded-2xl relative cursor-pointer">
        <img v-bind:src="mediaItem.source_url" class="block size-full object-cover" alt="Image"/>

        <div class="absolute bottom-2 left-2 inline-block z-10">
            <GiphyAttribution></GiphyAttribution>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import GiphyAttribution from '@/kernel/vue/components/attributes/GiphyAttribution.vue';

    export default defineComponent({
        props: {
            postMedia: {
                type: Array,
                default: {}
            }
        },
        setup: function(props) {
            const mediaItem = computed(() => {
                return props.postMedia[0];
            });
            
            return {
                mediaItem: mediaItem
            }
        },
        components: {
            GiphyAttribution: GiphyAttribution
        }
    });
</script>