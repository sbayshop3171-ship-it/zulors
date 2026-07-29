<template>
    <div v-for="(mediaItem, idx) in postMedia" v-bind:key="mediaItem.id" class="overflow-hidden border border-bord-card rounded-2xl">
        <div class="flex items-center leading-none p-2" v-bind:class="{ 'opacity-50': mediaItem.deleted }">
            <div class="size-10 shrink-0">
                <FileFormatIcon v-bind:extension="mediaItem.extension"></FileFormatIcon>
            </div>
            <div class="flex-1 pr-3 overflow-hidden pl-1">
                <span class="text-lab-pr2 text-par-s block font-medium mb-1 truncate capitalize">
                    {{ mediaItem.metadata.file_name }}
                </span>
                <span class="text-lab-sc text-cap-l uppercase block truncate">
                    {{ $filters.fileSize(mediaItem.size) }} / {{ mediaItem.extension }}

                    <template v-if="PostTypeUtils.isAudio(mediaItem.type)">
                        / {{ $filters.mediaDuration(mediaItem.metadata.duration) }}
                    </template>
                </span>
            </div>
            <div v-if="canDelete" class="shrink-0 pr-2" v-bind:class="{ 'invisible': mediaItem.deleted }">
                <MediaDeleteButton v-on:click="deleteDocument(mediaItem)"></MediaDeleteButton>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { PostTypeUtils } from '@/kernel/enums/post/post.type.js';

    import MediaDeleteButton from '@D/components/timeline/editor/buttons/MediaDeleteButton.vue';

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
                deleteDocument: (mediaItem) => {
                    context.emit('delete', mediaItem);
                },
                canDelete: computed(() => {
                    return props.canDelete;
                }),
                PostTypeUtils: PostTypeUtils
            };
        },
        components: {
            MediaDeleteButton: MediaDeleteButton
        }
    });
</script>
