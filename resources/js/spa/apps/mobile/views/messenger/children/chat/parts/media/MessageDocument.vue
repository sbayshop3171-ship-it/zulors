<template>
    <a v-bind:href="mediaData.source_url" v-bind:download="fileName" class="block min-w-56 rounded-xl bg-bg-pr/70 p-2 text-lab-pr2">
        <div class="flex items-center gap-2 overflow-hidden">
            <div class="size-10 shrink-0">
                <FileFormatIcon v-bind:extension="mediaData.extension"></FileFormatIcon>
            </div>
            <div class="min-w-0 flex-1">
                <span class="block truncate text-par-s font-medium">{{ fileName }}</span>
                <span class="block truncate text-cap-l uppercase text-lab-sc">
                    {{ $filters.fileSize(mediaData.size) }} - {{ mediaData.extension }}
                </span>
            </div>
            <span class="size-icon-small shrink-0 text-brand-900">
                <SvgIcon type="line" name="download-01"></SvgIcon>
            </span>
        </div>
    </a>
</template>

<script>
    import { computed, defineComponent } from 'vue';

    export default defineComponent({
        props: {
            mediaData: {
                type: Object,
                required: true
            }
        },
        setup: function(props) {
            return {
                fileName: computed(() => {
                    return props.mediaData.metadata?.file_name || `${__t('labels.document')}.${props.mediaData.extension}`;
                })
            };
        }
    });
</script>
