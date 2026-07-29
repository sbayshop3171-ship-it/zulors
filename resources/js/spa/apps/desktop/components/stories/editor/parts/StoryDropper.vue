<template>
	<div
		v-on:drop.prevent="handleFileDrop"
		v-on:dragenter.prevent="state.isDragging = true"
		v-on:dragover.prevent="state.isDragging = true"
		v-on:dragleave.prevent="state.isDragging = false"
		v-on:dragend.prevent="state.isDragging = false"
	class="shadow-xs popup-background-tr rounded-md p-2 h-full group">
		<div v-bind:class="[state.isDragging ? 'border-brand-900' : 'border-edge-pr hover:border-brand-900']" class="flex flex-col justify-center cursor-pointer border border-dashed h-full rounded-md smoothing">
			<div class="text-center mt-auto">
				<div class="flex justify-center group-hover:-translate-y-2 smoothing">
					<img class="size-24" v-bind:src="$asset('assets/icons/upload.png')" alt="Image">
				</div>
				<h5 class="text-par-n text-lab-pr2">
					{{ $t('story.story_drop_zone.title') }}
				</h5>
			</div>
			<p class="text-center text-par-s text-lab-sc mt-auto mb-3">
				{{ $t('story.story_drop_zone.desc') }}
			</p>
		</div>
	</div>
</template>

<script>
	import { defineComponent, reactive } from 'vue';

	export default defineComponent({
		emits: ['upload'],
		setup: function(props, context) {
			const state = reactive({
				isDragging: false
			});

			return {
				state: state,
				handleFileDrop: (event) => {
					state.isDragging = false;

					const droppedFiles = event.dataTransfer.files;

					if (droppedFiles.length) {
						context.emit('upload', droppedFiles[0]);
					}
				}
			};
		}
	});
</script>