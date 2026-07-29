<template>
	<div class="flex gap-2.5 leading-none">
		<div class="shrink-0 text-lab-pr2">
			<div class="size-small-avatar flex-center bg-fill-tr rounded-full">
				<div class="size-icon-small text-lab-pr2">
					<SvgIcon v-bind:name="iconName" v-bind:type="iconType"></SvgIcon>
				</div>
			</div>
		</div>
		<div class="flex-1">
			<h5 class="font-semibold text-par-n text-lab-pr mb-1.5">
				{{ labelText }}
			</h5>
			<p class="text-lab-sc text-par-n">
				{{ contentText }}
			</p>
		</div>
		<div v-if="isCopyable" class="shrink-0 text-lab-pr2">
			<PrimaryIconButton
				v-if="copied"
				iconName="check-circle"
				buttonColor="text-green-500"
				iconType="solid"
			iconSize="icon-small"></PrimaryIconButton>
			<PrimaryIconButton
				v-else
				iconName="copy-06"
				iconType="line"
				iconSize="icon-small"
			v-on:click="copyContent"></PrimaryIconButton>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref } from 'vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		props: {
			labelText: {
				type: String,
				default: ''
			},
			isCopyable: {
				type: Boolean,
				default: false
			},
			contentText: {
				type: String,
				default: ''
			},
			iconName: {
				type: String,
				default: ''
			},
			iconType: {
				type: String,
				default: 'line'
			}
		},
		setup: function (props) {
			const copied = ref(false);

			return { 
				copied: copied,
				copyContent: () => {
					navigator.clipboard.writeText(props.contentText).then(() => {
						copied.value = true;

						setTimeout(() => {
							copied.value = false;
						}, 2000);
					});
				}
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>