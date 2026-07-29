<template>
	<div class="block">
		<button v-bind:type="buttonType" v-bind:disabled="disabled"
			class="flex w-full items-center py-4 px-4 hover:bg-fill-qt gap-3 overflow-hidden cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed"
		v-bind:class="[buttonTextColors[buttonRole]]">
			<span class="size-icon-normal shrink-0">
				<SvgIcon v-bind:name="iconName" v-bind:type="iconType"></SvgIcon>
			</span>
			<span class="text-par-n font-medium truncate">
				{{ buttonText }}
			</span>
			<span v-if="loading" class="inline-block px-4 ml-auto"><div class="colibri-primary-animation"></div></span>
			<template v-else>
				<span v-if="hasArrow" class="size-icon-small shrink-0 ml-auto">
					<SvgIcon name="chevron-right" type="solid" classes="size-icon-small"></SvgIcon>
				</span>
			</template>
		</button>
	</div>
</template>

<script>
	import { defineComponent, ref } from 'vue';

	export default defineComponent({
		props: {
			buttonText: {
                type: String,
                default: 'Label'
            },
			popovertarget: {
				type: String,
				default: ''
			},
			disabled: {
				type: Boolean,
				default: false
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
			buttonRole: {
                type: String,
                default: 'default'
            },
			hasArrow: {
				type: Boolean,
				default: false
			},
			loading: {
				type: Boolean,
				default: false
			}
		},
		setup(props, context) {
			const buttonTextColors = {
                default: ['text-lab-pr2'],
                danger: ['text-red-900'],
            };

			return {
				buttonTextColors: buttonTextColors
			};
		}
	});
</script>