<template>
	<div class="py-1 px-4 border-b border-bord-pr bg-fill-fv">
		<div class="flex overflow-hidden items-center">
			<div class="flex-1 overflow-hidden text-par-s">
				<span class="text-lab-sc">{{ $t('labels.reply_to') }}</span>
				&nbsp;
				<span class="font-semibold text-lab-pr2">
					{{ repliedComment.relations.user.name }}
				</span>
				<span class="text-par-s text-lab-sc leading-none ml-1 font-normal">
					{{ repliedComment.date.time_ago }}
				</span>
			</div>
			<div class="shrink-0">
				<PrimaryIconButton v-on:click="$emit('cancel')" name="x-close"></PrimaryIconButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		props: {
			repliedComment: {
				type: Object,
				default: {}
			}
		},
		emits: ['cancel'],
		components: {
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>