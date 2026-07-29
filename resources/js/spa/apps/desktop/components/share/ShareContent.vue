<template>
	<div class="block">
		<div class="block">
			<ShareMediaItem v-for="mediaItem in mediaOptions" v-bind:mediaItem="mediaItem" v-bind:link="link"></ShareMediaItem>
		</div>
		<Border></Border>
		<div class="flex justify-center py-4">
			<MarginalTextButton v-on:click="copyLink" v-bind:buttonText="$t('labels.copy_link')"></MarginalTextButton>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import ShareMediaItem from '@D/components/share/ShareMediaItem.vue';
	import MarginalTextButton from '@D/components/inter-ui/buttons/MarginalTextButton.vue';

	export default defineComponent({
		props: {
			mediaOptions: {
				type: Array,
				required: true
			},
			link: {
				type: String,
				required: true
			}
		},
		setup: function(props, context) {

			return {
				copyLink: () => {
					navigator.clipboard.writeText(props.link).then(() => {
                        toastSuccess(__t('toast.share_copied'), 1000);
                    });
				}
			};
		},
		components: {
			ShareMediaItem: ShareMediaItem,
			MarginalTextButton: MarginalTextButton
		}
	});
</script>