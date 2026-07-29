<template>
	<div>
		<div class="flex gap-1 overflow-x-auto px-4 hidden-scroll">
			<button class="w-20 shrink-0">
				<ShareMediaItem
					v-on:click="copyLink"
					iconName="link-01"
					iconType="line"
				v-bind:buttonText="$t('labels.copy_link')"></ShareMediaItem>
			</button>

			<a v-for="mediaItem in mediaOptions" v-bind:href="mediaItem.url + link" target="_blank" class="w-20 shrink-0">
				<ShareMediaItem v-bind:iconName="mediaItem.icon" v-bind:buttonText="mediaItem.name"></ShareMediaItem>
			</a>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import ShareMediaItem from '@M/components/share/ShareMediaItem.vue';
	import MarginalTextButton from '@D/components/inter-ui/buttons/MarginalTextButton.vue';

	export default defineComponent({
		emits: ['close'],
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
					try {
						navigator.clipboard.writeText(props.link).then(() => {
							toastSuccess(__t('toast.share_copied'));
						});
					} catch (error) {
						toastError(__t('toast.share_copied'));
					}

					context.emit('close');
				}
			};
		},
		components: {
			ShareMediaItem: ShareMediaItem,
			MarginalTextButton: MarginalTextButton
		}
	});
</script>