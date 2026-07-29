<template>
    <button v-bind:type="buttonType"
        v-bind:class="[disabled ? 'opacity-70 cursor-default' : 'cursor-pointer', hoverBg, buttonColor, hoverText, `size-${iconAreaSize}`]"
    class="outline-hidden transition-transform duration-300 inline-flex items-center justify-center rounded-full leading-none disabled:opacity-70 disabled:cursor-default">
        <SvgIcon 
            v-bind:name="iconName"
            v-bind:type="iconType"
        v-bind:classes="`size-${iconSize}`"></SvgIcon>
    </button>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            disabled: {
                type: Boolean,
                default: false
            },
            iconName: {
                type: String,
                default: 'x' 
            },
            buttonType: {
                type: String,
                default: 'button'
            },
            iconAreaSize: {
                type: String,
                default: '8'
            },
            iconType: {
                type: String,
                default: 'solid' 
            },
            iconSize: {
                type: String,
                default: '6'
            },
            buttonColor: {
                type: String,
                default: 'text-lab-pr' 
            },
            hoverBg: {
                type: [String, Boolean],
                default: 'hover:bg-fill-tr' 
            },
            hoverText: {
                type: [String, Boolean],
                default: 'hover:text-lab-pr2' 
            }
        },
        setup: function(props) {
            const btnClasses = ['size-6', props.buttonColor];

            return {
                btnClasses: btnClasses.join(' ')
            }
        }
    });
</script>