<template>
    <button v-bind:type="buttonType" v-bind:disabled="disableButton" class="inline-flex justify-center outline-hidden items-center gap-1 cursor-pointer leading-none disabled:cursor-not-allowed disabled:opacity-60" v-bind:class="[((buttonFluid) ? 'w-full' : ''),btnColor, (iconPosition === 'left') ? 'flex-row-reverse' : '' ]">
        <span class="text-par-n font-medium">
            {{ buttonText }}
        </span>
        <template v-if="$slots.buttonIcon">
            <span v-show="! loading" class="size-icon-x-small">
                <slot name="buttonIcon"></slot>
            </span>
        </template>

        <span v-if="loading" class="inline-flex items-center px-4 h-4">
            <div class="colibri-primary-animation"></div>
        </span>
    </button>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    export default defineComponent({
        props: {
            loading: {
                type: Boolean,
                default: false
            },
            buttonFluid: {
                type: Boolean,
                default: false
            },
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
            iconPosition: {
                type: String,
                default: 'right'
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