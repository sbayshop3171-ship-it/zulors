<template>
	<div class="flex items-center leading-none h-12 link-item-container px-4" v-bind:class="[disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer']">
		<span class="shrink-0">
			<SvgIcon v-bind:type="iconType" v-bind:name="iconName" v-bind:classes="['size-6', ...linkColor].join(' ')"></SvgIcon>
		</span>
		<span v-bind:class="['ml-3', 'text-par-l', ...linkColor]">
			{{ textLabel }}
		</span>
		<span class="shrink-0 ml-auto">
			<SvgIcon name="chevron-right" classes="size-5 text-lab-sc"></SvgIcon>
		</span>
	</div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            linkColor: {
                type: Array,
                default: ['text-lab-pr2']
            },
            iconType: {
                type: String,
                default: 'line'
            },
            iconName: {
                type: String,
                default: ''
            },
            textLabel: {
                type: String,
                default: ''
            },
            disabled: {
                type: Boolean,
                default: false
            }
        }
    });
</script>