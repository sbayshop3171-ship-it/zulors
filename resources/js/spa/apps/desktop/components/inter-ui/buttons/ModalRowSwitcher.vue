<template>
	<div class="block">
		<button v-bind:type="buttonType" class="flex w-full items-center py-4 px-4 hover:bg-fill-qt gap-2 overflow-hidden text-lab-pr2">
			<span class="size-icon-small shrink-0">
				<SvgIcon v-bind:name="iconName" v-bind:type="iconType" classes="size-icon-small"></SvgIcon>
			</span>
			<span class="text-par-s truncate">
				{{ buttonText }}
			</span>
			<span class="shrink-0 ml-auto">
				<SecondarySwitcher v-bind:modelValue="modelValue" v-model="switcherValue"></SecondarySwitcher>
			</span>
		</button>
	</div>
</template>

<script>
	import { defineComponent, ref, watch } from 'vue';
	import SecondarySwitcher from '@D/components/inter-ui/switchers/SecondarySwitcher.vue';
	
	export default defineComponent({
		props: {
			buttonText: {
                type: String,
                default: 'Label'
            },
            buttonType: {
                type: String,
                default: 'button'
            },
			iconName: {
                type: String,
                default: 'x' 
            },
			iconType: {
                type: String,
                default: 'line' 
            },
			iconColor: {
                type: String,
                default: 'text-lab-pr2' 
            },
			modelValue: {
				type: Boolean,
				default: false
			}
		},
		emits: ['update:modelValue'],
		setup(props, context) {
			const switcherValue = ref(props.modelValue);

            watch(switcherValue, (newValue) => {
                context.emit('update:modelValue', newValue);
            });

			return {
				switcherValue: switcherValue
			};
		},
		components: {
			SecondarySwitcher: SecondarySwitcher
		}
	});
</script>