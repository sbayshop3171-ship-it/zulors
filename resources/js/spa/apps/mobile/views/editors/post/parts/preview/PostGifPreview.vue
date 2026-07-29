<template>
    <div v-for="mediaItem in postMedia" v-bind:key="mediaItem.id" class="overflow-hidden min-h-44 relative">

        <img v-bind:src="mediaItem.metadata.preview_url" class="w-full block" alt="Image">
        
        <div v-if="canDelete" class="absolute top-3 right-3 inline-block z-10">
            <MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
        </div>

        <div class="absolute bottom-2 left-2 inline-block z-10">
            <GiphyAttribution></GiphyAttribution>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    import MediaDeleteButton from '@M/views/editors/post/parts/buttons/MediaDeleteButton.vue';
    import GiphyAttribution from '@/kernel/vue/components/attributes/GiphyAttribution.vue';

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
	        setup: function(props) {
	            return {
	                canDelete: computed(() => {
	                    return props.canDelete;
	                })
	            };
	        },
	        components: {
            MediaDeleteButton: MediaDeleteButton,
            GiphyAttribution: GiphyAttribution
        }
    });
</script>
