<template>
    <div class="block">
        <div v-for="mediaItem in postMedia" v-bind:key="mediaItem.id" class="overflow-hidden border border-bord-card rounded-2xl">
            <div class="flex items-center leading-none p-2" v-bind:class="{ 'opacity-50': mediaItem.deleted }">
                <div class="size-10 shrink-0">
                    <FileFormatIcon v-bind:extension="mediaItem.extension"></FileFormatIcon>
                </div>
                <div class="flex-1 pr-3 overflow-hidden pl-1">
                    <span class="text-lab-pr2 text-par-s block font-medium mb-1.5 truncate capitalize">
                        {{ mediaItem.metadata.file_name }}
                    </span>
                    <span class="text-lab-sc text-cap-l uppercase block truncate">
                        {{ $filters.fileSize(mediaItem.size) }} - {{ mediaItem.extension }}

                        <span v-if="mediaItem.metadata.downloads" v-bind:title="$t('labels.downloads_count', { count: mediaItem.metadata.downloads })">
                            / {{ mediaItem.metadata.downloads }} &DownArrow;
                        </span>
                    </span>
                </div>
                <div class="shrink-0">
                    <a v-bind:href="mediaItem.source_url" v-bind:download="mediaItem.metadata.file_name">
                        <PrimaryIconButton
                            hoverText="hover:text-brand-900"
                            iconName="download-01"
                            iconSize="icon-small"
                        iconType="line"></PrimaryIconButton>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        props: {
            postMedia: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            const postMedia = computed(() => {
                return props.postMedia;
            });

            return {
                postMedia: postMedia
            };
        },
        components: {
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>