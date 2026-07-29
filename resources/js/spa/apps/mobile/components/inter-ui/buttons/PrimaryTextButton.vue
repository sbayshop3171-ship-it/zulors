<template>
    <button v-bind:type="buttonType" v-bind:disabled="disableButton" class="inline-flex justify-center outline-hidden items-center gap-1 leading-none disabled:opacity-60" v-bind:class="[btnColor]">
        <span class="text-par-m" v-bind:class="[bold ? 'font-semibold' : 'font-normal']">
            {{ buttonText }}
        </span>
    </button>
</template>

<script>
    import { defineComponent, computed } from 'vue';

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
            isDisabled: {
                type: Boolean,
                default: false
            },
            buttonRole: {
                type: String,
                default: 'default'
            },
            bold: {
                type: Boolean,
                default: false
            }
        },
        setup: function(props) {
            const btnStyles = {
                default: 'text-brand-900',
                danger: 'text-red-900',
                marginal: 'text-lab-sc'
            };

            return {
                btnColor: computed(() => {
                    return props.loading ? 'text-lab-sc' : btnStyles[props.buttonRole];
                }),
                disableButton: computed(() => {
                    return props.loading || props.isDisabled;
                })
            }
        }
    });
</script>