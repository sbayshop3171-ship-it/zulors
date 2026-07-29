<template>
	<div class="relative">
		<LinkSnapshot v-bind:linkSnapshot="linkSnapshot"></LinkSnapshot>

		<div v-if="canDelete" class="absolute top-4 right-4 inline-block">
			<MediaDeleteButton v-on:click="$emit('delete')"></MediaDeleteButton>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import MediaDeleteButton from '@D/components/timeline/editor/buttons/MediaDeleteButton.vue';
	import LinkSnapshot from '@D/components/media/links/LinkSnapshot.vue';

	export default defineComponent({
		props: {
			linkSnapshot: {
				type: Object,
				required: true
			},
			canDelete: {
				type: Boolean,
				default: true
			}
		},
		emits: ['delete'],
		components: {
			MediaDeleteButton: MediaDeleteButton,
			LinkSnapshot: LinkSnapshot
		}
	});
</script>
