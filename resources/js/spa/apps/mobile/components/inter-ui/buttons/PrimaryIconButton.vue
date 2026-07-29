<template>
    <button type="button"
        v-bind:class="[hoverBg, buttonColor, hoverText, `size-${iconAreaSize}`]"
    class="outline-hidden cursor-pointer inline-flex active:bg-fill-tr items-center justify-center rounded-full leading-zero disabled:opacity-70 disabled:cursor-default">
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
            iconName: {
                type: String,
                default: 'x' 
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
                default: 'text-lab-pr2' 
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