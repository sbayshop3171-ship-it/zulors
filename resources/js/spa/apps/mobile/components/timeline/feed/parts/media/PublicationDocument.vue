<template>
	<div v-for="mediaItem in postMedia" v-bind:key="mediaItem.id" class="overflow-hidden py-1 px-4">
		<div class="flex items-center leading-none" v-bind:class="{ 'opacity-50': mediaItem.deleted }">
			<div class="size-10 shrink-0">
				<FileFormatIcon v-bind:extension="mediaItem.extension"></FileFormatIcon>
			</div>
			<div class="flex-1 pr-3 overflow-hidden pl-1">
				<span class="text-lab-pr2 text-par-s block font-medium mb-1.5 truncate capitalize">
					{{ mediaItem.metadata.file_name }}
				</span>
				<span class="text-lab-sc text-cap-l block truncate">
					<span class="uppercase">
						{{ $filters.fileSize(mediaItem.size) }} - {{ mediaItem.extension }}
	
						<span v-if="mediaItem.metadata.downloads" v-bind:title="$t('labels.downloads_count', { count: mediaItem.metadata.downloads ?? 2 })">
							/ {{ mediaItem.metadata.downloads }} &DownArrow;
						</span>
					</span>
					-
					<a class="text-brand-900" v-bind:href="mediaItem.source_url" v-bind:download="mediaItem.metadata.file_name">
						{{ $t('labels.download') }}
					</a>
				</span>
			</div>
		</div>
	</div>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

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