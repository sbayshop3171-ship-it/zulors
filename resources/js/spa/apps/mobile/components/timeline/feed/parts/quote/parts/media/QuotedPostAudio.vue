<template>
	<div class="overflow-hidden border-t border-bord-card">
		<div v-for="mediaItem in postMedia" v-bind:key="mediaItem.id" class="flex items-center justify-between px-4 py-3">
			<div class="size-8 shrink-0">
                <FileFormatIcon extension="mp3"></FileFormatIcon>
            </div>
			<div class="flex-1 overflow-hidden mx-1">
				<h4 class="text-lab-pr2 text-par-s font-medium mb-0.5 truncate">
					{{ mediaItem.metadata.file_name }}
				</h4>
				<p class="text-cap-l text-lab-sc">
					{{ $filters.fileSize(mediaItem.size) }} - {{ $filters.mediaDuration(mediaItem.metadata.duration) }}
				</p>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			postMedia: {
				type: Object,
				required: true
			}
		}
	});
</script>