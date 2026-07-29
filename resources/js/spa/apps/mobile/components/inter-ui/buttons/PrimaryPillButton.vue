<template>
    <button class="leading-none inline-flex justify-center rounded-full overflow-hidden font-semibold disabled:opacity-60 cursor-pointer" v-bind:type="buttonType" v-bind:disabled="disableButton" v-bind:class="[btnStylingClasses, btnSizes[buttonSize], ((buttonFluid) ? 'w-full' : '')]">
        <span v-if="loading" class="inline-block px-4">&nbsp;<span class="inline-block colibri-primary-animation"></span>&nbsp;</span>
        <span v-else class="truncate overflow-hidden">{{ buttonText }}</span>
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
            buttonText: {
                type: String,
                default: 'Label'
            },
            buttonType: {
                type: String,
                default: 'button'
            },
            buttonSize: {
                type: String,
                default: 'lg'
            },
            buttonFluid: {
                type: Boolean,
                default: false
            },
            isDisabled: {
                type: Boolean,
                default: false
            },
            buttonRole: {
                type: String,
                default: 'default'
            }
        },
        setup: function(props) {
            const btnStyles = {
                accent: 'bg-lab-pr2 text-bg-pr border border-lab-pr2',
                stroked: 'text-lab-pr border-bord-card border',
                default: 'text-lab-pr bg-fill-qt border border-fill-fv',
                danger: 'text-red-900 bg-fill-qt hover:bg-fill-qt border border-fill-qt',
                marginal: 'text-lab-sc hover:bg-fill-qt border border-transparent',
                marginalDisabled: 'text-lab-sc border border-transparent',
                marginalDanger: 'text-red-900 hover:bg-fill-qt border border-transparent'
            };

            const btnSizes = {
                lg: 'py-4 px-5 text-par-m',
                md: 'py-3 px-4 text-par-m',
                sm: 'py-2.5 px-4 text-par-m'
            };

            return {
                btnSizes: btnSizes,
                btnStylingClasses: computed(() => {
                    if (props.buttonRole === 'accent' && props.loading) {
                        return btnStyles['default'];
                    }

                    return btnStyles[props.buttonRole];
                }),
                disableButton: computed(() => {
                    return props.loading || props.isDisabled;
                })
            }
        }
    });
</script>